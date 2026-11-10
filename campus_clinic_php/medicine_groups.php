<?php
require_once __DIR__.'/includes/auth.php';require_admin();ensure_medicine_groups($pdo);header('Cache-Control: no-store');
$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 verify_csrf();
 try{$ids=$_POST['batch_ids'] ?? [];if(!is_array($ids))throw new InvalidArgumentException('Choose medicine batches.');$group=group_medicine_batches($pdo,$ids,$_POST['reorder_point'] ?? '',($_POST['confirmed'] ?? '')==='yes');record_activity($_SESSION['user']['id'],$_SESSION['user']['username'],'group_medicine_batches','Reorder group #'.$group);flash('success','Batches linked. Decision Support and procurement reports now use their combined usable stock and usage.');redirect('medicine_groups.php');}
 catch(InvalidArgumentException $e){$error=$e->getMessage();}catch(Throwable $e){error_log('Batch grouping failed: '.get_class($e));$error='Could not link the batches. Please retry.';}
}
$search=is_string($_GET['q'] ?? null)?mb_substr(trim($_GET['q']),0,100):'';
$params=[];$where=' WHERE m.archived_at IS NULL';if($search!==''){$like='%'.str_replace(['!','%','_'],['!!','!%','!_'],$search).'%';$where=" WHERE m.archived_at IS NULL AND (m.name LIKE ? ESCAPE '!' OR m.batch_number LIKE ? ESCAPE '!')";$params=[$like,$like];}
$stmt=$pdo->prepare('SELECT m.*,COALESCE(g.reorder_point,m.reorder_point) AS current_point FROM medicines m LEFT JOIN medicine_reorder_groups g ON g.id=m.reorder_group_id'.$where.' ORDER BY m.name,m.unit,m.id LIMIT 100');$stmt->execute($params);$batches=$stmt->fetchAll();
$page_title='Fix Duplicate Records';include __DIR__.'/includes/header.php';render_flash();
?>
<p><a href="medicines.php">Back to Medicines</a></p>
<section class="panel"><h2>Combine confirmed duplicate medicine records</h2><p>Administrator maintenance for existing records only. Normal receiving does not require this step.</p><p>Select batches containing the same medicine, strength, dosage form and stock unit. Different expiry dates are allowed. Names, generic names, brands, descriptions, categories and units must match; similar names are never linked automatically.</p><p>This combines planning only. Each batch keeps its own expiry, quantity, issue/disposal movements and history. A shared reorder point is applied to the group.</p><form method="get"><label>Find the medicine or batch<input name="q" value="<?= h($search) ?>" maxlength="100" placeholder="Type the exact medicine name"></label><button type="submit">Search</button></form></section>
<?php if($error): ?><p class="alert danger" role="alert"><?= h($error) ?></p><?php endif; ?>
<section class="panel"><h2>Confirm batches to link</h2><p>Showing up to 100 matching batch records. Include every member when updating an existing group.</p><form method="post"><?= csrf_field() ?>
<table><thead><tr><th>Select</th><th>Medicine</th><th>Batch</th><th>Unit</th><th>Expiry</th><th>Quantity</th><th>Current minimum</th><th>Reorder group</th></tr></thead><tbody><?php foreach($batches as $m): ?><tr><td><input type="checkbox" name="batch_ids[]" value="<?= (int)$m['id'] ?>" aria-label="Select <?= h($m['name'].' batch '.($m['batch_number'] ?: $m['id'])) ?>"></td><td><?= h($m['name']) ?><br><small><?= h(($m['generic_name'] ?? '').' '.($m['brand'] ?? '').' '.($m['description'] ?? '')) ?></small></td><td><?= h($m['batch_number'] ?: 'Not recorded') ?></td><td><?= h($m['unit']) ?></td><td><?= h($m['expiration_date']) ?></td><td><?= (int)$m['quantity'] ?></td><td><?= (int)$m['current_point'] ?></td><td><?= $m['reorder_group_id']?'Group #'.(int)$m['reorder_group_id']:'Separate batch' ?></td></tr><?php endforeach; ?></tbody></table>
<label>Shared reorder point<input type="number" name="reorder_point" min="0" max="1000000" required placeholder="Minimum usable stock for the whole medicine"></label>
<label><input type="checkbox" name="confirmed" value="yes" required> I verified that all selected batches contain the same medicine, strength, form and unit.</label><p><button type="submit">Link selected batches</button></p>
</form></section>
<?php include __DIR__.'/includes/footer.php'; ?>
