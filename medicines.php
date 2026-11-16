<?php
require_once __DIR__ . "/includes/auth.php";
require_login();
$page_title = "Medicine Records";

/* Adds generic_name / brand / description to medicines the first time this page loads (safe to run repeatedly). */
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
ensure_medicine_groups($pdo);
require_once __DIR__.'/includes/inventory.php';ensure_inventory_schema($pdo);

/** Read and validate the medicine form. Returns [data, errors]. */
function medicine_input() {
    $d = [
        "name"            => trim($_POST["name"] ?? ""),
        "generic_name"    => trim($_POST["generic_name"] ?? ""),
        "brand"           => trim($_POST["brand"] ?? ""),
        "description"     => trim($_POST["description"] ?? ""),
        "category"        => trim($_POST["category"] ?? ""),
        "unit"            => trim($_POST["unit"] ?? ""),
        "supplier"        => trim($_POST["supplier"] ?? ""),
        "batch_number"    => trim($_POST["batch_number"] ?? ""),
        "quantity"        => (int)($_POST["quantity"] ?? 0),
        "reorder_point"   => (int)($_POST["reorder_point"] ?? 0),
        "expiration_date" => trim($_POST["expiration_date"] ?? ""),
        "unit_cost"       => (float)($_POST["unit_cost"] ?? 0),
    ];
    if ($d["unit"] === "") {
        $d["unit"] = "tablet";
    }

    $errors = [];

    if ($d["name"] === "" || $d["category"] === "") {
        $errors[] = "Name and category are required.";
    }

    $limits = ["name" => 150, "generic_name" => 150, "brand" => 150, "description" => 255,
               "category" => 100, "unit" => 50, "supplier" => 150, "batch_number" => 100];
    foreach ($limits as $field => $max) {
        if (mb_strlen($d[$field]) > $max) {
            $errors[] = ucfirst(str_replace("_", " ", $field)) . " is too long (max {$max} characters).";
        }
    }

    if ($d["quantity"] < 0 || $d["reorder_point"] < 0 || $d["unit_cost"] < 0) {
        $errors[] = "Quantity, reorder point, and unit cost cannot be negative.";
    }
    if ($d["quantity"] > 1000000 || $d["reorder_point"] > 1000000 || $d["unit_cost"] > 1000000) {
        $errors[] = "One of the numbers is too large.";
    }

    $date = DateTime::createFromFormat("Y-m-d", $d["expiration_date"]);
    if (!$date || $date->format("Y-m-d") !== $d["expiration_date"]) {
        $errors[] = "Enter a valid expiration date.";
    }

    return [$d, $errors];
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    verify_csrf();
    $action = $_POST["action"] ?? "";
    $user = $_SESSION["user"];

    if ($action === "add") {
        [$d, $errors] = medicine_input();

        if ($errors) {
            flash("error", implode(" ", $errors));
        } else {
            try {
                $pdo->beginTransaction();

                $pdo->prepare("INSERT INTO medicines(name,generic_name,brand,description,category,unit,supplier,batch_number,quantity,reorder_point,expiration_date,unit_cost) VALUES(?,?,?,?,?,?,?,?,?,?,?,?)")
                    ->execute([$d["name"], $d["generic_name"], $d["brand"], $d["description"], $d["category"], $d["unit"], $d["supplier"], $d["batch_number"],
                               $d["quantity"], $d["reorder_point"], $d["expiration_date"], $d["unit_cost"]]);
                $new_id = (int)$pdo->lastInsertId();

                // Log the starting stock so the transaction history always matches the stock count
                if ($d["quantity"] > 0) {
                    $pdo->prepare("INSERT INTO transactions(medicine_id,type,quantity,reference_no,notes,user_id,balance_before,balance_after,transaction_date) VALUES(?,?,?,?,?,?,0,?,NOW())")
                        ->execute([$new_id, "Stock In", $d["quantity"], "", "Initial stock", $user["id"],$d["quantity"]]);
                }

                $pdo->commit();
                record_activity($user["id"], $user["username"], "add_medicine", "Added medicine: " . $d["name"]);
                flash("success", "Medicine added.");
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log("Add medicine failed: " . $e->getMessage());
                flash("error", "Could not add the medicine. Please check the details and try again.");
            }
        }
    } elseif ($action === "edit") {
        $id = (int)($_POST["id"] ?? 0);
        [$d, $errors] = medicine_input();
        if ($id < 1) {
            $errors[] = "Medicine not found.";
        }

        if ($errors) {
            flash("error", implode(" ", $errors));
        } else {
            try {
                update_medicine_details($pdo,$id,$d);
                record_activity($user["id"], $user["username"], "edit_medicine", "Updated medicine ID {$id}");
                flash("success", "Medicine updated.");
            } catch (Throwable $e) {
                error_log("Edit medicine failed: " . $e->getMessage());
                flash("error", "Could not update the medicine.");
            }
        }
    } elseif ($action === "delete") {
        $id = (int)($_POST["id"] ?? 0);

        try {
            $message=delete_empty_medicine($pdo,$id,is_admin());
            record_activity($user['id'],$user['username'],'delete_medicine','Removed medicine ID '.$id.'; history retained where applicable');
            flash('success',$message);
        } catch (InvalidArgumentException $e) { flash('error',$e->getMessage()); }
        catch (Throwable $e) { error_log('Delete medicine failed: '.$e->getMessage()); flash('error','Could not remove the medicine. Please try again.'); }

    }

    redirect("medicines.php"); // refreshing the page no longer repeats the action
}

