<?php
require_once __DIR__ . "/includes/auth.php";
require_login();
$page_title = "Alerts";
if($_SERVER['REQUEST_METHOD']==='POST'){
 verify_csrf();
 try{
  $choice=$_POST['expiry_period'] ?? '';
  if(!is_string($choice)||!in_array($choice,['30','60','90','custom'],true))throw new InvalidArgumentException('Choose an expiry warning period.');
  $days=save_expiry_warning_days($pdo,$choice==='custom'?($_POST['custom_days'] ?? ''):$choice,is_admin());
  record_activity($_SESSION['user']['id'],$_SESSION['user']['username'],'expiry_warning_settings','Expiry warning period set to '.$days.' days');
  flash('success','Expiry warnings are now set to '.$days.' days across the system.');
 }catch(InvalidArgumentException $e){flash('error',$e->getMessage());}
 catch(Throwable $e){error_log('Expiry settings failed: '.$e->getMessage());flash('error','Could not save the expiry warning period. Please try again.');}
 redirect('alerts.php');
}
$warningDays=expiry_warning_days();

// Expiry alerts are per stock record; low-stock alerts use the medicine's combined usable stock.
$rows=medicine_attention_rows(clinic_stock_rows($pdo));

$counts = ["expired" => 0, "soon" => 0, "low" => 0];
foreach ($rows as &$r) {

    if ($r["exp"] && $r["exp"]["label"] === "Expired") { $counts["expired"]++; }
    elseif ($r["exp"]) { $counts["soon"]++; }
    if ($r["low"]) { $counts["low"]++; }
}
unset($r);
$filter=is_string($_GET['filter'] ?? null)?$_GET['filter']:'';
if(!in_array($filter,['low','expiry'],true))$filter='';
if($filter!=='')$rows=array_values(array_filter($rows,function($row)use($filter){return $filter==='low'?$row['low']:(bool)$row['exp'];}));


include __DIR__ . "/includes/header.php";
render_flash();
?>
<div class="cards">
    <div class="card danger-card"><span>Expired</span><strong><?= (int)$counts["expired"] ?></strong></div>
    <div class="card warning"><span>Expiring within <?= $warningDays ?> days</span><strong><?= (int)$counts["soon"] ?></strong></div>
    <div class="card danger-card"><span>Low Stock</span><strong><?= (int)$counts["low"] ?></strong></div>
</div>

<?php if(is_admin()): ?>
<section class="panel"><details><summary>Expiry warning settings</summary>
<p>Warn staff before stock expires. This applies to Alerts, Dashboard and medicine expiry badges. Expired stock is always flagged.</p>
<form method="post" class="form-grid">
<?= csrf_field() ?>
<label>Warn before expiry<select name="expiry_period" id="expiry-warning-period">
<?php foreach([30,60,90] as $preset): ?><option value="<?= $preset ?>" <?= $warningDays===$preset?'selected':'' ?>><?= $preset ?> days</option><?php endforeach; ?>
<option value="custom" <?= !in_array($warningDays,[30,60,90],true)?'selected':'' ?>>Custom</option>
</select></label>
<label id="expiry-custom-label">Number of days (1-365)<input type="number" id="expiry-custom-days" name="custom_days" min="1" max="365" step="1" value="<?= $warningDays ?>"></label>
<div class="form-actions"><button class="btn primary" type="submit">Save warning period</button></div>
</form></details></section>
<script src="assets/expiry-settings.js?v=<?= filemtime(__DIR__.'/assets/expiry-settings.js') ?>" defer></script>
<?php endif; ?>
<section class="panel">
<h2><?= $filter==='low'?'Low-stock medicines':($filter==='expiry'?'Expiry alerts':'Needs Attention') ?></h2>
<?php if($filter!==''): ?><p><a href="alerts.php">View all alerts</a></p><?php endif; ?>
<?php if (!$rows): ?>
    <p><?= $filter==='low'?'No medicines are currently low in stock.':($filter==='expiry'?'No stock currently needs an expiry warning.':'No alerts. All medicines are well stocked and within date.') ?></p>
<?php else: ?>
<p class="muted">Choose an action for the affected batch. Opening an action does not change stock; staff must confirm and save the movement. Alerts update when stock or expiry conditions change.</p>
<table>
<thead>
<tr><th>Medicine</th><th>Batch</th><th>Stock record quantity</th><th>Reorder Point</th><th>Expiration</th><th>Alert</th><th>Action</th></tr>
</thead>
<tbody>
<?php foreach ($rows as $m): ?>
<tr>
    <td><strong><?= h($m["name"]) ?></strong></td>
    <td><?= h($m["batch_number"]) ?></td>
    <td><?= h($m["quantity"]) ?> <?= h($m["unit"]) ?><?php if($m['group_batch_count']>1): ?><br><small>Total usable for medicine: <?= (int)$m['group_usable_qty'] ?> <?= h($m['unit']) ?></small><?php endif; ?></td>
    <td><?= h($m["reorder_point"]) ?></td>
    <td><?= h($m["expiration_date"]) ?></td>
    <td>
        <?php if ($m["exp"]): ?><span class="badge <?= h($m["exp"]["class"]) ?>"><?= h($m["exp"]["label"]) ?></span><?php endif; ?>
        <?php if ($m["low"]): ?><span class="badge low"><?= (int)$m["group_usable_qty"] === 0 ? "Out of stock" : "Low stock" ?></span><?php endif; ?>
    </td>
    <td>
    <?php if($m['exp'] && $m['exp']['label']==='Expired' && (int)$m['quantity']>0): ?>
        <a class="btn small danger" href="transactions.php?<?= h(http_build_query(['medicine_id'=>$m['id'],'movement'=>'Disposal'])) ?>#record-movement">Record disposal</a>
    <?php elseif($m['exp'] && $m['exp']['label']==='Expired' && !$m['low']): ?>
        <small>No stock left to dispose of.</small>
    <?php else: ?>
        <a class="btn small secondary" href="transactions.php?<?= h(http_build_query(['medicine_id'=>$m['id']])) ?>#record-movement">Manage stock</a>
    <?php endif; ?>
    </td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
<?php endif; ?>
</section>
<?php include __DIR__ . "/includes/footer.php"; ?>