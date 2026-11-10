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

// Medicines closest to (or below) their reorder point
$items=$products;usort($items,function($a,$b){return (($a['usable_total']-$a['reorder_point'])<=>($b['usable_total']-$b['reorder_point']))?:strcasecmp($a['name'],$b['name']);});$items=array_slice($items,0,5);

$levels = [
    ["label" => "High",   "value" => (int)$s["high"],   "color" => "#1b52b3"],
    ["label" => "Medium", "value" => (int)$s["medium"], "color" => "#f2b705"],
    ["label" => "Low",    "value" => (int)$s["low"],    "color" => "#0a2352"],
];
$total_levels = max(1, array_sum(array_column($levels, "value")));

include __DIR__ . "/includes/header.php";
render_flash();

?>
<div class="cards">
    <div class="card"><span>Medicine Items</span><strong><?= (int)$s["total"] ?></strong></div>
    <div class="card danger-card"><span>Low Stock</span><strong><?= (int)$s["low"] ?></strong></div>
    <div class="card warning"><span>Expiry alerts within <?= expiry_warning_days() ?> days</span><strong><?= (int)$s["expiring"] ?></strong></div>
    <div class="card"><span>Students</span><strong><?= $students ?></strong></div>
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

<section class="panel chart-panel">
<h2>Stock Level Distribution</h2>
<div class="chart-wrap">
<?php foreach ($levels as $level): $pct = ($level["value"] / $total_levels) * 100; ?>
    <div class="bar-row">
        <div class="bar-row-head"><span><?= h($level["label"]) ?> Stock</span><span><?= (int)$level["value"] ?></span></div>
        <div class="bar-track"><div class="bar-fill" style="width:<?= round($pct, 1) ?>%;background:<?= h($level["color"]) ?>"></div></div>
    </div>
<?php endforeach; ?>
</div>
</section>
</div>

<div class="grid-2">
<section class="panel">
<div class="panel-head"><h2>Closest to Reorder Point</h2><a class="btn small secondary" href="alerts.php">All alerts</a></div>
<table>
<thead><tr><th>Medicine</th><th>Usable stock</th><th>Minimum</th><th>Status</th></tr></thead>
<tbody>
<?php foreach ($items as $m): $status = stock_level_label($m["usable_total"], $m["reorder_point"]); ?>
<tr>
    <td><?= h($m["name"]) ?></td>
    <td><?= (int)$m["usable_total"] ?></td>
    <td><?= (int)$m["reorder_point"] ?></td>
    <td><span class="badge <?= h($status["class"]) ?>"><?= h($status["label"]) ?> Stock</span></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</section>

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
</div>
<?php include __DIR__ . "/includes/footer.php"; ?>