require_once __DIR__.'/includes/list_filters.php';
$search=is_string($_GET['q'] ?? null)?mb_substr(trim($_GET['q']),0,80):'';$where='';$params=[];
if($search!==''){$like='%'.str_replace(['!','%','_'],['!!','!%','!_'],$search).'%';$where=" WHERE name LIKE ? ESCAPE '!' OR generic_name LIKE ? ESCAPE '!' OR brand LIKE ? ESCAPE '!' OR batch_number LIKE ? ESCAPE '!' OR category LIKE ? ESCAPE '!' OR supplier LIKE ? ESCAPE '!'";$params=array_fill(0,6,$like);}
$show=($_GET['show'] ?? '')==='20'?'20':'10';
$sort=is_string($_GET['sort'] ?? null)?$_GET['sort']:'az';
$sorts=['az'=>'Name: A to Z','za'=>'Name: Z to A','newest'=>'Newest added','oldest'=>'Oldest added','expiry'=>'Expiry: soonest first'];
$orders=['az'=>'name ASC,id ASC','za'=>'name DESC,id DESC','newest'=>'created_at DESC,id DESC','oldest'=>'created_at ASC,id ASC','expiry'=>'expiration_date ASC,name ASC,id ASC'];
if(!isset($orders[$sort]))$sort='az';
$allBatches=$pdo->query('SELECT m.*,COALESCE(g.reorder_point,m.reorder_point) AS reorder_point FROM medicines m LEFT JOIN medicine_reorder_groups g ON g.id=m.reorder_group_id WHERE m.archived_at IS NULL ORDER BY m.id')->fetchAll();
$listing=medicine_listing_groups($allBatches,$search,$sort);$total=count($listing);$pageSize=(int)$show;$pager=clinic_pager($total,$_GET['page'] ?? 1,$pageSize);$items=array_slice($listing,$pager['offset'],$pageSize);
include __DIR__ . "/includes/header.php";
render_flash();
?>


<section class="panel">
<div class="panel-head medicine-list-head">
    <div class="list-heading-actions"><h2>Medicine Lists</h2><button type="button" class="btn primary" data-open-dialog="add-medicine">Add Medicine</button></div>
    <form method="get" class="medicine-list-controls"><div class="medicine-picker" id="medicine-list-picker"><label>Find medicine<input type="search" name="q" id="medicine-list-search" class="search-input" autocomplete="off" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="medicine-list-matches" value="<?= h($search) ?>" placeholder="Name, batch or category"></label><div id="medicine-list-matches" class="medicine-matches" role="listbox" aria-label="Matching medicines" hidden></div></div><input type="hidden" name="show" value="<?= h($show) ?>"><label>Sort by<select name="sort"><?php foreach($sorts as $value=>$label): ?><option value="<?= h($value) ?>" <?= $sort===$value?'selected':'' ?>><?= h($label) ?></option><?php endforeach; ?></select></label><button type="submit">Apply</button> <a class="btn secondary" href="medicines.php">Reset</a></form>
