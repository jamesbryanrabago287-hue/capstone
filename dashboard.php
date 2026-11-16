<?php
require_once __DIR__ . "/includes/auth.php";
require_login();
$page_title = "Dashboard";

$stockRows=clinic_stock_rows($pdo);$products=medicine_listing_groups($stockRows);
$s=['total'=>count($products),'low'=>0,'medium'=>0,'high'=>0,'expiring'=>0];
foreach($products as $product){$level=stock_level_label($product['usable_total'],$product['reorder_point']);$s[strtolower($level['label'])]++;}
foreach($stockRows as $row)if((int)$row['quantity']>0 && $row['expiration_date']<=date('Y-m-d',strtotime('+'.expiry_warning_days().' days')))$s['expiring']++;
$students = (int)$pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();

$recent = $pdo->query(
    "SELECT t.*, m.name AS medicine_name
     FROM transactions t JOIN medicines m ON m.id = t.medicine_id
     ORDER BY t.transaction_date DESC, t.id DESC LIMIT 5"
)->fetchAll();

$issues=[];foreach($pdo->query("SELECT medicine_id,SUM(quantity) AS used FROM transactions WHERE type='Stock Out' GROUP BY medicine_id")->fetchAll() as $row)$issues[(int)$row['medicine_id']]=(int)$row['used'];
$usage=[];foreach($products as $product){$used=0;foreach($product['batches'] as $batch)$used+=$issues[(int)$batch['id']] ?? 0;$usage[]=['name'=>$product['name'],'used'=>$used];}usort($usage,function($a,$b){return $b['used']<=>$a['used'];});$usage=array_slice($usage,0,6);
$max_used = 1;
foreach ($usage as $u) { $max_used = max($max_used, (int)$u["used"]); }

$attention=medicine_attention_rows($stockRows);$attentionTotal=count($attention);$attention=array_slice($attention,0,5);

include __DIR__ . "/includes/header.php";
render_flash();

?>
<div class="cards dashboard-shortcuts">
    <a class="card dashboard-shortcut" href="medicines.php"><span>Medicine Items</span><strong><?= (int)$s['total'] ?></strong><small>View medicine list <span aria-hidden="true">&rarr;</span></small></a>
    <a class="card danger-card dashboard-shortcut" href="alerts.php?filter=low"><span>Low Stock</span><strong><?= (int)$s['low'] ?></strong><small>View low-stock medicines <span aria-hidden="true">&rarr;</span></small></a>
    <a class="card warning dashboard-shortcut" href="alerts.php?filter=expiry"><span>Expiry alerts within <?= expiry_warning_days() ?> days</span><strong><?= (int)$s['expiring'] ?></strong><small>View expiry alerts <span aria-hidden="true">&rarr;</span></small></a>
    <a class="card dashboard-shortcut" href="patients.php"><span>Patients</span><strong><?= $students ?></strong><small>View patient records <span aria-hidden="true">&rarr;</span></small></a>
</div>

<div class="grid-2">
<section class="panel chart-panel">
<h2>Most Used Medicines (Stock Out)</h2>
<div class="chart-wrap">
<svg viewBox="0 0 520 220" role="img" aria-label="Most used medicines chart">
<?php
$bar_width = 54;
$gap = 16;
$left = 38;
$bottom = 180;
foreach ($usage as $i => $item) {
    $bar_height = ((int)$item["used"] / $max_used) * 120;
    $x = $left + ($i * ($bar_width + $gap));
    $y = $bottom - $bar_height;
    echo '<rect x="' . $x . '" y="' . $y . '" width="' . $bar_width . '" height="' . $bar_height . '" rx="8" fill="#1b52b3" opacity="0.9"></rect>';
    echo '<text x="' . ($x + ($bar_width / 2)) . '" y="' . ($bottom + 18) . '" text-anchor="middle" font-size="10" fill="#526582">' . h(mb_substr($item["name"], 0, 8)) . '</text>';
    echo '<text x="' . ($x + ($bar_width / 2)) . '" y="' . ($y - 8) . '" text-anchor="middle" font-size="10" fill="#0a2352">' . (int)$item["used"] . '</text>';
}
?>
</svg>
</div>
</section>

<section class="panel" aria-labelledby="attention-title">
<div class="panel-head"><h2 id="attention-title">Medicines needing attention</h2><a class="btn small secondary" href="alerts.php">View all alerts</a></div>
<?php if(!$attention): ?><p>No medicines need attention right now.</p><?php else: ?>
<table>
<thead><tr><th>Medicine</th><th>Available stock</th><th>Reason</th><th>Action</th></tr></thead>
<tbody>
<?php foreach($attention as $item):
 $expired=$item['exp'] && $item['exp']['label']==='Expired';
 $action=$expired?'Dispose stock':($item['low']?'Add stock':'View stock');
 $link=$expired||$item['low']?'transactions.php?'.http_build_query(['medicine_id'=>$item['id'],'movement'=>$expired?'Disposal':'Stock In']).'#record-movement':'medicines.php?'.http_build_query(['q'=>$item['name']]);
?>
<tr>
<td><strong><?= h($item['name']) ?></strong><?php if($item['exp']): ?><br><small>Expiry: <?= h(date('M d, Y',strtotime($item['expiration_date']))) ?></small><?php endif; ?></td>
<td><?= (int)$item['group_usable_qty'] ?> <?= h($item['unit']) ?></td>
<td><?php if($item['exp']): ?><span class="badge <?= h($item['exp']['class']) ?>"><?= h($item['exp']['label']) ?></span><br><small><?= (int)$item['quantity'] ?> <?= h($item['unit']) ?> <?= $expired?'expired':'near expiry' ?></small><?php endif; ?>
<?php if($item['low']): ?><span class="badge low"><?= (int)$item['group_usable_qty']===0?'Out of stock':'Low stock' ?></span><?php endif; ?></td>
<td><a class="btn small <?= $expired?'danger':'secondary' ?>" href="<?= h($link) ?>"><?= h($action) ?></a></td>
</tr>
<?php endforeach; ?>
</tbody></table>
<?php if($attentionTotal>5): ?><p class="muted">Showing 5 of <?= $attentionTotal ?> alerts.</p><?php endif; ?>
<?php endif; ?>
</section>
</div>

<section class="panel">
<div class="panel-head"><h2>Recent Transactions</h2><a class="btn small secondary" href="transactions.php">View all</a></div>
<table>
<thead><tr><th>Date</th><th>Medicine</th><th>Type</th><th>Qty</th></tr></thead>
<tbody>
<?php foreach ($recent as $r): ?>
<tr>
    <td><?= h($r["transaction_date"]) ?></td>
    <td><?= h($r["medicine_name"]) ?></td>
    <td><span class="badge <?= $r["type"] === "Stock In" ? "success" : "warning" ?>"><?= h($r["type"]) ?></span></td>
    <td><?= (int)$r["quantity"] ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</section>
<?php include __DIR__ . "/includes/footer.php"; ?>