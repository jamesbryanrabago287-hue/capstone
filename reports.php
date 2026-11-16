<?php
require_once __DIR__ . "/includes/auth.php";
require_login();
require_once __DIR__.'/includes/purchase_request.php';
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
require_once __DIR__.'/includes/ppmp_procurement.php';ensure_ppmp_procurement_schema($pdo);

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

require_once __DIR__.'/includes/ppmp_sort.php';
$ppmpSort=ppmp_sort_key($_POST['sort'] ?? $_GET['sort'] ?? 'category');

$appError='';
require_once __DIR__.'/includes/app_report.php';ensure_app_details($pdo);
$ppmpError='';
require_once __DIR__.'/includes/ppmp_save.php';
require_once __DIR__.'/includes/ppmp_plan.php';
if($_SERVER['REQUEST_METHOD']==='POST'&&($_POST['action'] ?? '')==='app_save'){
 verify_csrf();
 try{$intent=$_POST['app_intent'] ?? 'save';$format=$_POST['export_format'] ?? 'xlsx';if(!is_string($intent)||!in_array($intent,['save','preview','download'],true))throw new InvalidArgumentException('Choose Save, Preview or Download.');if($intent==='download'&&(!is_string($format)||!in_array($format,['xlsx','csv','pdf'],true)))throw new InvalidArgumentException('Choose Excel, CSV or PDF.');save_app_details($pdo,$fy,$_POST['app_rows'] ?? []);record_activity($_SESSION['user']['id'],$_SESSION['user']['username'],'app_save','Saved APP details for FY '.$fy);$back='reports.php?type=APP&fy='.$fy;if($intent==='preview')$back.='&preview=1';elseif($intent==='download')$back.='&format='.$format;else flash('success','APP details saved.');redirect($back);}
 catch(InvalidArgumentException $e){$appError=$e->getMessage();}catch(Throwable $e){error_log('APP save failed: '.$e->getMessage());$appError='Could not save APP details. Please try again.';}
 $_GET['type']='APP';unset($_GET['format'],$_GET['print'],$_GET['preview']);
}
/* ---------- PPMP actions ---------- */
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["action"] ?? "")!=="app_save") {
    verify_csrf();
    $me = $_SESSION["user"];
    $action = $_POST["action"] ?? "";
    $back = "reports.php?type=PPMP&fy=" . $fy . "&sort=" . rawurlencode($ppmpSort);

    if ($action === "ppmp_add") {
        try{
            $itemId=add_ppmp_medicine($pdo,$fy,$_POST['medicine_id'] ?? '');
            record_activity($me['id'],$me['username'],'ppmp_add','Added PPMP item #'.$itemId.' for FY '.$fy);
            flash('success','Medicine added. Enter the planned quantities for each quarter.');
        }catch(InvalidArgumentException $e){flash('error',$e->getMessage());}
        catch(Throwable $e){error_log('PPMP add failed: '.$e->getMessage());flash('error','Could not add the medicine. Please try again.');}
    } elseif ($action === "ppmp_save") {
        if (isset($_POST["delete_id"])) {
            $pdo->prepare("DELETE FROM ppmp_items WHERE id = ? AND fiscal_year = ?")
                ->execute([(int)$_POST["delete_id"], $fy]);
            flash("success", "Row removed.");
        } else {
            try {
                $intent=$_POST['ppmp_intent'] ?? 'save';
                if(!is_string($intent)||!in_array($intent,['save','preview','download','add'],true))throw new InvalidArgumentException('Choose Save changes, Preview or Download.');
                $format=$_POST['export_format'] ?? 'xlsx';
                if($intent==='download'&&(!is_string($format)||!in_array($format,['xlsx','csv','pdf'],true)))throw new InvalidArgumentException('Choose Excel, CSV or PDF.');
                save_ppmp_rows($pdo,$fy,$_POST['rows'] ?? [],$_POST['ppmp_document'] ?? null);
                record_activity($me['id'],$me['username'],'ppmp_save','Saved PPMP FY '.$fy);
                if($intent==='add'){
                    $itemId=add_ppmp_medicine($pdo,$fy,$_POST['medicine_id'] ?? '');
                    record_activity($me['id'],$me['username'],'ppmp_add','Added PPMP item #'.$itemId.' for FY '.$fy);
                    flash('success','Medicine added. Enter the planned quantities for each quarter.');
                }
                elseif($intent==='preview')$back.='&preview=1';
                elseif($intent==='download')$back.='&format='.rawurlencode($format);
                else flash('success','PPMP changes saved.');
            }catch(InvalidArgumentException $e){$ppmpError=$e->getMessage();}
            catch(Throwable $e){error_log('PPMP save failed: '.$e->getMessage());$ppmpError='Could not save the PPMP. No download was started. Please try again.';}

        }
    }
    if($ppmpError==='')redirect($back);
    $_GET['type']='PPMP';unset($_GET['preview'],$_GET['format'],$_GET['print']);
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
        "heads"      => ["Item No.", "Product Category", "Description/Specifications", "Quantity", "Unit", "Unit Cost", "Total Cost"],
        "signatories" => ["Requested by", "Recommended by", "Approved by"],
    ],
    "APP" => [
        "title"      => "Annual Procurement Plan (APP)",
        "purpose"    => "Annual budget summary from the saved PPMP for the selected fiscal year.",
        "heads"      => [], // Grouped APP headers are rendered by app_report_table.
        "signatories" => ["Prepared by", "Reviewed by", "Approved by"],
    ],
    "PPMP" => [
        "title"      => "Project Procurement Management Plan (PPMP) FY " . $fy,
        "purpose"    => "Medicines and estimated budget for the clinic's medicine procurement, scheduled per quarter.",
        "heads"      => [],
        "signatories" => ["Prepared by", "Reviewed by", "Approved by"],
    ],
];

