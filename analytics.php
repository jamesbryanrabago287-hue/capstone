<?php
require_once __DIR__ . "/includes/auth.php";
require_login();
$page_title = "Decision Support";

// One query for every medicine (usage included) instead of one query per row
$items = reorder_list();

foreach($items as &$medicine)$medicine['review']=medicine_restock_review($medicine);unset($medicine);
usort($items,function($a,$b){return ($a['review']['priority']<=>$b['review']['priority'])?:strcasecmp($a['name'],$b['name']);});
$need_count=0;$suggestedCount=0;$restockBudget=0;$unpriced=0;
foreach($items as $medicine){if($medicine['review']['needs_review'])$need_count++;if($medicine['review']['quantity']>0){$suggestedCount++;if($medicine['review']['estimated_cost']===null)$unpriced++;else $restockBudget+=$medicine['review']['estimated_cost'];}}

// Medicine-giving records per month; no mixed-unit quantity total.
$months = [];
for ($i = 5; $i >= 0; $i--) {
    $months[date("Y-m", strtotime("first day of -{$i} month"))] = 0;
}
$monthly = $pdo->query(
    "SELECT DATE_FORMAT(transaction_date, '%Y-%m') AS ym, COUNT(*) AS units
     FROM transactions
     WHERE type = 'Stock Out'
       AND transaction_date >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 5 MONTH), '%Y-%m-01')
       AND transaction_date <= NOW()
     GROUP BY ym"
)->fetchAll();
foreach ($monthly as $row) {
    if (isset($months[$row["ym"]])) {
        $months[$row["ym"]] = (int)$row["units"];
    }
}
$max_units = max(1, max($months));
$emptyCount=0;$usedMedicineCount=0;foreach($items as $medicine){if((int)$medicine['usable_qty']===0)$emptyCount++;if((int)$medicine['used30']>0)$usedMedicineCount++;}
$issuedByBatch=[];foreach($pdo->query("SELECT medicine_id,COUNT(*) AS records,SUM(quantity) AS quantity FROM transactions WHERE type='Stock Out' AND transaction_date>=DATE_SUB(NOW(),INTERVAL 30 DAY) AND transaction_date<=NOW() GROUP BY medicine_id")->fetchAll() as $issue)$issuedByBatch[(int)$issue['medicine_id']]=$issue;
$frequent=[];foreach($items as $medicine){$records=0;$quantity=0;foreach($medicine['batch_ids'] as $batchId){$records+=(int)($issuedByBatch[$batchId]['records'] ?? 0);$quantity+=(int)($issuedByBatch[$batchId]['quantity'] ?? 0);}if($records>0)$frequent[]=['name'=>$medicine['name'],'records'=>$records,'quantity'=>$quantity,'unit'=>$medicine['unit']];}
usort($frequent,function($a,$b){return ($b['records']<=>$a['records'])?:strcasecmp($a['name'],$b['name']);});$frequent=array_slice($frequent,0,5);$maxFrequent=$frequent?max(array_column($frequent,'records')):1;


