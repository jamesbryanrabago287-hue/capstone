<?php
require_once __DIR__.'/includes/auth.php';require_login();
require_once __DIR__.'/includes/inventory.php';ensure_inventory_schema($pdo);ensure_medicine_groups($pdo);
require_once __DIR__.'/includes/assessment.php';require_once __DIR__.'/includes/dispensing.php';ensure_assessment_tables($pdo);ensure_dispensing_table($pdo);
$page_title='Manage Inventory';
$requestedMovement=is_string($_GET['movement'] ?? null)?$_GET['movement']:'';
$initialMovement=in_array($requestedMovement,['Stock In','Stock Out','Disposal'],true)?$requestedMovement:'Stock In';
if($_SERVER['REQUEST_METHOD']==='POST') {
 verify_csrf();
 try {
  $result=($_POST['type'] ?? '')==='Stock In'?inventory_receive_by_expiry($pdo,$_POST,$_SESSION['user']):inventory_movement($pdo,$_POST,$_SESSION['user']);
  if(!$result['duplicate'])record_activity($_SESSION['user']['id'],$_SESSION['user']['username'],'inventory_movement',$result['type'].' for medicine #'.(int)($result['medicine_id'] ?? $_POST['medicine_id']));
  $verb=['Stock In'=>'Received','Stock Out'=>'Issued','Disposal'=>'Disposed of','Adjustment In'=>'Added by count adjustment','Adjustment Out'=>'Removed by count adjustment'][$result['type']];
  flash('success',($result['duplicate']?'Already recorded: ':'').$verb.' '.$result['quantity'].' '.$result['unit'].' of '.$result['name'].'; batch '.($result['batch'] ?: 'not specified').'. Balance after this movement: '.$result['balance'].(isset($result['expiration_date'])?'; expiry '.$result['expiration_date']:'').'.');
 }catch(InvalidArgumentException $e){flash('error',$e->getMessage());}
 catch(Throwable $e){error_log('Inventory movement failed: '.$e->getMessage());flash('error','The movement was not saved. Refresh and try again.');}
 redirect('transactions.php');
}
$today = date("Y-m-d");
$meds = $pdo->query("SELECT * FROM medicines WHERE archived_at IS NULL ORDER BY name")->fetchAll();
require_once __DIR__.'/includes/list_filters.php';
$range=inventory_period($_GET);$conditions=[];$params=[];
$selectedMedicine=filter_var($_GET['medicine_id'] ?? '',FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]) ?: null;
if($range['from']!==''){$conditions[]='t.transaction_date>=? AND t.transaction_date<?';$params[]=$range['from'];$params[]=$range['until'];}
if($selectedMedicine){$conditions[]='t.medicine_id=?';$params[]=$selectedMedicine;}
$where=$conditions?' WHERE '.implode(' AND ',$conditions):'';
$stmt=$pdo->prepare('SELECT COUNT(*) FROM transactions t'.$where);$stmt->execute($params);$total=(int)$stmt->fetchColumn();$pager=clinic_pager($total,$_GET['page'] ?? 1);
$stmt=$pdo->prepare('SELECT t.*,m.name AS medicine_name,u.full_name,v.id AS linked_visit_id FROM transactions t JOIN medicines m ON m.id=t.medicine_id JOIN users u ON u.id=t.user_id LEFT JOIN clinic_dispenses d ON d.transaction_id=t.id LEFT JOIN clinic_consultations v ON v.id=d.consultation_id'.$where.' ORDER BY t.id DESC LIMIT 20 OFFSET '.$pager['offset']);$stmt->execute($params);$rows=$stmt->fetchAll();


