<?php
require_once __DIR__ . "/includes/auth.php";
require_admin();
$page_title = "Manage Accounts";

/** Read and validate the account form. Returns [data, errors]. */
function account_input($creating) {
    $d = [
        "username"  => trim((string)($_POST["username"] ?? "")),
        "full_name" => trim((string)($_POST["full_name"] ?? "")),
        "role"      => (string)($_POST["role"] ?? "staff"),
        "status"    => (string)($_POST["status"] ?? "Active"),
        "password"  => is_string($_POST["password"] ?? null) ? $_POST["password"] : "",
    ];

    $errors = [];

    if (!preg_match('/^[A-Za-z0-9._-]{3,50}$/', $d["username"])) {
        $errors[] = "Username must be 3-50 characters: letters, numbers, dot, dash or underscore.";
    }
    if ($d["full_name"] === "" || mb_strlen($d["full_name"]) > 120) {
        $errors[] = "Full name is required (max 120 characters).";
    }
    if (!in_array($d["role"], ["admin", "staff"], true)) {
        $errors[] = "Invalid role.";
    }
    if (!in_array($d["status"], ["Active", "Inactive"], true)) {
        $errors[] = "Invalid status.";
    }

    // Password is required when creating; when editing, blank means "keep the current one"
    if ($creating || $d["password"] !== "") {
        if (strlen($d["password"]) < 8) {
            $errors[] = "Password must be at least 8 characters.";
        }
        if (strlen($d["password"]) > 72) {
            $errors[] = "Password is too long (max 72 characters).";
        }
    }

    return [$d, $errors];
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    verify_csrf();
    $action = $_POST["action"] ?? "";
    $me = $_SESSION["user"];

    if ($action === "create") {
        [$d, $errors] = account_input(true);

        if ($errors) {
            flash("error", implode(" ", $errors));
        } else {
            try {
                $pdo->prepare("INSERT INTO users(username,password,full_name,role,status) VALUES(?,?,?,?,?)")
                    ->execute([$d["username"], password_hash($d["password"], PASSWORD_DEFAULT), $d["full_name"], $d["role"], "Active"]);
                record_activity($me["id"], $me["username"], "create_user", "Created account: " . $d["username"]);
                flash("success", "Account created.");
            } catch (PDOException $e) {
                if ($e->getCode() === "23000") { // duplicate username
                    flash("error", "The username '{$d['username']}' is already taken.");
                } else {
                    error_log("Create user failed: " . $e->getMessage());
                    flash("error", "Could not create the account.");
                }
            }
        }
    } elseif ($action === "edit") {
        $id = (int)($_POST["id"] ?? 0);
        [$d, $errors] = account_input(false);
        if ($id < 1) {
            $errors[] = "Account not found.";
        }
        // The signed-in admin can never demote or deactivate themselves,
        // so there is always at least one active admin.
        if ($id === (int)$me["id"] && ($d["role"] !== "admin" || $d["status"] !== "Active")) {
            $errors[] = "You cannot demote or deactivate your own account.";
        }

        if ($errors) {
            flash("error", implode(" ", $errors));
        } else {
            try {
                $sql = "UPDATE users SET username=?, full_name=?, role=?, status=?";
                $params = [$d["username"], $d["full_name"], $d["role"], $d["status"]];
                if ($d["password"] !== "") {
                    $sql .= ", password=?";
                    $params[] = password_hash($d["password"], PASSWORD_DEFAULT);
                }
                $sql .= " WHERE id=?";
                $params[] = $id;

                $pdo->prepare($sql)->execute($params);
                record_activity($me["id"], $me["username"], "edit_user", "Updated account: " . $d["username"]);
                flash("success", "Account updated.");
            } catch (PDOException $e) {
                if ($e->getCode() === "23000") {
                    flash("error", "The username '{$d['username']}' is already taken.");
                } else {
                    error_log("Edit user failed: " . $e->getMessage());
                    flash("error", "Could not update the account.");
                }
            }
        }
    } elseif ($action === "delete") {
        $id = (int)($_POST["id"] ?? 0);

        if ($id < 1) {
            flash("error", "Account not found.");
        } elseif ($id === (int)$me["id"]) {
            flash("error", "You cannot delete your own account.");
        } else {
            try {
                $stmt = $pdo->prepare("SELECT username FROM users WHERE id = ? LIMIT 1");
                $stmt->execute([$id]);
                $account = $stmt->fetch();

                $check = $pdo->prepare("SELECT COUNT(*) FROM transactions WHERE user_id = ?");
                $check->execute([$id]);

                if (!$account) {
                    flash("error", "Account not found.");
                } elseif ((int)$check->fetchColumn() > 0) {
                    flash("error", "This account has transaction history and cannot be deleted. Set its status to Inactive instead.");
                } else {
                    $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$id]);
                    record_activity($me["id"], $me["username"], "delete_user", "Deleted account: " . $account["username"]);
                    flash("success", "Account deleted.");
                }
            } catch (Throwable $e) {
                error_log("Delete user failed: " . $e->getMessage());
                flash("error", "Could not delete the account.");
            }
        }
    }

    redirect("users.php"); // refreshing the page no longer repeats the action
}

