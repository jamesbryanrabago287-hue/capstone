<?php
require_once __DIR__.'/includes/auth.php';require_login();
require_once __DIR__.'/includes/inventory.php';ensure_inventory_schema($pdo);ensure_medicine_groups($pdo);
require_once __DIR__.'/includes/assessment.php';require_once __DIR__.'/includes/dispensing.php';ensure_assessment_tables($pdo);ensure_dispensing_table($pdo);
require_once __DIR__.'/includes/patient_issuing.php';ensure_patient_schema($pdo);ensure_patient_issues($pdo);
$page_title='Manage Inventory';
$requestedMovement=is_string($_GET['movement'] ?? null)?$_GET['movement']:'';
$initialMovement=in_array($requestedMovement,['Stock In','Stock Out','Disposal'],true)?$requestedMovement:'Stock In';
if($_SERVER['REQUEST_METHOD']==='POST') {
 verify_csrf();
 $afterSave='transactions.php';
 try {
  $result=($_POST['type'] ?? '')==='Stock In'?inventory_receive_by_expiry($pdo,$_POST,$_SESSION['user']):(($_POST['type'] ?? '')==='Stock Out'?inventory_issue_to_patient($pdo,$_POST,$_SESSION['user']):inventory_movement($pdo,$_POST,$_SESSION['user']));
  if(isset($result['patient_id']))$afterSave='transactions.php?movement=Stock%20Out&patient_id='.(int)$result['patient_id'].'#record-movement';
  if(!$result['duplicate'])record_activity($_SESSION['user']['id'],$_SESSION['user']['username'],'inventory_movement',$result['type'].' for medicine #'.(int)($result['medicine_id'] ?? $_POST['medicine_id']));
  $verb=['Stock In'=>'Received','Stock Out'=>'Issued','Disposal'=>'Disposed of','Adjustment In'=>'Added by count adjustment','Adjustment Out'=>'Removed by count adjustment'][$result['type']];
  flash('success',($result['duplicate']?'Already recorded: ':'').$verb.' '.$result['quantity'].' '.$result['unit'].' of '.$result['name'].'; batch '.($result['batch'] ?: 'not specified').'. Balance after this movement: '.$result['balance'].(isset($result['expiration_date'])?'; expiry '.$result['expiration_date']:'').(isset($result['patient_name'])?'; given to '.$result['patient_name']:'').'.');
 }catch(InvalidArgumentException $e){flash('error',$e->getMessage());}
 catch(Throwable $e){error_log('Inventory movement failed: '.$e->getMessage());flash('error','The movement was not saved. Refresh and try again.');}
 redirect($afterSave);
}
$selectedPatient=null;$patientId=filter_var($_GET['patient_id'] ?? '',FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);if($patientId){$stmt=$pdo->prepare('SELECT id,full_name,student_no,patient_type FROM students WHERE id=?');$stmt->execute([$patientId]);$selectedPatient=$stmt->fetch()?:null;}
$today = date("Y-m-d");
$meds = $pdo->query("SELECT * FROM medicines WHERE archived_at IS NULL ORDER BY name")->fetchAll();
require_once __DIR__.'/includes/list_filters.php';
$range=inventory_period($_GET);$conditions=[];$params=[];
$selectedMedicine=filter_var($_GET['medicine_id'] ?? '',FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]) ?: null;
if($range['from']!==''){$conditions[]='t.transaction_date>=? AND t.transaction_date<?';$params[]=$range['from'];$params[]=$range['until'];}
if($selectedMedicine){$conditions[]='t.medicine_id=?';$params[]=$selectedMedicine;}
$where=$conditions?' WHERE '.implode(' AND ',$conditions):'';
$stmt=$pdo->prepare('SELECT COUNT(*) FROM transactions t'.$where);$stmt->execute($params);$total=(int)$stmt->fetchColumn();$pager=clinic_pager($total,$_GET['page'] ?? 1);
$stmt=$pdo->prepare('SELECT t.*,m.name AS medicine_name,u.full_name,v.id AS linked_visit_id,COALESCE(ip.patient_id,v.student_id) AS linked_patient_id,COALESCE(ip.patient_name_snapshot,v.student_name_snapshot) AS linked_patient_name FROM transactions t JOIN medicines m ON m.id=t.medicine_id JOIN users u ON u.id=t.user_id LEFT JOIN clinic_dispenses d ON d.transaction_id=t.id LEFT JOIN clinic_consultations v ON v.id=d.consultation_id LEFT JOIN inventory_patient_issues ip ON ip.transaction_id=t.id'.$where.' ORDER BY t.id DESC LIMIT 20 OFFSET '.$pager['offset']);$stmt->execute($params);$rows=$stmt->fetchAll();