include __DIR__ . "/includes/header.php";
render_flash();
?>
<section class="panel">
<details class="form-disclosure" id="record-movement" open>
<summary>Record a stock movement</summary>
<form method="post" class="form-grid">
    <?= csrf_field() ?><input type="hidden" name="request_token" value="<?= h(bin2hex(random_bytes(32))) ?>"><input type="hidden" name="expected_balance" id="expected-balance" value="<?= isset($meds[0])?(int)$meds[0]["quantity"]:0 ?>">
    <label id="movement-type-label">What are you recording?
        <select name="type" id="movement-type">
            <option value="Stock In" <?= $initialMovement==='Stock In'?'selected':'' ?>>Receive stock (delivery)</option>
            <option value="Stock Out" <?= $initialMovement==='Stock Out'?'selected':'' ?>>Issue medicine</option><option value="Disposal" <?= $initialMovement==='Disposal'?'selected':'' ?>>Dispose of stock</option><?php if(is_admin()): ?><option value="Physical Count">Physical count / correct balance</option><?php endif; ?>
        </select>
    </label>
    <div class="medicine-picker">
    <label id="movement-search-label" hidden>Medicine batch<input type="search" id="movement-search" placeholder="Type a medicine name or batch" autocomplete="off" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="movement-matches" aria-describedby="movement-search-status"></label>
    <label id="movement-native-label">Medicine batch
        <select name="medicine_id" id="movement-medicine" required>
            <option value="">Select a medicine batch</option>
            <?php foreach ($meds as $m): ?>
                <option value="<?= (int)$m["id"] ?>" data-expiry="<?= h($m['expiration_date']) ?>" data-unit="<?= h($m['unit']) ?>" data-group="<?= h($m['reorder_group_id']?'g:'.$m['reorder_group_id'].':'.hash('sha256',medicine_identity($m)):'b:'.$m['id']) ?>" data-medicine-label="<?= h($m['name'].' ('.$m['unit'].')') ?>" data-balance="<?= (int)$m["quantity"] ?>" data-expired="<?= $m["expiration_date"] < $today?'1':'0' ?>" data-issuable="<?= (int)$m["quantity"]>0 && $m["expiration_date"] >= $today?'1':'0' ?>" <?= $selectedMedicine===(int)$m["id"]?'selected':'' ?>>
                    <?= h($m["name"]) ?> [Batch <?= h($m["batch_number"] ?: "not specified") ?>; expires <?= h($m["expiration_date"]) ?>] — <?= (int)$m["quantity"] ?> <?= h($m["unit"]) ?><?= $m["expiration_date"] < $today ? " (EXPIRED)" : "" ?>
                </option>
            <?php endforeach; ?>
        </select>
    </label>

    <div id="movement-matches" class="medicine-matches" role="listbox" aria-label="Matching medicine batches" hidden></div>
    <p id="movement-search-status" role="status" aria-live="polite">Type and choose a medicine batch.</p>
    </div>
    <p class="movement-help" id="movement-help" aria-live="polite"></p><label id="quantity-label"><span id="quantity-caption">Quantity received</span><input id="movement-quantity" type="number" name="quantity" min="1" max="100000" required></label><label id="count-label" hidden>Actual physical count<input id="actual-count" type="number" name="actual_count" min="0" max="1000000" disabled></label><p id="count-preview" aria-live="polite"></p>
    <label id="receive-expiry-label"><span>Expiry date of stock received</span><input type="date" name="expiration_date" id="receive-expiry" min="<?= h($today) ?>" required></label>
    <label id="reference-label"><span id="reference-caption">Delivery reference (optional)</span><input id="movement-reference" name="reference_no" maxlength="100" placeholder="Delivery note or receipt number"></label>
    <label id="reason-label" hidden><span id="reason-caption">Reason</span><textarea rows="3" name="notes" id="movement-notes" maxlength="1000" placeholder="Required for disposal and physical counts; include the reason and supporting reference."></textarea></label>
    <div class="movement-actions"><button class="btn primary" id="save-movement">Save movement</button></div>
</form>
</details>
</section>

<section class="panel">
<h2>Transaction History</h2><?php if($range['error']): ?><p class="alert danger" role="alert"><?= h($range['error']) ?></p><?php endif; ?>
<form method="get" class="assessment-filters"><label>Period<select name="period" id="history-period"><?php foreach(['today'=>'Today','7'=>'Last 7 days','30'=>'Last 30 days','90'=>'Last 90 days','all'=>'All time','custom'=>'Custom range'] as $key=>$label): ?><option value="<?= h($key) ?>" <?= $range['period']===(string)$key?'selected':'' ?>><?= h($label) ?></option><?php endforeach; ?></select></label><label class="custom-period-field" <?= $range['period']!=='custom'?'hidden':'' ?>>From<input type="date" name="from" value="<?= h($range['from']) ?>" <?= $range['period']!=='custom'?'disabled':'required' ?>></label><label class="custom-period-field" <?= $range['period']!=='custom'?'hidden':'' ?>>Through<input type="date" name="to" value="<?= h($range['to']) ?>" <?= $range['period']!=='custom'?'disabled':'required' ?>></label><?php if($selectedMedicine): ?><input type="hidden" name="medicine_id" value="<?= $selectedMedicine ?>"><?php endif; ?><button type="submit">Apply period</button></form><p><span id="history-period-help"><?= $range['period']==='custom'?'Both dates are included.':'Choose Custom range to enter your own dates.' ?></span> This filters history only.</p><p><?= $total ?> matching movements. Disposal and adjustments are excluded from medicine usage. Older records may not have recorded balances.</p>
<table>
    <thead>
        <tr><th>Date</th><th>Medicine</th><th>Type</th><th>Qty</th><th>Balance before / after</th><th>Reference / visit</th><th>Notes</th><th>User</th></tr>
    </thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
        <tr>
            <td><?= h($r["transaction_date"]) ?></td>
            <td><?= h($r["medicine_name"]) ?></td>
            <td><span class="badge <?= $r["type"] === "Stock In" ? "success" : "warning" ?>"><?= h($r["type"]) ?></span></td>
            <td><?= h($r["quantity"]) ?></td><td><?= $r["balance_before"]===null?"Not recorded":h($r["balance_before"])." / ".h($r["balance_after"]) ?></td>
            <td><?= h($r["reference_no"]) ?><?php if($r["linked_visit_id"]): ?><br><a href="consultation.php?visit_id=<?= (int)$r["linked_visit_id"] ?>">View consultation</a><?php endif; ?></td>
            <td><?= h($r["notes"]) ?></td>
            <td><?= h($r["full_name"]) ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<nav class="list-pagination" aria-label="Inventory history pages"><span>Page <?= $pager['page'] ?> of <?= $pager['pages'] ?></span><?php foreach(['Previous'=>-1,'Next'=>1] as $label=>$delta): $target=$pager['page']+$delta;if($target<1||$target>$pager['pages'])continue; ?><a class="btn secondary" href="?<?= h(http_build_query(['period'=>$range['period'],'from'=>$range['from'],'to'=>$range['to'],'medicine_id'=>$selectedMedicine,'page'=>$target])) ?>"><?= h($label) ?></a><?php endforeach; ?></nav>
</section>

<script src="assets/inventory.js?v=<?= (int)filemtime(__DIR__ . '/assets/inventory.js') ?>" defer></script>
<?php include __DIR__ . "/includes/footer.php"; ?>