$users = $pdo->query("SELECT id,username,full_name,role,status,created_at FROM users ORDER BY full_name")->fetchAll();
require_once __DIR__.'/includes/list_filters.php';
$activitySize=($_GET['activity_show'] ?? '')==='20'?20:5;
$activityTotal=(int)$pdo->query('SELECT COUNT(*) FROM activity_log')->fetchColumn();
$activityPager=clinic_pager($activityTotal,$_GET['activity_page'] ?? 1,$activitySize);
$recent_activity=$pdo->query('SELECT * FROM activity_log ORDER BY created_at DESC,id DESC LIMIT '.$activitySize.' OFFSET '.$activityPager['offset'])->fetchAll();
include __DIR__ . "/includes/header.php";
render_flash();
?>
<section class="panel">
<div class="list-heading-actions"><h2>Accounts</h2><button type="button" class="btn primary" data-open-dialog="create-account">Create Account</button></div>
<table>
<thead>
<tr><th>Username</th><th>Name</th><th>Role</th><th>Status</th><th>Created</th><th>Actions</th></tr>
</thead>
<tbody>
<?php foreach ($users as $u): $is_me = (int)$u["id"] === (int)$_SESSION["user"]["id"]; ?>
<tr>
    <td><?= h($u["username"]) ?><?= $is_me ? " (you)" : "" ?></td>
    <td><?= h($u["full_name"]) ?></td>
    <td><?= h($u["role"] === "admin" ? "Administrator" : "Staff") ?></td>
    <td><span class="badge <?= $u["status"] === "Active" ? "success" : "warning" ?>"><?= h($u["status"]) ?></span></td>
    <td><?= h($u["created_at"]) ?></td>
    <td>
        <div class="action-stack">
            <button type="button" class="btn small secondary"
                onclick="document.getElementById('edit-<?= (int)$u["id"] ?>').showModal()">Edit</button>
            <?php if (!$is_me): ?>
            <form method="post" onsubmit="return confirm('Delete this account?')">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= (int)$u["id"] ?>">
                <button class="btn small danger" type="submit">Delete</button>
            </form>
            <?php endif; ?>
        </div>

        <dialog id="edit-<?= (int)$u["id"] ?>">
            <h3>Edit <?= h($u["username"]) ?></h3>
            <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" value="<?= (int)$u["id"] ?>">
                <div class="form-grid">
                    <label>Username<input type="text" name="username" value="<?= h($u["username"]) ?>" maxlength="50" required></label>
                    <label>Full Name<input type="text" name="full_name" value="<?= h($u["full_name"]) ?>" maxlength="120" required></label>
                    <label>Role
                        <select name="role" <?= $is_me ? "disabled" : "" ?>>
                            <option value="staff" <?= $u["role"] === "staff" ? "selected" : "" ?>>Clinic Staff</option>
                            <option value="admin" <?= $u["role"] === "admin" ? "selected" : "" ?>>Clinic Administrator</option>
                        </select>
                    </label>
                    <label>Status
                        <select name="status" <?= $is_me ? "disabled" : "" ?>>
                            <option value="Active" <?= $u["status"] === "Active" ? "selected" : "" ?>>Active</option>
                            <option value="Inactive" <?= $u["status"] === "Inactive" ? "selected" : "" ?>>Inactive</option>
                        </select>
                    </label>
                    <label>New Password (leave blank to keep current)
                        <input type="password" name="password" minlength="8" maxlength="72" autocomplete="new-password">
                    </label>
                </div>
                <?php if ($is_me): // disabled fields are not submitted, so send the unchanged values ?>
                    <input type="hidden" name="role" value="admin">
                    <input type="hidden" name="status" value="Active">
                <?php endif; ?>
                <div class="form-actions">
                    <button type="button" class="btn secondary" onclick="this.closest('dialog').close()">Cancel</button>
                    <button type="submit" class="btn primary">Save Changes</button>
                </div>
            </form>
        </dialog>
    </td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</section>