require_once __DIR__.'/includes/list_filters.php';
$view=($_GET['view'] ?? '')==='all'?'all':'needed';
$filtered=$view==='all'?$items:array_values(array_filter($items,function($m){return $m['review']['needs_review'];}));
$pager=clinic_pager(count($filtered),$_GET['page'] ?? 1);$shown=array_slice($filtered,$pager['offset'],20);
$period=($_GET['period'] ?? '')==='90'?90:30;
$usageItems=$items;usort($usageItems,function($a,$b)use($period){return strcasecmp($a['unit'],$b['unit'])?:(($b['used'.$period]<=>$a['used'.$period])?:strcasecmp($a['name'],$b['name']));});
$usagePager=clinic_pager(count($usageItems),$_GET['usage_page'] ?? 1,10);$usageShown=array_slice($usageItems,$usagePager['offset'],10);
$disposalWhere="t.type='Disposal' AND t.transaction_date>=DATE_SUB(NOW(),INTERVAL ".$period." DAY) AND t.transaction_date<=NOW()";
$disposalTotal=(int)$pdo->query('SELECT COUNT(*) FROM transactions t JOIN medicines m ON m.id=t.medicine_id WHERE '.$disposalWhere)->fetchColumn();
$disposalPager=clinic_pager($disposalTotal,$_GET['disposal_page'] ?? 1,10);
$disposals=$pdo->query('SELECT t.transaction_date,t.quantity,t.notes,m.name,m.unit FROM transactions t JOIN medicines m ON m.id=t.medicine_id WHERE '.$disposalWhere.' ORDER BY t.transaction_date DESC,t.id DESC LIMIT 10 OFFSET '.$disposalPager['offset'])->fetchAll();
include __DIR__ . "/includes/header.php";
render_flash();
?>
<section class="panel analytics-overview" aria-labelledby="analytics-title">
<h2 id="analytics-title">Inventory analytics</h2>
<p class="muted">An overview of active medicines and recorded activity. Restocking recommendations are below.</p>
<div class="cards analytics-cards">
<div class="card"><span>Medicine items</span><strong><?= count($items) ?></strong></div>
<div class="card danger-card"><span>Need restock review</span><strong><?= $need_count ?></strong></div>
<div class="card danger-card"><span>Out of usable stock</span><strong><?= $emptyCount ?></strong></div>
<div class="card"><span>Medicines given out in last 30 days</span><strong><?= $usedMedicineCount ?></strong></div>
<div class="card"><span>Estimated restocking cost</span><strong>&#8369;<?= number_format($restockBudget,2) ?></strong><small>Recorded prices; <?= $unpriced ?> item(s) without a price excluded.</small></div>
</div>
</section>
<section class="panel">
<h2>Restocking recommendations</h2>
<p><strong><?= $need_count ?> medicine item(s) need a restock review.</strong> Check the suggested quantities below before preparing a purchase request. Linked batches are combined; expired quantities are excluded.</p>

