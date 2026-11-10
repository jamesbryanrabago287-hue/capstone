<?php
require_once __DIR__ . "/includes/auth.php";
require_login();
$page_title = "Reports: APP / PPMP / Purchase Request";

/* ---------- PPMP storage (created automatically, same pattern as activity_log) ---------- */
function ensure_ppmp_table() {
    global $pdo;
    if (!empty($_SESSION["_ppmp_table_ready"])) {
        return;
    }
    $pdo->exec("CREATE TABLE IF NOT EXISTS ppmp_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        fiscal_year SMALLINT NOT NULL,
        medicine_id INT NULL,
        product_category VARCHAR(100) NOT NULL DEFAULT '',
        description VARCHAR(255) NOT NULL DEFAULT '',
        unit VARCHAR(50) NOT NULL DEFAULT 'tablet',
        unit_cost DECIMAL(12,2) NOT NULL DEFAULT 0,
        acct_code VARCHAR(20) NOT NULL DEFAULT '10404070',
        acct_category VARCHAR(150) NOT NULL DEFAULT 'Medical, Dental and Laboratory Supplies',
        q1_qty INT NOT NULL DEFAULT 0,
        q2_qty INT NOT NULL DEFAULT 0,
        q3_qty INT NOT NULL DEFAULT 0,
        q4_qty INT NOT NULL DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        KEY idx_ppmp_year (fiscal_year)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $_SESSION["_ppmp_table_ready"] = true;
}
ensure_ppmp_table();

/* Adds generic_name / brand / description to medicines if missing (safe to run repeatedly). */
if (!function_exists("ensure_medicine_columns")) {
    function ensure_medicine_columns() {
        global $pdo;
        if (!empty($_SESSION["_med_cols_ready"])) {
            return;
        }
        $have = [];
        foreach ($pdo->query("SHOW COLUMNS FROM medicines")->fetchAll() as $c) {
            $have[$c["Field"]] = true;
        }
        $add = [
            "generic_name" => "VARCHAR(150) NOT NULL DEFAULT ''",
            "brand"        => "VARCHAR(150) NOT NULL DEFAULT ''",
            "description"  => "VARCHAR(255) NOT NULL DEFAULT ''",
        ];
        foreach ($add as $col => $def) {
            if (!isset($have[$col])) {
                $pdo->exec("ALTER TABLE medicines ADD COLUMN {$col} {$def}");
            }
        }
        $_SESSION["_med_cols_ready"] = true;
    }
}
ensure_medicine_columns();

const PPMP_DEFAULT_ACCT_CODE = "10404070";
const PPMP_DEFAULT_ACCT_CAT  = "Medical, Dental and Laboratory Supplies";

$fy = (int)($_REQUEST["fy"] ?? date("Y"));
if ($fy < 2000 || $fy > 2100) {
    $fy = (int)date("Y");
}

/* ---------- PPMP actions ---------- */
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    verify_csrf();
    $me = $_SESSION["user"];
    $action = $_POST["action"] ?? "";
    $back = "reports.php?type=PPMP&fy=" . $fy;

    if ($action === "ppmp_generate") {
        // Pull medicines that need reordering; skip ones already in this year's PPMP
        $cats = [];
        foreach ($pdo->query("SELECT id, category FROM medicines")->fetchAll() as $c) {
            $cats[(int)$c["id"]] = $c["category"];
        }
        $have = $pdo->prepare("SELECT medicine_id FROM ppmp_items WHERE fiscal_year = ? AND medicine_id IS NOT NULL");
        $have->execute([$fy]);
        $existing = array_map("intval", $have->fetchAll(PDO::FETCH_COLUMN));

        $ins = $pdo->prepare("INSERT INTO ppmp_items(fiscal_year,medicine_id,product_category,description,unit,unit_cost,acct_code,acct_category,q1_qty,q2_qty,q3_qty,q4_qty)
                              VALUES(?,?,?,?,?,?,?,?,?,?,?,?)");
        $added = 0;
        foreach (reorder_list() as $r) {
            $qty = (int)$r["advice"]["order_qty"];
            if (!$r["advice"]["needs"] || $qty < 1 || count(array_intersect($r["batch_ids"] ?? [(int)$r["id"]],$existing))>0) {
                continue;
            }
            $base = intdiv($qty, 4);
            $q1 = $qty - ($base * 3); // remainder goes to Q1
            $ins->execute([$fy, (int)$r["id"], $cats[(int)$r["id"]] ?? "", $r["name"], $r["unit"], $r["unit_cost"],
                           PPMP_DEFAULT_ACCT_CODE, PPMP_DEFAULT_ACCT_CAT, $q1, $base, $base, $base]);
            $added++;
        }
        record_activity($me["id"], $me["username"], "ppmp_generate", "Generated PPMP FY {$fy}: {$added} items");
        flash("success", "{$added} item(s) added to the FY {$fy} PPMP. Review the quantities, then save.");

    } elseif ($action === "ppmp_add") {
        $pdo->prepare("INSERT INTO ppmp_items(fiscal_year, acct_code, acct_category) VALUES(?,?,?)")
            ->execute([$fy, PPMP_DEFAULT_ACCT_CODE, PPMP_DEFAULT_ACCT_CAT]);
        flash("success", "Blank row added. Fill it in and save.");

    } elseif ($action === "ppmp_save") {
        if (isset($_POST["delete_id"])) {
            $pdo->prepare("DELETE FROM ppmp_items WHERE id = ? AND fiscal_year = ?")
                ->execute([(int)$_POST["delete_id"], $fy]);
            flash("success", "Row removed.");
        } else {
            $upd = $pdo->prepare("UPDATE ppmp_items SET product_category=?, description=?, unit=?, unit_cost=?, acct_code=?, acct_category=?,
                                  q1_qty=?, q2_qty=?, q3_qty=?, q4_qty=? WHERE id=? AND fiscal_year=?");
            $rows_in = is_array($_POST["rows"] ?? null) ? $_POST["rows"] : [];
            foreach ($rows_in as $id => $r) {
                if (!is_array($r)) { continue; }
                $q = function ($k) use ($r) { return max(0, min(1000000, (int)($r[$k] ?? 0))); };
                $upd->execute([
                    mb_substr(trim((string)($r["product_category"] ?? "")), 0, 100),
                    mb_substr(trim((string)($r["description"] ?? "")), 0, 255),
                    mb_substr(trim((string)($r["unit"] ?? "")), 0, 50),
                    max(0, min(1000000, (float)($r["unit_cost"] ?? 0))),
                    mb_substr(trim((string)($r["acct_code"] ?? "")), 0, 20),
                    mb_substr(trim((string)($r["acct_category"] ?? "")), 0, 150),
                    $q("q1_qty"), $q("q2_qty"), $q("q3_qty"), $q("q4_qty"),
                    (int)$id, $fy,
                ]);
            }
            record_activity($me["id"], $me["username"], "ppmp_save", "Saved PPMP FY {$fy}");
            flash("success", "PPMP saved.");
        }
    }
    redirect($back);
}

/* ---------- PR / APP data ---------- */
$rows = array_values(array_filter(reorder_list(), function ($r) {
    return $r["advice"]["needs"] && $r["advice"]["order_qty"] > 0;
}));

// Generic name / brand / description, looked up by medicine id (works whatever reorder_list() returns)
$med_extra = [];
foreach ($pdo->query("SELECT id, generic_name, brand, description FROM medicines")->fetchAll() as $m) {
    $med_extra[(int)$m["id"]] = $m;
}

$docs = [
    "PR" => [
        "title"      => "Purchase Request",
        "purpose"    => "For replenishment of clinic medicine stock at or below reorder level.",
        "heads"      => ["Generic Name", "Description", "Brand", "Quantity", "Unit", "Unit Price"],
        "signatories" => ["Requested by", "Recommended by", "Approved by"],
    ],
    "APP" => [
        "title"      => "Annual Procurement Plan (APP)",
        "purpose"    => "Medicines proposed for inclusion in the annual procurement plan.",
        "heads"      => ["Generic Name", "Description", "Brand", "Quantity", "Unit", "Unit Price"],
        "signatories" => ["Prepared by", "Reviewed by", "Approved by"],
    ],
    "PPMP" => [
        "title"      => "Project Procurement Management Plan (PPMP) FY " . $fy,
        "purpose"    => "Medicines and estimated budget for the clinic's medicine procurement, scheduled per quarter.",
        "heads"      => [],
        "signatories" => ["Prepared by", "Reviewed by", "Approved by"],
    ],
];

$type = $_GET["type"] ?? "PR";
if (!isset($docs[$type])) {
    $type = "PR";
}
$doc = $docs[$type];

function money($n) {
    return number_format((float)$n, 2);
}

/**
 * Builds the PR / APP table.
 * Columns: Generic Name, Description, Brand, Quantity, Unit, Unit Price.
 * Sets $total (Quantity x Unit Price) by reference.
 */
function report_table($type, array $doc, array $rows, &$total) {
    $total = 0;
    $html = '<table class="report-table report-preview-table"><thead><tr>';
    foreach ($doc["heads"] as $head) {
        $html .= '<th>' . h($head) . '</th>';
    }
    $html .= '</tr></thead><tbody>';

    foreach ($rows as $r) {
        $qty   = (int)$r["advice"]["order_qty"];
        $price = (float)$r["unit_cost"];
        $total += $qty * $price;

        global $med_extra;
        $x       = $med_extra[(int)($r["id"] ?? 0)] ?? [];
        $generic = ($x["generic_name"] ?? "") !== "" ? $x["generic_name"] : ($r["name"] ?? "");
        $desc    = $x["description"] ?? "";
        $brand   = $x["brand"] ?? "";

        $cells = [
            h($generic),
            h($desc),
            h($brand),
            $qty,
            h($r["unit"]),
            money($price),
        ];

        $html .= '<tr>';
        foreach ($cells as $i => $c) {
            $cls = ($i >= 3 && $i !== 4) ? ' class="num"' : '';
            $html .= '<td' . $cls . '>' . $c . '</td>';
        }
        $html .= '</tr>';
    }

    if (!$rows) {
        $html .= '<tr><td colspan="' . count($doc["heads"]) . '">No medicines currently need reordering.</td></tr>';
    }

    $html .= '</tbody><tfoot><tr><th colspan="' . (count($doc["heads"]) - 1) . '">Estimated Total (Quantity &times; Unit Price)</th>'
           . '<th class="num">' . money($total) . '</th></tr></tfoot></table>';
    return $html;
}

/**
 * Builds the quarterly PPMP table (clinic format).
 * $editable = true  -> inputs inside the app, $editable = false -> plain text for print.
 */
function ppmp_table(array $items, $editable, &$total) {
    $total = 0;
    $qtotals = [1 => 0, 2 => 0, 3 => 0, 4 => 0];

    $html = '<div class="ppmp-scroll"><table class="report-table ppmp-table"><thead><tr>'
          . '<th>Item #</th><th>Product Category</th><th>Description / Specifications</th><th>Quantity</th><th>Unit</th>'
          . '<th>Unit Cost</th><th>Total Estimated Budget</th><th>Acct Code</th><th>Acct Category</th>';
    for ($q = 1; $q <= 4; $q++) {
        $html .= "<th class=\"qcol\">Q{$q} Qty</th><th class=\"qcol\">Q{$q} Amount</th>";
    }
    $html .= $editable ? '<th></th>' : '';
    $html .= '</tr></thead><tbody>';

    $n = 0;
    foreach ($items as $it) {
        $n++;
        $id   = (int)$it["id"];
        $cost = (float)$it["unit_cost"];
        $qs   = [1 => (int)$it["q1_qty"], 2 => (int)$it["q2_qty"], 3 => (int)$it["q3_qty"], 4 => (int)$it["q4_qty"]];
        $qty  = array_sum($qs);
        $est  = $qty * $cost;
        $total += $est;

        $html .= '<tr><td>' . $n . '</td>';

        if ($editable) {
            $f = "rows[{$id}]";
            $html .= '<td><input name="' . $f . '[product_category]" value="' . h($it["product_category"]) . '" maxlength="100"></td>'
                   . '<td><input name="' . $f . '[description]" value="' . h($it["description"]) . '" maxlength="255"></td>'
                   . '<td class="num">' . $qty . '</td>'
                   . '<td><input name="' . $f . '[unit]" value="' . h($it["unit"]) . '" maxlength="50" class="w-unit"></td>'
                   . '<td><input type="number" step="0.01" min="0" name="' . $f . '[unit_cost]" value="' . number_format($cost, 2, ".", "") . '" class="w-num"></td>'
                   . '<td class="num">' . money($est) . '</td>'
                   . '<td><input name="' . $f . '[acct_code]" value="' . h($it["acct_code"]) . '" maxlength="20" class="w-code"></td>'
                   . '<td><input name="' . $f . '[acct_category]" value="' . h($it["acct_category"]) . '" maxlength="150"></td>';
            for ($q = 1; $q <= 4; $q++) {
                $qtotals[$q] += $qs[$q] * $cost;
                $html .= '<td class="qcell"><input type="number" min="0" name="' . $f . "[q{$q}_qty]\" value=\"{$qs[$q]}\" class=\"w-num\"></td>"
                       . '<td class="num qcell">' . money($qs[$q] * $cost) . '</td>';
            }
            $html .= '<td><button type="submit" name="delete_id" value="' . $id . '" class="btn small danger" formnovalidate '
                   . 'onclick="return confirm(\'Remove this row?\')">Remove</button></td>';
        } else {
            $html .= '<td>' . h($it["product_category"]) . '</td>'
                   . '<td>' . h($it["description"]) . '</td>'
                   . '<td class="num">' . $qty . '</td>'
                   . '<td>' . h($it["unit"]) . '</td>'
                   . '<td class="num">' . money($cost) . '</td>'
                   . '<td class="num">' . money($est) . '</td>'
                   . '<td>' . h($it["acct_code"]) . '</td>'
                   . '<td>' . h($it["acct_category"]) . '</td>';
            for ($q = 1; $q <= 4; $q++) {
                $qtotals[$q] += $qs[$q] * $cost;
                $html .= '<td class="num qcell">' . $qs[$q] . '</td><td class="num qcell">' . money($qs[$q] * $cost) . '</td>';
            }
        }
        $html .= '</tr>';
    }

    $cols = $editable ? 18 : 17;
    if (!$items) {
        $html .= '<tr><td colspan="' . $cols . '">No PPMP items yet. Use "Generate from inventory" or "Add blank row".</td></tr>';
    }

    $html .= '</tbody><tfoot><tr><th colspan="6">Total Estimated Budget</th><th class="num">' . money($total) . '</th><th colspan="2"></th>';
    for ($q = 1; $q <= 4; $q++) {
        $html .= '<th></th><th class="num">' . money($qtotals[$q]) . '</th>';
    }
    $html .= $editable ? '<th></th>' : '';
    $html .= '</tr></tfoot></table></div>';
    return $html;
}

function ppmp_items($fy) {
    global $pdo;
    $st = $pdo->prepare("SELECT * FROM ppmp_items WHERE fiscal_year = ? ORDER BY product_category, description, id");
    $st->execute([$fy]);
    return $st->fetchAll();
}

$user_name = $_SESSION["user"]["full_name"];

/* ---------- Print view ---------- */
if (isset($_GET["print"])) {
    $table = $type === "PPMP" ? ppmp_table(ppmp_items($fy), false, $total) : report_table($type, $doc, $rows, $total);
    ?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title><?= h($doc["title"]) ?></title>
<style>
    <?php if ($type === "PPMP"): ?>@page { size: A4 landscape; margin: 10mm; }<?php endif; ?>
    body { font-family: Arial, sans-serif; margin: 30px; color: #0a2352; background: #fff; }
    h1 { margin: 0 0 4px; font-size: 20px; color: #0a2352; }
    h2 { margin: 0 0 16px; font-size: 16px; font-weight: normal; color: #15408f; }
    .meta { margin-bottom: 14px; font-size: 13px; line-height: 1.6; }
    table { border-collapse: collapse; width: 100%; font-size: 13px; }
    th, td { border: 1px solid #d8e2f2; padding: 7px; text-align: left; }
    th { background: #eaf0fb; border-bottom: 2px solid #f2b705; color: #0f3170; }
    .num { text-align: right; }
    .ppmp-table { font-size: 9px; }
    .ppmp-table th, .ppmp-table td { padding: 3px 4px; }
    thead { display: table-header-group; }
    tr { page-break-inside: avoid; }
    .signatures { display: flex; gap: 30px; margin-top: 48px; page-break-inside: avoid; }
    .sig { flex: 1; font-size: 13px; }
    .sig .line { border-bottom: 1px solid #15408f; height: 34px; margin-bottom: 4px; }
    .sig .name { font-weight: bold; min-height: 16px; }
    .generated { margin-top: 28px; font-size: 11px; color: #526582; }
</style>
</head>
<body>
<h1>Iloilo Science and Technology University &mdash; Campus Clinic</h1>
<h2><?= h($doc["title"]) ?></h2>
<div class="meta">
    <div><strong>Date:</strong> <?= date("F d, Y") ?></div>
    <div><strong>Purpose:</strong> <?= h($doc["purpose"]) ?></div>
</div>
<?= $table ?>
<div class="signatures">
<?php foreach ($doc["signatories"] as $i => $label): ?>
    <div class="sig">
        <div class="line"></div>
        <div class="name"><?= $i === 0 ? h($user_name) : "&nbsp;" ?></div>
        <div><?= h($label) ?> (signature over printed name)</div>
    </div>
<?php endforeach; ?>
</div>
<div class="generated">Generated <?= date("F d, Y g:i A") ?> by <?= h($user_name) ?> from the Campus Clinic Medicine Inventory system.</div>
<script>window.print()</script>
</body>
</html>
<?php
    exit;
}

include __DIR__ . "/includes/header.php";
render_flash();
$preview = isset($_GET["preview"]);
$pv = $preview ? "&preview=1" : "";
?>
<style>
.ppmp-scroll { overflow-x: auto; }
.ppmp-table { min-width: 1500px; }
.ppmp-table th, .ppmp-table td { padding: 6px 6px; font-size: 0.8rem; }
.ppmp-table input { margin: 0; padding: 6px 8px; min-width: 150px; font-size: 0.8rem; }
.ppmp-table input.w-unit { min-width: 70px; }
.ppmp-table input.w-code { min-width: 95px; }
.ppmp-table input.w-num { min-width: 70px; }
.ppmp-table .num { text-align: right; white-space: nowrap; }
.ppmp-table .qcol, .ppmp-table .qcell { background: #fffbe8; }
.ppmp-toolbar { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; margin: 14px 0; }
.ppmp-toolbar form { margin: 0; }
.ppmp-toolbar input[type=number] { width: 100px; margin: 0; }
.report-table td.num, .report-table tfoot th.num { text-align: right; }
</style>

<section class="panel">
<h2>Generate Procurement Document</h2>
<p class="muted">Purchase Request and APP list medicines that need reordering. The PPMP is a yearly plan scheduled per quarter.</p>
<div class="tabs">
    <a class="btn <?= $type === "PR" ? "primary" : "" ?>" href="?type=PR<?= $pv ?>">Purchase Request</a>
    <a class="btn <?= $type === "APP" ? "primary" : "" ?>" href="?type=APP<?= $pv ?>">APP</a>
    <a class="btn <?= $type === "PPMP" ? "primary" : "" ?>" href="?type=PPMP&fy=<?= $fy ?><?= $pv ?>">PPMP</a>
    <?php if ($type !== "PPMP"): ?><a class="btn secondary" href="?type=<?= h($type) ?>&fy=<?= $fy ?><?= $preview ? '' : '&preview=1' ?>"><?= $preview ? 'Back to report' : 'Preview document' ?></a><?php endif; ?>
    <a class="btn primary" href="?type=<?= h($type) ?>&fy=<?= $fy ?>&print=1" target="_blank" rel="noopener">Print / Save PDF</a>
</div>

<?php if ($type === "PPMP"): ?>
    <div class="ppmp-toolbar">
        <form method="get">
            <input type="hidden" name="type" value="PPMP">
            <label style="display:inline-flex;align-items:center;gap:8px">Fiscal Year
                <input type="number" name="fy" value="<?= $fy ?>" min="2000" max="2100">
            </label>
            <button class="btn small secondary" type="submit">Go</button>
        </form>
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="fy" value="<?= $fy ?>">
            <input type="hidden" name="action" value="ppmp_generate">
            <button class="btn small secondary" type="submit">Generate from inventory</button>
        </form>
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="fy" value="<?= $fy ?>">
            <input type="hidden" name="action" value="ppmp_add">
            <button class="btn small secondary" type="submit">Add blank row</button>
        </form>
    </div>
    <p class="muted">"Generate from inventory" adds medicines flagged for reorder, with the suggested quantity spread evenly over Q1 to Q4. Edit anything, then press Save PPMP.</p>

    <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="fy" value="<?= $fy ?>">
        <input type="hidden" name="action" value="ppmp_save">
        <?= ppmp_table(ppmp_items($fy), true, $total) ?>
        <div class="form-actions" style="margin-top:14px">
            <button class="btn primary" type="submit">Save PPMP</button>
        </div>
    </form>

<?php else:
    $table = report_table($type, $doc, $rows, $total);
    if ($preview): ?>
<div class="report-preview-document">
    <div class="report-header"><div><span class="eyebrow">Document preview</span><h3><?= h($doc["title"]) ?></h3><p class="muted"><?= h($doc["purpose"]) ?></p></div><div class="report-meta"><span>Date: <?= date("F d, Y") ?></span><span>Campus Clinic</span></div></div>
    <?= $table ?>
</div>
    <?php else: ?>
    <?= $table ?>
    <?php endif;
endif; ?>
</section>
<?php include __DIR__ . "/includes/footer.php"; ?>