<section class="panel">
<h2 id="recent-activity">Recent Activity</h2>
<div class="list-pagination"><span><?= $activityTotal ?> activity entries ? showing up to <?= $activitySize ?> per page</span><?php if($activitySize===5 && $activityTotal>5): ?><a class="btn secondary" href="?activity_show=20#recent-activity">Show more</a><?php elseif($activitySize===20): ?><a class="btn secondary" href="?activity_show=5#recent-activity">Show less</a><?php endif; ?></div>
<?php if (!$recent_activity): ?>
    <p>No recent activity.</p>
<?php else: ?>
<table>
<thead><tr><th>Username</th><th>Action</th><th>Details</th><th>Time</th></tr></thead>
<tbody>
<?php foreach ($recent_activity as $log): ?>
<tr>
    <td><?= h($log["username"]) ?></td>
    <td><?= h(ucfirst(str_replace("_", " ", $log["action"]))) ?></td>
    <td><?= h($log["details"]) ?></td>
    <td><?= h($log["created_at"]) ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
<?php endif; ?>
<?php if($activityPager['pages']>1): ?><nav class="list-pagination" aria-label="Activity history pages"><span>Page <?= $activityPager['page'] ?> of <?= $activityPager['pages'] ?></span><?php if($activityPager['page']>1): ?><a class="btn secondary" href="?<?= h(http_build_query(['activity_show'=>$activitySize,'activity_page'=>$activityPager['page']-1])) ?>#recent-activity">Newer entries</a><?php endif; ?><?php if($activityPager['page']<$activityPager['pages']): ?><a class="btn secondary" href="?<?= h(http_build_query(['activity_show'=>$activitySize,'activity_page'=>$activityPager['page']+1])) ?>#recent-activity">Older entries</a><?php endif; ?></nav><?php endif; ?>
</section>

<dialog id="create-account" class="record-dialog" aria-labelledby="create-account-title"><div class="record-dialog-heading"><h3 id="create-account-title">Create Account</h3><button type="button" class="btn secondary" onclick="this.closest('dialog').close()">Close</button></div>
<form method="post" class="form-grid">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="create">
    <label>Username<input name="username" maxlength="50" required></label>
    <label>Full Name<input name="full_name" maxlength="120" required></label>
    <label>Password (min. 8 characters)<input type="password" name="password" minlength="8" maxlength="72" autocomplete="new-password" required></label>
    <label>Role
        <select name="role">
            <option value="staff">Clinic Staff</option>
            <option value="admin">Clinic Administrator</option>
        </select>
    </label>
    <div><button class="btn primary">Create Account</button></div>
</form>
</dialog>
<?php include __DIR__ . "/includes/footer.php"; ?>