</div>
<?php
$suggestions=[];foreach($allBatches as $suggestion){$name=trim((string)$suggestion['name']);if($name==='')continue;$key=mb_strtolower($name,'UTF-8');if(!isset($suggestions[$key]))$suggestions[$key]=['name'=>$name,'search'=>''];foreach(['name','generic_name','brand','batch_number','category','supplier'] as $field)$suggestions[$key]['search'].=' '.($suggestion[$field] ?? '');}ksort($suggestions,SORT_NATURAL|SORT_FLAG_CASE);
?>
<script id="medicine-list-options" type="application/json"><?= json_encode(array_values($suggestions),JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_INVALID_UTF8_SUBSTITUTE) ?></script>
<script src="assets/medicine-search.js?v=<?= filemtime(__DIR__.'/assets/medicine-search.js') ?>" defer></script>
<p><?= $total ?> matching medicine(s) &middot; <a href="transactions.php">Manage Inventory</a> &middot; <a href="analytics.php">Decision Support</a></p>
<div class="list-pagination"><span>Showing <?= count($items) ?> medicine(s) on this page; <?= $pageSize ?> per page.</span><?php if($show==='20' || $total>10): $toggleSize=$show==='20'?10:20;$togglePage=(int)floor($pager['offset']/$toggleSize)+1; ?><a class="btn secondary" href="?<?= h(http_build_query(['q'=>$search,'sort'=>$sort,'show'=>$toggleSize,'page'=>$togglePage])) ?>"><?= $show==='20'?'Show less':'Show more' ?></a><?php endif; ?></div>
<table id="med-table"><thead><tr><th>Name</th><th>Category</th><th>Unit</th><th>Total usable stock</th><th>Stock level</th><th>Stock by expiry</th><th>Supplier</th><th>Actions</th></tr></thead><tbody>
<?php foreach($items as $product):
 $status=stock_level_label($product['usable_total'],$product['reorder_point']);$dates=[];$suppliers=[];
 foreach($product['batches'] as $batch){$date=$batch['expiration_date'];$dates[$date]=($dates[$date] ?? 0)+(int)$batch['quantity'];if(trim((string)$batch['supplier'])!=='')$suppliers[trim($batch['supplier'])]=true;}
 ksort($dates);$positive=array_filter($dates,function($qty){return $qty>0;});if($positive)$dates=$positive;$first=array_slice($dates,0,2,true);$more=array_slice($dates,2,null,true);
?>
<tr><td><strong><?= h($product['name']) ?></strong><?php if(($product['generic_name'] ?? '')!==''): ?><br><small><?= h($product['generic_name']) ?></small><?php endif; ?><?php if(($product['description'] ?? '')!==''): ?><br><small><?= h($product['description']) ?></small><?php endif; ?></td>
<td><?= h($product['category']) ?></td><td><?= h($product['unit']) ?></td><td><?= (int)$product['usable_total'] ?><?php if($product['expired_total']>0): ?><br><small class="badge red"><?= (int)$product['expired_total'] ?> expired, excluded</small><?php endif; ?></td>
<td><span class="badge <?= h($status['class']) ?>"><?= h($status['label']) ?> stock</span></td>
<td class="expiry-breakdown"><?php foreach($first as $date=>$qty): ?><div><strong><?= h(date('M d, Y',strtotime($date))) ?></strong>: <?= (int)$qty ?> <?= h($product['unit']) ?><?php $exp=expiry_status($date);if($exp): ?> <span class="badge <?= h($exp['class']) ?>"><?= h($exp['label']) ?></span><?php endif; ?></div><?php endforeach; ?>
<?php if($more): ?><details><summary>View all expiry dates (<?= count($dates) ?>)</summary><?php foreach($more as $date=>$qty): ?><div><strong><?= h(date('M d, Y',strtotime($date))) ?></strong>: <?= (int)$qty ?> <?= h($product['unit']) ?><?php $exp=expiry_status($date);if($exp): ?> <span class="badge <?= h($exp['class']) ?>"><?= h($exp['label']) ?></span><?php endif; ?></div><?php endforeach; ?></details><?php endif; ?></td>
<td><?= h($suppliers?implode(', ',array_keys($suppliers)):'Not recorded') ?></td>
<td><div class="action-stack"><a class="btn small secondary" href="transactions.php?medicine_id=<?= (int)$product['id'] ?>#record-movement">Manage stock</a>
<button type="button" class="btn small secondary" onclick="document.getElementById('<?= count($product['batches'])===1?'edit-':'choose-edit-' ?><?= (int)$product['id'] ?>').showModal()">Edit</button>
<?php if(is_admin()): ?><form method="post" onsubmit="return confirm('Remove this medicine and its expiry records from active inventory? Stock must be zero. Past transactions will be kept.');"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$product['id'] ?>"><button class="btn small danger" type="submit">Delete</button></form><?php endif; ?></div>
<?php if(count($product['batches'])>1): ?>
<dialog id="choose-edit-<?= (int)$product['id'] ?>">
<h3>Edit <?= h($product['name']) ?></h3>
<p>Choose the expiry record you want to edit.</p>
<div class="form-actions">
<?php foreach($product['batches'] as $batch): ?>
<button type="button" class="btn secondary" onclick="this.closest('dialog').close();document.getElementById('edit-<?= (int)$batch['id'] ?>').showModal()">Expiry: <?= h(date('M d, Y',strtotime($batch['expiration_date']))) ?> &mdash; <?= (int)$batch['quantity'] ?> <?= h($batch['unit']) ?><?= $batch['batch_number']?' / '.h($batch['batch_number']):'' ?></button>
<?php endforeach; ?>
</div>
<div class="form-actions"><button type="button" class="btn secondary" onclick="this.closest('dialog').close()">Cancel</button></div>
</dialog>
<?php endif; ?>
<?php foreach($product['batches'] as $m): ?>
        <dialog id="edit-<?= (int)$m["id"] ?>">
            <h3>Edit <?= h($m["name"]) ?></h3>
            <?php if(count($product['batches'])>1): ?><p>Name, generic name, brand, description, category, unit and reorder point apply to all expiry records. Supplier, batch number, expiry and cost apply only to this record.</p><?php endif; ?>
            <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" value="<?= (int)$m["id"] ?>">
                <div class="form-grid">
                    <label>Name<input type="text" name="name" value="<?= h($m["name"]) ?>" maxlength="150" required></label>
                    <label>Generic Name<input type="text" name="generic_name" value="<?= h($m["generic_name"] ?? "") ?>" maxlength="150"></label>
                    <label>Brand<input type="text" name="brand" value="<?= h($m["brand"] ?? "") ?>" maxlength="150"></label>
                    <label>Description<input type="text" name="description" value="<?= h($m["description"] ?? "") ?>" maxlength="255" placeholder="e.g. 500 mg tablet"></label>
                    <label>Category<input type="text" name="category" value="<?= h($m["category"]) ?>" maxlength="100" required></label>
                    <label>Unit<input type="text" name="unit" value="<?= h($m["unit"]) ?>" maxlength="50" required></label>
                    <label>Supplier<input type="text" name="supplier" value="<?= h($m["supplier"]) ?>" maxlength="150"></label>
                    <label>Batch Number<input type="text" name="batch_number" value="<?= h($m["batch_number"]) ?>" maxlength="100"></label>
                    <label>Quantity (change via Manage Inventory)<input type="number" value="<?= (int)$m["quantity"] ?>" disabled></label>
                    <label>Reorder Point<input type="number" name="reorder_point" value="<?= (int)$m["reorder_point"] ?>" min="0" required></label>
                    <label>Expiration Date<input type="date" name="expiration_date" value="<?= h($m["expiration_date"]) ?>" required></label>
                    <label>Unit Cost<input type="number" step="0.01" min="0" name="unit_cost" value="<?= number_format((float)$m["unit_cost"], 2, '.', '') ?>" required></label>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn secondary" onclick="this.closest('dialog').close()">Cancel</button>
                    <button type="submit" class="btn primary">Save Changes</button>
                </div>
            </form>
        </dialog>
