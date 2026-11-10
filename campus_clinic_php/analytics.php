<?php
require_once __DIR__ . "/includes/auth.php";
require_login();
$page_title = "Decision Support";

// One query for every medicine (usage included) instead of one query per row
$items = reorder_list();

// Medicines that need reordering first, then alphabetical
usort($items, function ($a, $b) {
    if ($a["advice"]["needs"] !== $b["advice"]["needs"]) {
        return $a["advice"]["needs"] ? -1 : 1;
    }
    return strcmp($a["name"], $b["name"]);
});
$need_count = 0;
foreach ($items as $i) {
    if ($i["advice"]["needs"]) { $need_count++; }
}

// Stock-out units per month for the last 6 months (months with no usage show 0)
$months = [];
for ($i = 5; $i >= 0; $i--) {
    $months[date("Y-m", strtotime("first day of -{$i} month"))] = 0;
}
$monthly = $pdo->query(
    "SELECT DATE_FORMAT(transaction_date, '%Y-%m') AS ym, SUM(quantity) AS units
     FROM transactions
     WHERE type = 'Stock Out'
       AND transaction_date >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 5 MONTH), '%Y-%m-01')
     GROUP BY ym"
)->fetchAll();
foreach ($monthly as $row) {
    if (isset($months[$row["ym"]])) {
        $months[$row["ym"]] = (int)$row["units"];
    }
}
$max_units = max(1, max($months));

require_once __DIR__.'/includes/list_filters.php';
$view=($_GET['view'] ?? '')==='all'?'all':'needed';
$filtered=$view==='all'?$items:array_values(array_filter($items,function($m){return $m['advice']['needs'];}));
$pager=clinic_pager(count($filtered),$_GET['page'] ?? 1);$shown=array_slice($filtered,$pager['offset'],20);
include __DIR__ . "/includes/header.php";
render_flash();
?>
<section class="panel">
<h2>What needs restocking?</h2>
<p><strong><?= $need_count ?> medicine item(s) need a restock review.</strong> Check the suggested quantities below before preparing a purchase request. Linked batches are combined; expired quantities are excluded.</p>
<?php if(is_admin()): ?><p><a href="medicine_groups.php">Link batches of the same medicine</a></p><?php endif; ?>
<form method="get" class="assessment-filters"><label>Show<select name="view"><option value="needed" <?= $view==='needed'?'selected':'' ?>>Needs restocking</option><option value="all" <?= $view==='all'?'selected':'' ?>>All medicines / linked groups</option></select></label><button type="submit">Apply</button></form>
<?php if(!$shown): ?><p><?= $view==='needed'?'No batches are flagged for restocking. This is based on saved stock thresholds and recorded usage.':'No medicine records found.' ?></p><?php else: ?>
<table><thead><tr><th>Medicine / batch</th><th>Available stock</th><th>Estimated time left</th><th>Suggested action</th><th>Why?</th></tr></thead><tbody>
<?php foreach($shown as $m):$a=$m['advice']; ?><tr id="medicine-<?= (int)$m['id'] ?>">
<td><strong><?= h($m['name']) ?></strong><br><small><?= h($m['batch_count']>1?$m['batch_number']:'Batch '.($m['batch_number'] ?: 'Not specified')) ?></small></td>
<td><?= (int)$m['usable_qty'] ?> <?= h($m['unit']) ?><?php if($m['is_expired'] && (int)$m['expired_qty']>0): ?><br><small class="badge red">Expired stock excluded</small><?php endif; ?></td>
<td><?= $a['days_left']===null?'Not enough usage data':(int)$a['days_left'].' days' ?></td>
<td><?php if($a['needs']): ?><strong>Consider ordering <?= (int)$a['order_qty'] ?> <?= h($m['unit']) ?></strong><?php else: ?>Monitor stock<?php endif; ?></td>
<td><?= $a['needs']?h((int)$m['usable_qty']<=(int)$m['reorder_point']?'Stock reached the saved minimum level.':'Stock may run out within 14 days at the recent usage rate.'):((int)$m['used30']===0?'No recent issues recorded; confirm demand with staff.':'No restock triggered by current rules.') ?></td>
</tr><?php endforeach; ?></tbody></table>
<nav class="list-pagination" aria-label="Restock recommendation pages"><span>Page <?= $pager['page'] ?> of <?= $pager['pages'] ?></span><?php foreach(['Previous'=>-1,'Next'=>1] as $label=>$step):$target=$pager['page']+$step;if($target<1||$target>$pager['pages'])continue; ?><a class="btn secondary" href="?<?= h(http_build_query(['view'=>$view,'page'=>$target])) ?>"><?= h($label) ?></a><?php endforeach; ?></nav>
<?php endif; ?>
<?php if($need_count>0): ?><p><a class="btn primary" href="reports.php?type=PR">Prepare Purchase Request</a></p><?php endif; ?>
<p>Suggestions only. Nothing is ordered automatically. Verify stock, demand and delivery timing before purchasing.</p>
</section>
<section class="panel"><details><summary><strong>How are these suggestions calculated?</strong></summary>
<p>Available stock excludes expired batches. A batch is flagged when available stock reaches its saved minimum, or is estimated to run out within 14 days. The suggested order restores stock to the larger of twice its saved minimum or the previous 30 days of recorded issues.</p>
<p>Only medicine issued counts as usage. Disposal and physical-count adjustments do not count. The same reorder calculation is used in procurement reports.</p>
<h3>Longer-term usage estimates</h3>
<p>The baseline below uses up to 90 days of recorded issues divided by the available record age to estimate daily usage. Projected 30-day demand = daily usage times 30. Suggested minimum = daily usage times an assumed 14-day delivery lead time. Values are rounded up, with no additional safety stock modeled. These do not change the saved minimum automatically.</p>
<p>These are implementation assumptions, not a forecasting equation specified by the paper. Short, missing or stockout-affected history can mislead the estimates; clinic review is needed.</p>
<?php if($shown): ?><table><thead><tr><th>Medicine / batch</th><th>Issued in observation period</th><th>Observation days</th><th>Projected next 30 days</th><th>Suggested minimum</th><th>Saved minimum</th><th>Limitation</th></tr></thead><tbody><?php foreach($shown as $m):$p=$m['projection']; ?><tr><td><?= h($m['name']) ?><br><?= h($m['batch_number']) ?></td><td><?= (int)$m['used90'] ?> <?= h($m['unit']) ?></td><td><?= (int)$p['days'] ?></td><td><?= $p['next30']===null?'Unavailable':(int)$p['next30'].' '.h($m['unit']) ?></td><td><?= $p['suggested_point']===null?'Needs review':(int)$p['suggested_point'].' '.h($m['unit']) ?></td><td><?= (int)$m['reorder_point'] ?></td><td><?= h($p['quality']) ?></td></tr><?php endforeach; ?></tbody></table><?php endif; ?>
</details></section>
<section class="panel"><details><summary><strong>Medicine usage over the last 6 months</strong></summary><p>Recorded quantities issued across medicine batches. Different medicine units are combined, so this is an activity overview, not a comparison of clinical demand.</p>
<?php foreach($months as $ym=>$units): ?><div class="bar-row"><div class="bar-row-head"><span><?= h(date('F Y',strtotime($ym.'-01'))) ?></span><span><?= (int)$units ?> issued units</span></div><div class="bar-track"><div class="bar-fill" style="width:<?= round(($units/$max_units)*100,1) ?>%"></div></div></div><?php endforeach; ?>
</details></section>
<?php include __DIR__.'/includes/footer.php'; ?>
