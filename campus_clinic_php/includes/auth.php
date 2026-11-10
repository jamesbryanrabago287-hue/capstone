<?php
// Prefer the deployment configuration; support the existing local XAMPP configuration.
require_once is_file(__DIR__ . '/config.php')
    ? __DIR__ . '/config.php'
    : __DIR__ . '/config.example.php';

/* ---------- Activity log ---------- */

function ensure_activity_log_table() {
    global $pdo;
    // Check once per login session instead of on every page load
    if (!$pdo || !empty($_SESSION["_activity_table_ready"])) {
        return;
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS activity_log (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NULL,
        username VARCHAR(100) NOT NULL,
        action VARCHAR(30) NOT NULL,
        details VARCHAR(255) DEFAULT '',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        KEY idx_activity_user (user_id),
        KEY idx_activity_time (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $_SESSION["_activity_table_ready"] = true;
}

function record_activity($user_id, $username, $action, $details = "") {
    global $pdo;
    if (!$pdo || !$username || !$action) {
        return;
    }

    // A logging problem must never break the action the user just did
    try {
        $stmt = $pdo->prepare("INSERT INTO activity_log(user_id, username, action, details, created_at) VALUES(?,?,?,?,NOW())");
        $stmt->execute([
            intval($user_id),
            mb_substr(trim((string)$username), 0, 100),
            trim((string)$action),
            mb_substr(trim((string)$details), 0, 255), // column is VARCHAR(255)
        ]);
    } catch (Throwable $e) {
        error_log("record_activity failed: " . $e->getMessage());
    }
}

/* ---------- Stock level ---------- */

function stock_level_label($quantity, $reorder_point) {
    $qty = (int)$quantity;
    $point = max(0, (int)$reorder_point);

    if ($qty <= $point) {
        return ["label" => "Low", "class" => "low"];
    }

    if ($qty <= ($point * 1.5)) {
        return ["label" => "Medium", "class" => "medium"];
    }

    return ["label" => "High", "class" => "high"];
}

require_once __DIR__.'/expiry_settings.php';

/** Expiry badge info using the clinic warning period. */
function expiry_status($date) {
    $ts = strtotime((string)$date);
    if ($ts === false) {
        return null;
    }
    if ($ts < strtotime("today")) {
        return ["label" => "Expired", "class" => "red"];
    }
    if ($ts <= strtotime("+".expiry_warning_days()." days",strtotime("today"))) {
        return ["label" => "Expiring soon", "class" => "warning"];
    }
    return null;
}

require_once __DIR__.'/decision_support.php';
require_once __DIR__.'/medicine_groups.php';

/* ---------- Reorder decision support (single source of truth) ----------
 * Used by analytics.php and reports.php so the two pages can never disagree.
 *
 * Rules:
 *  - Only usable (non-expired) stock counts as available.
 *  - A medicine needs reordering when usable stock is at or below its reorder point,
 *    OR it would run out in under 14 days at the last 30 days' usage rate.
 *  - Suggested quantity restocks up to the larger of: twice the reorder point,
 *    or one month (30 days) of usage.
 */
function reorder_advice($usable_qty, $reorder_point, $usage_30d) {
    $usable = max(0, (int)$usable_qty);
    $point  = max(0, (int)$reorder_point);
    $usage  = max(0, (int)$usage_30d);

    $days_left = $usage > 0 ? (int)floor($usable / ($usage / 30)) : null;
    $target    = max($point * 2, $usage);
    $order_qty = max($target - $usable, 0);

    $low_stock   = $usable <= $point;
    $running_out = $days_left !== null && $days_left < 14;
    $needs       = $order_qty > 0 && ($low_stock || $running_out);

    $reason = "";
    if ($needs) {
        $reason = $low_stock ? "At or below reorder point" : "About {$days_left} days of stock left";
    }

    return [
        "needs"     => $needs,
        "order_qty" => $needs ? $order_qty : 0,
        "days_left" => $days_left,
        "reason"    => $reason,
    ];
}

/** Every medicine with its 30-day usage and reorder advice, in ONE query. */
function reorder_list() {
    global $pdo;
    ensure_medicine_groups($pdo);

    $rows = $pdo->query(
        "SELECT m.*,g.reorder_point AS shared_reorder_point,
                m.quantity, m.reorder_point, m.expiration_date,
                GREATEST(1,DATEDIFF(CURDATE(),m.created_at)+1) AS record_age_days,
                (m.expiration_date < CURDATE()) AS is_expired,
                IF(m.expiration_date >= CURDATE(), m.quantity, 0) AS usable_qty,
                COALESCE(u.used30, 0) AS used30, COALESCE(u.used90,0) AS used90
         FROM medicines m
         LEFT JOIN medicine_reorder_groups g ON g.id=m.reorder_group_id
         LEFT JOIN (
             SELECT medicine_id, SUM(CASE WHEN transaction_date >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN quantity ELSE 0 END) AS used30, SUM(quantity) AS used90
             FROM transactions
             WHERE type = 'Stock Out' AND transaction_date >= DATE_SUB(NOW(), INTERVAL 90 DAY) AND transaction_date <= NOW()
             GROUP BY medicine_id
         ) u ON u.medicine_id = m.id
         WHERE m.archived_at IS NULL
         ORDER BY m.name"
    )->fetchAll();

    return aggregate_reorder_batches($rows);
}

/* ---------- Login / access control ---------- */

/** Start a logged-in session. Stores only what the pages need, never the password hash. */
function login_user(array $row) {
    session_regenerate_id(true);   // new session ID after login (prevents session fixation)
    $_SESSION["user"] = [
        "id"        => (int)$row["id"],
        "username"  => $row["username"],
        "full_name" => $row["full_name"],
        "role"      => $row["role"],
    ];
    unset($_SESSION["csrf"]);      // fresh CSRF token for the new session
}

/** True if this username failed to sign in too many times recently. */
function too_many_failed_logins($username, $max = 5, $minutes = 15) {
    global $pdo;
    $minutes = (int)$minutes;
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM activity_log
                           WHERE action = 'login_failed' AND username = ?
                           AND created_at > (NOW() - INTERVAL {$minutes} MINUTE)");
    $stmt->execute([$username]);
    return (int)$stmt->fetchColumn() >= $max;
}

function require_login() {
    global $pdo;

    if (!isset($_SESSION["user"]["id"])) {
        redirect("login.php");
    }

    // Re-check the account on every page, so a deactivated, deleted or demoted
    // user loses access immediately instead of at their next login.
    $stmt = $pdo->prepare("SELECT id, username, full_name, role, status FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([(int)$_SESSION["user"]["id"]]);
    $row = $stmt->fetch();

    if (!$row || $row["status"] !== "Active") {
        unset($_SESSION["user"]);
        flash("error", "Your account is no longer active. Please contact the administrator.");
        redirect("login.php");
    }

    $_SESSION["user"] = [
        "id"        => (int)$row["id"],
        "username"  => $row["username"],
        "full_name" => $row["full_name"],
        "role"      => $row["role"],
    ];
}

function is_admin() {
    return isset($_SESSION["user"]["role"]) && $_SESSION["user"]["role"] === "admin";
}

function require_admin() {
    require_login();
    if (!is_admin()) {
        http_response_code(403);
        exit("Access denied.");
    }
}

/* ---------- Output escaping ---------- */

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

/* ---------- Redirect + flash messages (stops duplicate form submits on refresh) ---------- */

function redirect($url) {
    header("Location: " . $url);
    exit;
}

function flash($type, $message) {
    $_SESSION["_flash"][] = ["type" => $type, "message" => $message];
}

function take_flash() {
    $items = $_SESSION["_flash"] ?? [];
    unset($_SESSION["_flash"]);
    return $items;
}

function render_flash() {
    foreach (take_flash() as $f) {
        $class = $f["type"] === "error" ? "danger" : "success";
        echo '<div class="alert ' . $class . '">' . h($f["message"]) . '</div>';
    }
}

/* ---------- CSRF protection ---------- */

function csrf_token() {
    if (empty($_SESSION["csrf"])) {
        $_SESSION["csrf"] = bin2hex(random_bytes(32));
    }
    return $_SESSION["csrf"];
}

function csrf_field() {
    return '<input type="hidden" name="csrf" value="' . h(csrf_token()) . '">';
}

function verify_csrf() {
    $sent = $_POST["csrf"] ?? "";
    if (!is_string($sent) || !hash_equals(csrf_token(), $sent)) {
        http_response_code(400);
        exit("Invalid or expired form. Please go back, refresh the page, and try again.");
    }
}

ensure_activity_log_table();