<?php endforeach; ?></td></tr>
<?php endforeach; ?>
<?php if(!$items): ?><tr><td colspan="8">No medicines match your search.</td></tr><?php endif; ?>
</tbody></table>
<nav class="list-pagination" aria-label="Medicine pages"><span>Page <?= $pager['page'] ?> of <?= $pager['pages'] ?></span><?php if($pager['page']>1): ?> <a class="btn secondary" href="?<?= h(http_build_query(['q'=>$search,'show'=>$show,'sort'=>$sort,'page'=>$pager['page']-1])) ?>">Previous</a><?php endif; ?><?php if($pager['page']<$pager['pages']): ?> <a class="btn secondary" href="?<?= h(http_build_query(['q'=>$search,'show'=>$show,'sort'=>$sort,'page'=>$pager['page']+1])) ?>">Next</a><?php endif; ?></nav>
</section>

<?php if(is_admin()): ?><details class="admin-maintenance"><summary>Administrator maintenance</summary><p>For older records that were added separately. New stock received under an existing medicine is already grouped automatically.</p><a href="medicine_groups.php">Fix duplicate records</a></details><?php endif; ?>

<dialog id="add-medicine" class="record-dialog" aria-labelledby="add-medicine-title"><div class="record-dialog-heading"><h3 id="add-medicine-title">Add Medicine</h3><button type="button" class="btn secondary" onclick="this.closest('dialog').close()">Close</button></div>
<form method="post" class="form-grid">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="add">
    <label>Name<input name="name" maxlength="150" required></label>
    <label>Generic Name<input name="generic_name" maxlength="150"></label>
    <label>Brand<input name="brand" maxlength="150"></label>
    <label>Description<input name="description" maxlength="255" placeholder="e.g. 500 mg tablet"></label>
    <label>Category<input name="category" maxlength="100" required></label>
    <label>Unit<input name="unit" value="tablet" maxlength="50" required></label>
    <label>Supplier<input name="supplier" maxlength="150"></label>
    <label>Batch Number<input name="batch_number" maxlength="100"></label>
    <label>Initial Quantity<input type="number" name="quantity" min="0" value="0" required></label>
    <label>Reorder Point<input type="number" name="reorder_point" min="0" value="10" required></label>
    <label>Expiration Date<input type="date" name="expiration_date" required></label>
    <label>Unit Cost<input type="number" step="0.01" min="0" name="unit_cost" value="0"></label>
    <div><button class="btn primary">Add Medicine</button></div>
</form>
</dialog>

<?php include __DIR__ . "/includes/footer.php"; ?>