include __DIR__ . "/includes/header.php";
render_flash();
?>
<section class="panel" id="record-movement" aria-labelledby="record-movement-title"><h2 id="record-movement-title">Update medicine stock</h2>
<form method="post" class="form-grid" id="stock-movement-form">
    <?= csrf_field() ?><input type="hidden" name="request_token" value="<?= h(bin2hex(random_bytes(32))) ?>"><input type="hidden" name="expected_balance" id="expected-balance" value="<?= isset($meds[0])?(int)$meds[0]["quantity"]:0 ?>">
    <label id="movement-type-label">What do you want to do?
        <select name="type" id="movement-type">
            <option value="Stock In" <?= $initialMovement==='Stock In'?'selected':'' ?>>Add stock</option>
            <option value="Stock Out" <?= $initialMovement==='Stock Out'?'selected':'' ?>>Give medicine</option><option value="Disposal" <?= $initialMovement==='Disposal'?'selected':'' ?>>Dispose expired / damaged stock</option><?php if(is_admin()): ?><option value="Physical Count">Check stock count</option><?php endif; ?>
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
    <section id="issue-patient-section" class="issue-patient-section" hidden aria-label="Patient receiving medicine">
    <div class="issue-patient-heading"><strong>Who is receiving the medicine?</strong><button type="button" class="btn small secondary" data-open-dialog="issue-new-patient" id="issue-add-patient">Add patient</button></div>
    <div class="medicine-picker issue-patient-picker"><label>Patient<input type="search" id="issue-patient-search" autocomplete="off" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="issue-patient-matches" placeholder="Search by patient name or ID" value="<?= h($selectedPatient?$selectedPatient['full_name'].' ('.($selectedPatient['student_no'] ?? $selectedPatient['patient_type']).')':'') ?>" <?= $selectedPatient?'data-selected="1"':'' ?>></label><div id="issue-patient-matches" class="medicine-matches" role="listbox" aria-label="Matching patients" hidden></div></div>
    <input type="hidden" id="issue-patient-id" name="patient_id" value="<?= $selectedPatient?(int)$selectedPatient['id']:'' ?>"><input type="hidden" id="issue-patient-mode" name="patient_mode" value="existing">
    <p id="issue-patient-status" role="status" aria-live="polite"><?= $selectedPatient?'Selected: '.h($selectedPatient['full_name']):'Choose an existing patient, or add a new one.' ?></p>
    <button type="button" id="issue-choose-existing" class="btn small secondary" hidden>Choose existing patient</button>
    <dialog id="issue-new-patient" class="record-dialog" aria-labelledby="issue-new-patient-title"><div class="record-dialog-heading"><h3 id="issue-new-patient-title">Add patient</h3><button type="button" class="btn secondary" id="issue-new-close">Close</button></div>
    <div class="form-grid">
    <label>Patient type<select name="new_patient[patient_type]" id="issue-new-type" data-issue-new disabled><option>Student</option><option>Faculty</option><option>Staff</option><option>Other</option></select></label>
    <label>Full name<input name="new_patient[full_name]" id="issue-new-name" maxlength="120" data-issue-new disabled required></label>
    <label>Student / Employee ID<input name="new_patient[student_no]" id="issue-new-number" maxlength="50" data-issue-new disabled required></label>
    <label>Department / College<input name="new_patient[college]" maxlength="120" data-issue-new disabled></label>
    <label data-issue-student>Course<input name="new_patient[course]" maxlength="120" data-issue-new disabled></label>
    <label data-issue-student>Year level<input name="new_patient[year_level]" maxlength="30" data-issue-new disabled></label>
    <label>Contact number<input name="new_patient[contact_no]" maxlength="50" data-issue-new disabled></label>
    </div><p class="muted">Save patient registers them immediately. You can record the medicine afterward.</p>
    <input type="hidden" id="issue-register-token" value="<?= h(bin2hex(random_bytes(32))) ?>"><p id="issue-register-error" role="alert" class="alert danger" hidden></p>
    <div class="form-actions"><button type="button" class="btn secondary" id="issue-new-cancel">Cancel</button><button type="button" class="btn primary" id="issue-use-new">Save patient</button></div>
    </dialog>
    </section>
    <p class="movement-help" id="movement-help" aria-live="polite"></p><label id="quantity-label"><span id="quantity-caption">Quantity to add</span><input id="movement-quantity" type="number" name="quantity" min="1" max="100000" required></label><label id="count-label" hidden>Counted stock<input id="actual-count" type="number" name="actual_count" min="0" max="1000000" disabled></label><p id="count-preview" aria-live="polite"></p>
    <label id="receive-expiry-label"><span>Expiry date</span><input type="date" name="expiration_date" id="receive-expiry" min="<?= h($today) ?>" required></label>
    <label id="reference-label"><span id="reference-caption">Receipt number (optional)</span><input id="movement-reference" name="reference_no" maxlength="100" placeholder="Delivery note or receipt number"></label>
    <label id="reason-label" hidden><span id="reason-caption">Reason</span><textarea rows="3" name="notes" id="movement-notes" maxlength="1000" placeholder="Required for disposal and physical counts; include the reason and supporting reference."></textarea></label>
    <div class="movement-actions"><button class="btn primary" id="save-movement">Save</button></div>