<form method="get" class="assessment-filters"><input type="hidden" name="period" value="<?= $period ?>"><label>Show<select name="view"><option value="needed" <?= $view==='needed'?'selected':'' ?>>Needs restocking</option><option value="all" <?= $view==='all'?'selected':'' ?>>All medicines</option></select></label><button type="submit">Apply</button></form>
<?php if(!$shown): ?><p><?= $view==='needed'?'No medicines are flagged for restocking. This is based on saved stock thresholds and recorded usage.':'No medicine records found.' ?></p><?php else: ?>
<table><thead><tr><th>Medicine</th><th>Available stock</th><th>Why it needs attention</th><th>Suggested quantity</th><th>Estimated cost</th></tr></thead><tbody>
<?php foreach($shown as $m):$a=$m['advice'];$review=$m['review']; ?><tr id="medicine-<?= (int)$m['id'] ?>">
<td><strong><?= h($m['name']) ?></strong><?php if($m['batch_count']>1): ?><br><small><?= (int)$m['batch_count'] ?> expiry records combined</small><?php endif; ?></td>
<td><?= (int)$m['usable_qty'] ?> <?= h($m['unit']) ?><?php if((int)$m['expired_qty']>0): ?><br><small class="badge red"><?= (int)$m['expired_qty'] ?> expired, excluded</small><?php endif; ?></td>
<td><?= h($review['reason']) ?><br><small><?= $review['limited_history']?'Not enough usage data for a time estimate.':($review['days_left']===null?'No usage estimate.':'About '.(int)$review['days_left'].' days left based on usage in the last 30 days.') ?></small></td>
<td><?php if($review['quantity']>0): ?><strong><?= $review['quantity'] ?> <?= h($m['unit']) ?></strong><details><summary>How calculated</summary><p>Target: <?= $review['target'] ?> <?= h($m['unit']) ?> (the larger of twice the minimum <?= (int)$m['reorder_point'] ?> or <?= (int)$m['used30'] ?> given out in 30 days).</p><p><?= $review['target'] ?> target minus <?= (int)$m['usable_qty'] ?> available = <?= $review['quantity'] ?> suggested.</p><?php if($review['limited_history']): ?><p>Limited usage history. Confirm the quantity with clinic staff.</p><?php endif; ?></details><?php elseif($review['needs_review']): ?>Set a minimum or confirm demand.<?php else: ?>None<?php endif; ?></td>
<td><?php if($review['estimated_cost']!==null): ?>&#8369;<?= number_format($review['estimated_cost'],2) ?><br><small>At &#8369;<?= number_format($review['unit_cost'],2) ?> per <?= h($m['unit']) ?></small><?php elseif($review['quantity']>0): ?>Unit cost not set<?php else: ?>&mdash;<?php endif; ?></td>
</tr><?php endforeach; ?></tbody></table>
<nav class="list-pagination" aria-label="Restock recommendation pages"><span>Page <?= $pager['page'] ?> of <?= $pager['pages'] ?></span><?php foreach(['Previous'=>-1,'Next'=>1] as $label=>$step):$target=$pager['page']+$step;if($target<1||$target>$pager['pages'])continue; ?><a class="btn secondary" href="?<?= h(http_build_query(['view'=>$view,'period'=>$period,'page'=>$target])) ?>"><?= h($label) ?></a><?php endforeach; ?></nav>
<?php endif; ?>
<?php if($suggestedCount>0): ?><p><a class="btn primary" href="reports.php?type=PR">Review Purchase Request</a></p><?php endif; ?>
<p>Suggestions only. Nothing is ordered automatically. Verify stock, demand and delivery timing before purchasing.</p>
</section>
<div class="grid-2">
<section class="panel" aria-labelledby="monthly-activity-title">
<h2 id="monthly-activity-title">Monthly medicine activity</h2><p class="muted">Number of saved medicine-giving records over the last 6 months. These are records, not patient counts.</p>
<?php if(array_sum($months)===0): ?><p>No medicine-giving records in this period.</p><?php endif; ?>
<?php foreach($months as $ym=>$records): ?><div class="bar-row"><div class="bar-row-head"><span><?= h(date('M Y',strtotime($ym.'-01'))) ?></span><span><?= (int)$records ?> records</span></div><div class="bar-track"><div class="bar-fill" style="width:<?= round(($records/$max_units)*100,1) ?>%"></div></div></div><?php endforeach; ?>
</section>
<section class="panel" aria-labelledby="frequent-medicines-title">
<h2 id="frequent-medicines-title">Most frequently given medicines</h2><p class="muted">Last 30 days, ranked by how often each medicine was recorded as given out. Linked expiry records are combined.</p>
<?php if(!$frequent): ?><p>No medicines have been recorded as given out in the last 30 days.</p><?php else: foreach($frequent as $medicine): ?>
<div class="bar-row"><div class="bar-row-head"><strong><?= h($medicine['name']) ?></strong><span><?= (int)$medicine['records'] ?> records</span></div><div class="bar-track"><div class="bar-fill" style="width:<?= round($medicine['records']/$maxFrequent*100,1) ?>%"></div></div><small><?= (int)$medicine['quantity'] ?> <?= h($medicine['unit']) ?> given out</small></div>
<?php endforeach; endif; ?>
</section></div>
<section class="panel" aria-labelledby="medicine-usage-title">
<div class="panel-head"><h2 id="medicine-usage-title">Usage by medicine</h2><form method="get" class="assessment-filters"><input type="hidden" name="view" value="<?= h($view) ?>"><label>Usage and disposal period<select name="period"><option value="30" <?= $period===30?'selected':'' ?>>Last 30 days</option><option value="90" <?= $period===90?'selected':'' ?>>Last 90 days</option></select></label><button type="submit">Apply</button></form></div>
<p class="muted">Quantities are shown in each medicine's own unit. Only medicine given out counts as usage; disposal and count corrections are excluded. Restocking suggestions always use the last 30 days.</p>
<?php if(!$usageShown): ?><p>No active medicine records.</p><?php else: ?><table><thead><tr><th>Medicine</th><th>Given out in <?= $period ?> days</th><th>Available now</th></tr></thead><tbody><?php foreach($usageShown as $medicine): ?><tr><td><?= h($medicine['name']) ?></td><td><?= (int)$medicine['used'.$period] ?> <?= h($medicine['unit']) ?></td><td><?= (int)$medicine['usable_qty'] ?> <?= h($medicine['unit']) ?></td></tr><?php endforeach; ?></tbody></table><?php endif; ?>
<?php if($usagePager['pages']>1): ?><nav class="list-pagination" aria-label="Usage pages"><span>Page <?= $usagePager['page'] ?> of <?= $usagePager['pages'] ?></span><?php foreach(['Previous'=>-1,'Next'=>1] as $label=>$step):$target=$usagePager['page']+$step;if($target<1||$target>$usagePager['pages'])continue; ?><a class="btn secondary" href="?<?= h(http_build_query(['view'=>$view,'period'=>$period,'usage_page'=>$target])) ?>#medicine-usage-title"><?= $label ?></a><?php endforeach; ?></nav><?php endif; ?>
</section>
<section class="panel" aria-labelledby="disposal-title"><h2 id="disposal-title">Disposal history</h2><p class="muted">Last <?= $period ?> days. Review recorded reasons to identify avoidable waste. Older medicines remain in this history even after removal from active inventory.</p>
<?php if(!$disposals): ?><p>No disposal records in this period.</p><?php else: ?><table><thead><tr><th>Date</th><th>Medicine</th><th>Disposed quantity</th><th>Recorded reason</th></tr></thead><tbody><?php foreach($disposals as $disposal): ?><tr><td><?= h($disposal['transaction_date']) ?></td><td><?= h($disposal['name']) ?></td><td><?= (int)$disposal['quantity'] ?> <?= h($disposal['unit']) ?></td><td><?= h(trim((string)$disposal['notes'])!==''?$disposal['notes']:'Reason not recorded') ?></td></tr><?php endforeach; ?></tbody></table><?php endif; ?>
<?php if($disposalPager['pages']>1): ?><nav class="list-pagination" aria-label="Disposal pages"><span>Page <?= $disposalPager['page'] ?> of <?= $disposalPager['pages'] ?></span><?php foreach(['Previous'=>-1,'Next'=>1] as $label=>$step):$target=$disposalPager['page']+$step;if($target<1||$target>$disposalPager['pages'])continue; ?><a class="btn secondary" href="?<?= h(http_build_query(['view'=>$view,'period'=>$period,'disposal_page'=>$target])) ?>#disposal-title"><?= $label ?></a><?php endforeach; ?></nav><?php endif; ?>
</section>
<section class="panel"><details><summary><strong>How are these suggestions calculated?</strong></summary>
<p>Available stock excludes expired batches. A batch is flagged when available stock reaches its saved minimum, or is estimated to run out within 14 days. The suggested order restores stock to the larger of twice its saved minimum or the previous 30 days of recorded issues.</p>
<p>Only medicine issued counts as usage. Disposal and physical-count adjustments do not count. The same reorder calculation is used in procurement reports.</p>
<h3>Longer-term usage estimates</h3>
<p>The baseline below uses up to 90 days of recorded issues divided by the available record age to estimate daily usage. Projected 30-day demand = daily usage times 30. Suggested minimum = daily usage times an assumed 14-day delivery lead time. Values are rounded up, with no additional safety stock modeled. These do not change the saved minimum automatically.</p>
<p>These are implementation assumptions, not a forecasting equation specified by the paper. Short, missing or stockout-affected history can mislead the estimates; clinic review is needed.</p>
<?php if($shown): ?><table><thead><tr><th>Medicine / batch</th><th>Issued in observation period</th><th>Observation days</th><th>Projected next 30 days</th><th>Suggested minimum</th><th>Saved minimum</th><th>Limitation</th></tr></thead><tbody><?php foreach($shown as $m):$p=$m['projection']; ?><tr><td><?= h($m['name']) ?><br><?= h($m['batch_number']) ?></td><td><?= (int)$m['used90'] ?> <?= h($m['unit']) ?></td><td><?= (int)$p['days'] ?></td><td><?= $p['next30']===null?'Unavailable':(int)$p['next30'].' '.h($m['unit']) ?></td><td><?= $p['suggested_point']===null?'Needs review':(int)$p['suggested_point'].' '.h($m['unit']) ?></td><td><?= (int)$m['reorder_point'] ?></td><td><?= h($p['quality']) ?></td></tr><?php endforeach; ?></tbody></table><?php endif; ?>
</details></section>

<?php include __DIR__.'/includes/footer.php'; ?>