$type = is_string($_GET["type"] ?? null) ? $_GET["type"] : "PR";
if (!isset($docs[$type])) {
    $type = "PR";
}
$doc = $docs[$type];
$ppmpDoc=$type==='PPMP'?ppmp_document($pdo,$fy):['document_no'=>'','stage'=>'','implementing_unit'=>''];
if($ppmpError!==''&&is_array($_POST['ppmp_document'] ?? null))foreach(['document_no','stage','implementing_unit'] as $field)if(is_string($_POST['ppmp_document'][$field] ?? null))$ppmpDoc[$field]=$_POST['ppmp_document'][$field];
$appData=$type==='APP'?app_report_data($pdo,$fy):[];
if($appError!=='')foreach($appData as &$group){$posted=$_POST['app_rows'][$group['key']] ?? [];if(is_array($posted))foreach(['program','description','requesting_office','source_fund'] as $field)if(is_string($posted[$field] ?? null))$group[$field]=$posted[$field];}unset($group);

function money($n) {
    return number_format((float)$n, 2);
}

/**
 * Builds the PR / APP table.
 * Columns: Generic Name, Description, Brand, Quantity, Unit, Unit Price.
 * Sets $total (Quantity x Unit Price) by reference.
 */
function report_table($type, array $doc, array $rows, &$total) {
    if($type==='PR'){global $med_extra;return purchase_request_table($rows,$med_extra,$total);}
    if($type==='APP'){global $appData;return app_report_table($appData,false,$total);}
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
function ppmp_table(array $items,$editable,&$total) {return ppmp_procurement_table($items,(bool)$editable,$total);}

$user_name = $_SESSION["user"]["full_name"];

/* ---------- Spreadsheet downloads ---------- */
if(isset($_GET['format'])){
 $format=$_GET['format'];
 if(!is_string($format)||!in_array($format,['xlsx','csv','pdf'],true)){http_response_code(400);exit('Choose Excel, CSV or PDF.');}
 if($format==='pdf')$_GET['print']=1;
 else{
  require_once __DIR__.'/includes/report_export.php';
  $values=report_export_rows($type,$rows,$med_extra,$type==='PPMP'?ppmp_items($fy,$ppmpSort):[],$appData);
  $merges=$type==='PR'?['A1:A2','B1:C1','D1:D2','E1:E2','F1:F2','G1:G2']:($type==='APP'?['A1:A2','B1:B2','C1:C2','D1:E1','F1:G1','H1:I1','J1:K1','L1:L2','M1:M2']:[]);
  $spreadsheetValues=$type==='PR'?purchase_request_spreadsheet_rows($values):($type==='APP'?app_spreadsheet_rows($values):ppmp_procurement_spreadsheet($values,$ppmpDoc,$fy));
  if($type==='PPMP')$merges=['A4:A5','B4:B5','C4:C5','D4:D5','E4:E5','F4:H4','I4:J4','K4:K5','L4:L5'];
  $payload=$format==='xlsx'?report_xlsx($spreadsheetValues,$merges):report_csv($values);
  header('Content-Type: '.($format==='xlsx'?'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet':'text/csv; charset=UTF-8'));
  header('Content-Disposition: attachment; filename="clinic-'.$type.'-'.$fy.'-'.date('Y-m-d').'.'.$format.'"');
  header('Cache-Control: private, no-store');header('X-Content-Type-Options: nosniff');
  echo $payload;exit;
 }
}
/* ---------- Print view ---------- */
if (isset($_GET["print"])) {
    $table = $type === 'PPMP' ? ppmp_document_header($ppmpDoc,$fy).ppmp_table(ppmp_items($fy,$ppmpSort),false,$total) : report_table($type,$doc,$rows,$total);
    ?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title><?= h($doc["title"]) ?></title>
<style>
    <?php if (in_array($type,["PPMP","APP"],true)): ?>@page { size: A4 landscape; margin: 10mm; }<?php endif; ?>
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
.staff-app .report-actions{display:flex;flex-direction:row;align-items:center;flex-wrap:nowrap;gap:12px;margin-top:20px;overflow-x:auto;padding-bottom:4px}
.staff-app .report-actions>.btn,.staff-app .report-actions select{flex:0 0 auto;width:auto;margin:0;min-height:43px;white-space:nowrap}
.staff-app .report-actions .report-download-form{display:flex;flex-direction:row;align-items:center;flex-wrap:nowrap!important;gap:12px;width:auto;margin:0}
.staff-app .report-actions label{flex:0 0 auto;width:auto;margin:0}

.staff-app .report-actions select{width:125px;min-width:125px;max-width:125px;flex:0 0 125px}

.staff-app .ppmp-toolbar{display:flex;justify-content:space-between;align-items:flex-end;gap:20px;flex-wrap:wrap;padding:16px;margin:18px 0 10px;background:#f7f9fc;border:1px solid #e1e7ef;border-radius:10px}
.staff-app .ppmp-toolbar .ppmp-filters{display:grid;grid-template-columns:110px minmax(220px,280px) auto;align-items:end;gap:12px;margin:0}
.staff-app .ppmp-toolbar label{display:flex;flex-direction:column;align-items:stretch;gap:7px;margin:0;min-width:0;font-size:13px;white-space:nowrap}
.staff-app .ppmp-toolbar input[type=number],.staff-app .ppmp-toolbar select{display:block;width:100%;min-width:0;margin:0;height:44px}
.staff-app .ppmp-toolbar button{height:44px;margin:0;white-space:nowrap}
.staff-app .ppmp-create-actions{display:flex;gap:10px;align-items:end;flex-wrap:wrap}
.staff-app .ppmp-create-actions form{display:block;margin:0}
.staff-app .ppmp-toolbar-note{font-size:13px;margin:0 0 22px;line-height:1.5}
@media(max-width:650px){.staff-app .ppmp-toolbar{padding:12px;gap:14px}.staff-app .ppmp-toolbar .ppmp-filters{grid-template-columns:90px minmax(0,1fr);width:100%}.staff-app .ppmp-filters>button{grid-column:1 / -1}.staff-app .ppmp-create-actions{width:100%}.staff-app .ppmp-create-actions form{flex:1;min-width:150px}.staff-app .ppmp-create-actions button{width:100%}}

.staff-app .ppmp-editor-table{min-width:1900px}.staff-app .ppmp-editor-table input:not([type=hidden]){min-width:70px;width:100%}.staff-app .ppmp-editor-table th:nth-child(2){min-width:145px}.staff-app .ppmp-editor-table th:nth-child(3){min-width:240px}.staff-app .ppmp-editor-table th:nth-child(9){min-width:230px}.staff-app .ppmp-editor-table input[data-plan-cost]{min-width:95px}.staff-app .ppmp-editor-table th{white-space:normal;vertical-align:middle}.staff-app .ppmp-editor-table input.w-code{min-width:100px}
.staff-app .ppmp-stock-reference{padding:16px;margin:0 0 24px;background:#f7f9fc;border:1px solid #e1e7ef;border-radius:10px}
.staff-app .ppmp-stock-reference h3{margin:0 0 8px}.staff-app .ppmp-reference-scroll{max-height:260px;overflow:auto}.staff-app .ppmp-stock-table{min-width:450px;width:100%}.staff-app .ppmp-stock-table thead th{position:sticky;top:0;background:#edf2f8;z-index:1}
.staff-app .app-summary-table{min-width:1650px}.staff-app .app-summary-table input{min-width:150px;width:100%;margin:0}.staff-app .app-summary-table td:nth-child(2){min-width:230px}.staff-app .app-year-filter{display:flex;gap:12px;align-items:end}.staff-app .app-year-filter label{display:flex;flex-direction:column;gap:7px}
.staff-app .ppmp-planning-reference{padding:16px;margin:0 0 24px;border:1px solid #e1e7ef;border-radius:10px;background:#f7f9fc}.staff-app .ppmp-planning-table{min-width:1450px}.staff-app .ppmp-document-fields{margin:12px 0 20px}.staff-app .ppmp-document-fields label{display:flex;flex-direction:column;gap:7px}.staff-app .ppmp-procurement-table{min-width:2200px}.staff-app .ppmp-procurement-table textarea{min-width:180px;width:100%;margin:0;font-size:13px}.staff-app .ppmp-procurement-table td:first-child{min-width:270px}.staff-app .ppmp-procurement-table input[type=month]{min-width:155px;width:100%;margin:0}.staff-app .ppmp-procurement-table select{min-width:130px;width:100%;margin:0}.staff-app .ppmp-procurement-table th{vertical-align:middle;white-space:normal}
</style>

<section class="panel">
<h2>Generate Procurement Document</h2>
<p class="muted">Purchase Request lists restocking needs. PPMP plans yearly items; APP summarizes their quarterly budgets.</p>
<div class="tabs">
    <a class="btn <?= $type === "PR" ? "primary" : "" ?>" href="?type=PR<?= $pv ?>">Purchase Request</a>
    <a class="btn <?= $type === "APP" ? "primary" : "" ?>" href="?type=APP&fy=<?= $fy ?><?= $pv ?>">APP</a>
    <a class="btn <?= $type === "PPMP" ? "primary" : "" ?>" href="?type=PPMP&fy=<?= $fy ?>&sort=<?= h($ppmpSort) ?><?= $pv ?>">PPMP</a>
    <?php if($type==='PPMP' && !$preview): ?><button class="btn secondary" type="submit" form="ppmp-edit-form" name="ppmp_intent" value="preview">Preview document</button><?php elseif($type==='APP' && !$preview): ?><button class="btn secondary" type="submit" form="app-edit-form" name="app_intent" value="preview">Preview document</button><?php else: ?>
    <a class="btn secondary" href="?type=<?= h($type) ?>&fy=<?= $fy ?>&sort=<?= h($ppmpSort) ?><?= $preview ? '' : '&preview=1' ?>"><?= $preview ? 'Back to report' : 'Preview document' ?></a>
    <?php endif; ?>

</div>

<?php if($type==='APP' && !$preview): ?>
<div class="ppmp-toolbar"><form method="get" class="app-year-filter"><input type="hidden" name="type" value="APP"><label>Fiscal year<input type="number" name="fy" value="<?= $fy ?>" min="2000" max="2100"></label><button type="submit" class="btn secondary">Apply</button></form><a class="btn secondary" href="?type=PPMP&fy=<?= $fy ?>">Review PPMP</a></div>
<p class="muted">Budget amounts come from saved PPMP items, grouped by account code and account category. Enter the program, requesting office and fund details; these are not guessed. Amount and Total show the same quarterly subtotal for each group.</p>
<form method="post" id="app-edit-form"><?= csrf_field() ?><input type="hidden" name="action" value="app_save"><input type="hidden" name="fy" value="<?= $fy ?>">
<?php if($appError!==''): ?><p class="alert danger" role="alert"><?= h($appError) ?></p><?php endif; ?>
<?= app_report_table($appData,true,$total) ?></form>
<p class="muted">Preview and Download save these details automatically. To change budget amounts, edit and save the PPMP.</p>
<?php elseif ($type === "PPMP" && !$preview): ?>
    <p class="muted">Preview and Download save your changes automatically.</p>
    <div class="ppmp-toolbar">
        <form method="get" class="ppmp-filters">
            <input type="hidden" name="type" value="PPMP">
            <label>Fiscal year<input type="number" name="fy" value="<?= $fy ?>" min="2000" max="2100"></label>
            <label>Sort by<select name="sort"><?php foreach(ppmp_sort_options() as $key=>$option): ?><option value="<?= h($key) ?>" <?= $ppmpSort===$key?'selected':'' ?>><?= h($option[0]) ?></option><?php endforeach; ?></select></label>
            <button class="btn secondary" type="submit">Apply</button>
        </form>
        <div class="ppmp-create-actions"><button type="button" class="btn primary" data-open-dialog="ppmp-add-item">Add item</button></div>
    </div>
    <p class="muted ppmp-toolbar-note">Set quantities and unit cost in the planning section, then complete the procurement details below.</p>
    <?php $planMedicines=medicine_listing_groups(clinic_stock_rows($pdo));$pickerOptions=[];foreach($planMedicines as $medicine)$pickerOptions[]=['id'=>(int)$medicine['id'],'name'=>$medicine['name'].' ('.$medicine['unit'].')','search'=>$medicine['name'].' '.($medicine['generic_name'] ?? '').' '.$medicine['category']]; ?>
    <dialog id="ppmp-add-item" class="record-dialog" aria-labelledby="ppmp-add-title">
    <div class="record-dialog-heading"><h3 id="ppmp-add-title">Add item</h3><button type="button" class="btn secondary" onclick="this.closest('dialog').close()">Close</button></div>
    <div class="ppmp-add-fields">
        <div class="medicine-picker"><label>Medicine<input id="ppmp-medicine-search" type="search" autocomplete="off" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="ppmp-medicine-matches" placeholder="Search for a medicine" required></label>
        <input id="ppmp-medicine-id" name="medicine_id" type="hidden" form="ppmp-edit-form"><div id="ppmp-medicine-matches" class="medicine-matches" role="listbox" aria-label="Matching medicines" hidden></div></div>
        <p class="muted">The medicine details and unit cost are filled in. You choose the quarterly quantities.</p>
        <div class="form-actions"><button type="button" class="btn secondary" onclick="this.closest('dialog').close()">Cancel</button><button type="submit" class="btn primary" form="ppmp-edit-form" name="ppmp_intent" value="add">Add item</button></div>
    </div></dialog>
    <script type="application/json" id="ppmp-medicine-options"><?= json_encode($pickerOptions,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_INVALID_UTF8_SUBSTITUTE) ?></script>
    <script src="assets/ppmp-plan.js?v=<?= filemtime(__DIR__.'/assets/ppmp-plan.js') ?>" defer></script>

    <form method="post" id="ppmp-edit-form">
        <?= csrf_field() ?>
        <input type="hidden" name="fy" value="<?= $fy ?>"><input type="hidden" name="sort" value="<?= h($ppmpSort) ?>">
        <input type="hidden" name="action" value="ppmp_save">
        <?php
        $editItems=ppmp_items($fy,$ppmpSort);
        if($ppmpError!=='')foreach($editItems as &$editItem){$posted=$_POST['rows'][$editItem['id']] ?? [];if(is_array($posted))foreach(['product_category','description','unit','unit_cost','acct_code','acct_category','q1_qty','q2_qty','q3_qty','q4_qty','project_type','procurement_mode','preconference','start_month','end_month','delivery_month','source_fund','supporting_documents','remarks'] as $field)if(isset($posted[$field])&&is_scalar($posted[$field]))$editItem[$field]=$posted[$field];}unset($editItem);
        ?>
        <?php if($ppmpError!==''): ?><p class="alert danger" role="alert"><?= h($ppmpError) ?></p><?php endif; ?>
        <?php $stockItems=ppmp_with_available_stock($editItems,clinic_stock_rows($pdo)); ?>
        <?= ppmp_stock_reference($stockItems) ?>
        <?= ppmp_planning_table($editItems) ?>
        <h3>Project Procurement Management Plan</h3>
        <?= ppmp_document_header($ppmpDoc,$fy,true) ?>
        <?= ppmp_table($editItems, true, $total) ?>

    </form>

<?php else:
    $table = $type==='PPMP' ? ppmp_document_header($ppmpDoc,$fy).ppmp_table(ppmp_items($fy,$ppmpSort),false,$total) : report_table($type,$doc,$rows,$total);
    if ($preview): ?>
<div class="report-preview-document">
    <div class="report-header"><div><span class="eyebrow">Document preview</span><h3><?= h($doc["title"]) ?></h3><p class="muted"><?= h($doc["purpose"]) ?></p></div><div class="report-meta"><span>Date: <?= date("F d, Y") ?></span><span>Campus Clinic</span></div></div>
    <?= $table ?>
</div>
    <?php else: ?>
    <?= $table ?>
    <?php endif;
endif; ?>
<div class="form-actions report-actions">
<?php if($type==='PPMP' && !$preview): ?><button class="btn primary" type="submit" form="ppmp-edit-form" name="ppmp_intent" value="save">Save</button><?php elseif($type==='APP' && !$preview): ?><button class="btn primary" type="submit" form="app-edit-form" name="app_intent" value="save">Save</button><?php endif; ?>
    <?php if($type==='PPMP' && !$preview): ?>
      <button class="btn primary" type="submit" form="ppmp-edit-form" name="ppmp_intent" value="download">Download</button>
      <select name="export_format" aria-label="Download format" form="ppmp-edit-form"><option value="xlsx">Excel (.xlsx)</option><option value="csv">CSV (.csv)</option><option value="pdf">PDF / Print</option></select>
    <?php elseif($type==='APP' && !$preview): ?>
      <button class="btn primary" type="submit" form="app-edit-form" name="app_intent" value="download">Download</button><select name="export_format" aria-label="Download format" form="app-edit-form"><option value="xlsx">Excel (.xlsx)</option><option value="csv">CSV (.csv)</option><option value="pdf">PDF / Print</option></select>
    <?php else: ?>
    <form method="get" class="report-download-form" style="display:flex;align-items:end;gap:10px;flex-wrap:wrap">
      <input type="hidden" name="type" value="<?= h($type) ?>"><input type="hidden" name="fy" value="<?= $fy ?>"><input type="hidden" name="sort" value="<?= h($ppmpSort) ?>">
      <button class="btn primary" type="submit">Download / Open</button>
      <label style="margin:0"><select name="format" aria-label="Download format"><option value="xlsx">Excel (.xlsx)</option><option value="csv">CSV (.csv)</option><option value="pdf">PDF / Print</option></select></label>
    </form>
    <?php endif; ?>
</div>
</section>
<?php include __DIR__ . "/includes/footer.php"; ?>