</form>
</section>

<section class="panel">
<h2>Transaction History</h2><?php if($range['error']): ?><p class="alert danger" role="alert"><?= h($range['error']) ?></p><?php endif; ?>
<form method="get" class="assessment-filters"><label>Period<select name="period" id="history-period"><?php foreach(['today'=>'Today','7'=>'Last 7 days','30'=>'Last 30 days','90'=>'Last 90 days','all'=>'All time','custom'=>'Custom range'] as $key=>$label): ?><option value="<?= h($key) ?>" <?= $range['period']===(string)$key?'selected':'' ?>><?= h($label) ?></option><?php endforeach; ?></select></label><label class="custom-period-field" <?= $range['period']!=='custom'?'hidden':'' ?>>From<input type="date" name="from" value="<?= h($range['from']) ?>" <?= $range['period']!=='custom'?'disabled':'required' ?>></label><label class="custom-period-field" <?= $range['period']!=='custom'?'hidden':'' ?>>Through<input type="date" name="to" value="<?= h($range['to']) ?>" <?= $range['period']!=='custom'?'disabled':'required' ?>></label><?php if($selectedMedicine): ?><input type="hidden" name="medicine_id" value="<?= $selectedMedicine ?>"><?php endif; ?><button type="submit">Apply period</button></form><p><span id="history-period-help"><?= $range['period']==='custom'?'Both dates are included.':'Choose Custom range to enter your own dates.' ?></span> This filters history only.</p><p><?= $total ?> matching movements. Disposal and adjustments are excluded from medicine usage. Older records may not have recorded balances.</p>
<table>
    <thead>
        <tr><th>Date</th><th>Medicine</th><th>Type</th><th>Qty</th><th>Balance before / after</th><th>Patient</th><th>Reference / visit</th><th>Notes</th><th>User</th></tr>
    </thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
        <tr>
            <td><?= h($r["transaction_date"]) ?></td>
            <td><?= h($r["medicine_name"]) ?></td>
            <td><span class="badge <?= $r["type"] === "Stock In" ? "success" : "warning" ?>"><?= h($r["type"]) ?></span></td>
            <td><?= h($r["quantity"]) ?></td><td><?= $r["balance_before"]===null?"Not recorded":h($r["balance_before"])." / ".h($r["balance_after"]) ?></td>
            <td><?php if($r['linked_patient_id']): ?><a href="patient_history.php?id=<?= (int)$r['linked_patient_id'] ?>"><?= h($r['linked_patient_name']) ?></a><?php else: ?><?= $r['type']==='Stock Out'?'Not recorded':'&mdash;' ?><?php endif; ?></td>
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
<script src="assets/patient-issuing.js?v=<?= filemtime(__DIR__.'/assets/patient-issuing.js') ?>" defer></script>
<?php include __DIR__ . "/includes/footer.php"; ?>