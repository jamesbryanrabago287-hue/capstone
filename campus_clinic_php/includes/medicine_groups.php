<?php
function ensure_medicine_groups(PDO $pdo) {
 if(!$pdo->query("SHOW COLUMNS FROM medicines LIKE 'archived_at'")->fetch())$pdo->exec('ALTER TABLE medicines ADD COLUMN archived_at DATETIME NULL DEFAULT NULL');
 $pdo->exec('CREATE TABLE IF NOT EXISTS medicine_reorder_groups (id INT AUTO_INCREMENT PRIMARY KEY,reorder_point INT NOT NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
 if(!$pdo->query("SHOW COLUMNS FROM medicines LIKE 'reorder_group_id'")->fetch())$pdo->exec('ALTER TABLE medicines ADD COLUMN reorder_group_id INT NULL, ADD INDEX idx_medicine_reorder_group (reorder_group_id)');
}
function medicine_identity(array $m) {
 $values=[];foreach(['name','generic_name','brand','description','category','unit'] as $key)$values[]=mb_strtolower(preg_replace('/\s+/u',' ',trim((string)($m[$key] ?? ''))),'UTF-8');return json_encode($values);
}
function group_medicine_batches(PDO $pdo,array $ids,$point,$confirmed) {
 if(!$confirmed)throw new InvalidArgumentException('Confirm the batches contain the same medicine, strength, form and unit.');
 if(count($ids)<2 || count($ids)>1000)throw new InvalidArgumentException('Choose at least two batches (up to 1,000).');
 $clean=[];foreach($ids as $id){$n=filter_var($id,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);if(!$n)throw new InvalidArgumentException('Choose valid medicine records.');$clean[]=$n;}$clean=array_values(array_unique($clean));if(count($clean)<2)throw new InvalidArgumentException('Choose two different batches.');sort($clean);
 $point=filter_var($point,FILTER_VALIDATE_INT,['options'=>['min_range'=>0,'max_range'=>1000000]]);if($point===false)throw new InvalidArgumentException('Shared reorder point must be a whole number from 0 to 1,000,000.');
 $pdo->beginTransaction();try{
  $marks=implode(',',array_fill(0,count($clean),'?'));$stmt=$pdo->prepare('SELECT * FROM medicines WHERE archived_at IS NULL AND id IN ('.$marks.') ORDER BY id FOR UPDATE');$stmt->execute($clean);$rows=$stmt->fetchAll();if(count($rows)!==count($clean))throw new InvalidArgumentException('One of the batches no longer exists. Refresh.');
  $existing=[];foreach($rows as $r)if($r['reorder_group_id'])$existing[(int)$r['reorder_group_id']]=true;
  // Require every member of an existing group; never silently split one.
  if($existing){$stmt=$pdo->prepare('SELECT * FROM medicines WHERE archived_at IS NULL AND reorder_group_id IN ('.implode(',',array_fill(0,count($existing),'?')).') ORDER BY id FOR UPDATE');$stmt->execute(array_keys($existing));foreach($stmt->fetchAll() as $member)if(!in_array((int)$member['id'],$clean,true))throw new InvalidArgumentException('Select every batch in the existing group before combining or updating it.');}
  $identity=medicine_identity($rows[0]);foreach($rows as $r)if(medicine_identity($r)!==$identity)throw new InvalidArgumentException('Names, generic names, brands, descriptions, categories and units must match. Review differences before grouping.');
  $pdo->prepare('INSERT INTO medicine_reorder_groups(reorder_point) VALUES(?)')->execute([$point]);$group=(int)$pdo->lastInsertId();
  $pdo->prepare('UPDATE medicines SET reorder_group_id=?,reorder_point=? WHERE id IN ('.$marks.')')->execute(array_merge([$group,$point],$clean));$pdo->commit();return $group;
 }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}
function aggregate_reorder_batches(array $rows) {
 $groups=[];
 foreach($rows as $m){
  // Identity is checked again so a later metadata edit cannot mix unlike medicines.
  $key=!empty($m['reorder_group_id'])?'group:'.$m['reorder_group_id'].':'.medicine_identity($m):'batch:'.$m['id'];
  if(!isset($groups[$key])){$g=$m;$g['batch_ids']=[];$g['batch_count']=0;$g['quantity']=0;$g['usable_qty']=0;$g['expired_qty']=0;$g['used30']=0;$g['used90']=0;$g['record_age_days']=1;$g['reorder_point']=(int)($m['shared_reorder_point'] ?? $m['reorder_point']);$groups[$key]=$g;}
  $g=&$groups[$key];$g['batch_ids'][]=(int)$m['id'];$g['batch_count']++;$g['quantity']+=(int)$m['quantity'];$g['usable_qty']+=(int)$m['usable_qty'];$g['expired_qty']+=!empty($m['is_expired'])?(int)$m['quantity']:0;$g['used30']+=(int)$m['used30'];$g['used90']+=(int)$m['used90'];$g['record_age_days']=max($g['record_age_days'],(int)$m['record_age_days']);
  // Use a current batch for indicative cost; do not add or average unit prices.
  if(!empty($g['is_expired']) && empty($m['is_expired'])){foreach(['supplier','unit_cost','expiration_date'] as $field)$g[$field]=$m[$field];$g['is_expired']=false;}
  unset($g);
 }
 foreach($groups as &$g){$g['is_expired']=$g['expired_qty']>0;$g['batch_number']=$g['batch_count']>1?$g['batch_count'].' linked batches':$g['batch_number'];$g['advice']=reorder_advice($g['usable_qty'],$g['reorder_point'],$g['used30']);$g['projection']=medicine_usage_projection($g['used90'],$g['record_age_days']);}unset($g);
 return array_values($groups);
}

function add_medicine_batch(PDO $pdo,$sourceId,array $input,array $staff) {
 $sourceId=filter_var($sourceId,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);if(!$sourceId)throw new InvalidArgumentException('Choose the medicine to add a batch to.');
 $quantity=filter_var($input['quantity'] ?? '',FILTER_VALIDATE_INT,['options'=>['min_range'=>0,'max_range'=>1000000]]);if($quantity===false)throw new InvalidArgumentException('Enter a whole initial quantity from 0 to 1,000,000.');
 $batch=$input['batch_number'] ?? '';$supplier=$input['supplier'] ?? '';$expiry=$input['expiration_date'] ?? '';$cost=$input['unit_cost'] ?? '';
 if(!is_string($batch)||!is_string($supplier)||mb_strlen($batch)>100||mb_strlen($supplier)>150)throw new InvalidArgumentException('Check the batch number and supplier lengths.');
 $date=is_string($expiry)?DateTimeImmutable::createFromFormat('!Y-m-d',$expiry):false;if(!$date||$date->format('Y-m-d')!==$expiry)throw new InvalidArgumentException('Enter a valid expiry date.');
 if(!is_scalar($cost)||!is_numeric($cost)||(float)$cost<0||(float)$cost>1000000)throw new InvalidArgumentException('Enter a valid non-negative unit cost.');
 $pdo->beginTransaction();try{
  $stmt=$pdo->prepare('SELECT * FROM medicines WHERE id=? FOR UPDATE');$stmt->execute([$sourceId]);$base=$stmt->fetch();if(!$base)throw new InvalidArgumentException('The source medicine no longer exists.');
  $group=$base['reorder_group_id'];$point=(int)$base['reorder_point'];
  if($group){$stmt=$pdo->prepare('SELECT * FROM medicines WHERE reorder_group_id=? ORDER BY id FOR UPDATE');$stmt->execute([$group]);foreach($stmt->fetchAll() as $member)if(medicine_identity($member)!==medicine_identity($base))throw new InvalidArgumentException('This group has inconsistent medicine details. Ask an administrator to review it.');$stmt=$pdo->prepare('SELECT reorder_point FROM medicine_reorder_groups WHERE id=?');$stmt->execute([$group]);$point=(int)$stmt->fetchColumn();}
  else{$pdo->prepare('INSERT INTO medicine_reorder_groups(reorder_point) VALUES(?)')->execute([$point]);$group=(int)$pdo->lastInsertId();$pdo->prepare('UPDATE medicines SET reorder_group_id=? WHERE id=?')->execute([$group,$sourceId]);}
  if(trim($batch)!==''){$stmt=$pdo->prepare('SELECT id FROM medicines WHERE reorder_group_id=? AND batch_number=?');$stmt->execute([$group,trim($batch)]);if($stmt->fetch())throw new InvalidArgumentException('That batch already exists for this medicine. Use Receive stock for another delivery into the same batch.');}
  $pdo->prepare('INSERT INTO medicines(name,generic_name,brand,description,category,unit,supplier,batch_number,quantity,reorder_point,expiration_date,unit_cost,reorder_group_id) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?)')->execute([$base['name'],$base['generic_name'] ?? '',$base['brand'] ?? '',$base['description'] ?? '',$base['category'],$base['unit'],trim($supplier),trim($batch),$quantity,$point,$expiry,(float)$cost,$group]);$id=(int)$pdo->lastInsertId();
  if($quantity>0)$pdo->prepare("INSERT INTO transactions(medicine_id,type,quantity,reference_no,notes,user_id) VALUES(?,'Stock In',?,'','Initial stock for new batch',?)")->execute([$id,$quantity,$staff['id']]);
  $pdo->commit();return $id;
 }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}
function medicine_listing_groups(array $batches,$search='',$sort='az') {
 $groups=[];$today=date('Y-m-d');
 foreach($batches as $m){$key=!empty($m['reorder_group_id'])?'g:'.$m['reorder_group_id'].':'.medicine_identity($m):'b:'.$m['id'];if(!isset($groups[$key])){$groups[$key]=$m;$groups[$key]['batches']=[];$groups[$key]['usable_total']=0;$groups[$key]['expired_total']=0;$groups[$key]['next_expiry']=null;$groups[$key]['first_added']=$m['created_at'];$groups[$key]['last_added']=$m['created_at'];}
  $g=&$groups[$key];$g['batches'][]=$m;if($m['expiration_date']>=$today){$g['usable_total']+=(int)$m['quantity'];if((int)$m['quantity']>0 && ($g['next_expiry']===null||$m['expiration_date']<$g['next_expiry']))$g['next_expiry']=$m['expiration_date'];}else $g['expired_total']+=(int)$m['quantity'];$g['first_added']=min($g['first_added'],$m['created_at']);$g['last_added']=max($g['last_added'],$m['created_at']);unset($g);
 }
 $groups=array_values($groups);if($search!=='')$groups=array_values(array_filter($groups,function($g)use($search){foreach($g['batches'] as $m)foreach(['name','generic_name','brand','description','category','batch_number','supplier'] as $key)if(mb_stripos((string)($m[$key] ?? ''),$search)!==false)return true;return false;}));
 usort($groups,function($a,$b)use($sort){switch($sort){case 'za':$c=strcasecmp($b['name'],$a['name']);break;case 'newest':$c=strcmp($b['last_added'],$a['last_added']);break;case 'oldest':$c=strcmp($a['first_added'],$b['first_added']);break;case 'expiry':$c=strcmp($a['next_expiry'] ?? '9999',$b['next_expiry'] ?? '9999');break;default:$c=strcasecmp($a['name'],$b['name']);}return $c?:((int)$a['id']<=>(int)$b['id']);});return $groups;
}

function medicine_attention_rows(array $batches) {
 $alerts=[];$today=date('Y-m-d');$soon=date('Y-m-d',strtotime('+'.expiry_warning_days().' days'));
 foreach(medicine_listing_groups($batches) as $product){
  $low=$product['usable_total']<=(int)$product['reorder_point'];$representative=$product['id'];
  foreach($product['batches'] as $batch)if($batch['expiration_date']>=$today){$representative=$batch['id'];break;}
  foreach($product['batches'] as $batch){
   $expiryAttention=(int)$batch['quantity']>0 && $batch['expiration_date']<=$soon;
   $lowAttention=$low && (int)$batch['id']===(int)$representative;
   if(!$expiryAttention&&!$lowAttention)continue;
   $batch['exp']=$expiryAttention?expiry_status($batch['expiration_date']):null;$batch['low']=$lowAttention;
   $batch['reorder_point']=$product['reorder_point'];$batch['group_usable_qty']=$product['usable_total'];$batch['group_batch_count']=count($product['batches']);
   $batch['urgency']=$expiryAttention?($batch['expiration_date']<$today?0:1):2;$alerts[]=$batch;
  }
 }
 usort($alerts,function($a,$b){return ($a['urgency']<=>$b['urgency'])?:strcmp($a['expiration_date'],$b['expiration_date'])?:((int)$a['id']<=>(int)$b['id']);});return $alerts;
}
function clinic_stock_rows(PDO $pdo) {
 ensure_medicine_groups($pdo);
 return $pdo->query('SELECT m.*,COALESCE(g.reorder_point,m.reorder_point) AS reorder_point FROM medicines m LEFT JOIN medicine_reorder_groups g ON g.id=m.reorder_group_id WHERE m.archived_at IS NULL ORDER BY m.id')->fetchAll();
}

function delete_empty_medicine(PDO $pdo,int $id,bool $admin): string {
 if(!$admin)throw new InvalidArgumentException('Only an administrator can delete medicines.');
 $pdo->beginTransaction();
 try {
  $stmt=$pdo->prepare('SELECT * FROM medicines WHERE id=? AND archived_at IS NULL FOR UPDATE');$stmt->execute([$id]);$base=$stmt->fetch();
  if(!$base)throw new InvalidArgumentException('Medicine not found or already removed.');
  $rows=[$base];
  if(!empty($base['reorder_group_id'])){
   $stmt=$pdo->prepare('SELECT * FROM medicines WHERE reorder_group_id=? AND archived_at IS NULL ORDER BY id FOR UPDATE');$stmt->execute([$base['reorder_group_id']]);$rows=$stmt->fetchAll();
  }
  foreach($rows as $row)if((int)$row['quantity']!==0)throw new InvalidArgumentException('This medicine still has stock. Resolve its remaining stock in Manage Inventory before deleting it.');
  $kept=false;
  foreach($rows as $row){
   $stmt=$pdo->prepare('SELECT COUNT(*) FROM transactions WHERE medicine_id=?');$stmt->execute([$row['id']]);
   if((int)$stmt->fetchColumn()>0){$pdo->prepare('UPDATE medicines SET archived_at=NOW() WHERE id=?')->execute([$row['id']]);$kept=true;}
   else $pdo->prepare('DELETE FROM medicines WHERE id=?')->execute([$row['id']]);
  }
  $pdo->commit();return $kept?'Medicine removed from active inventory. Its transaction history has been preserved.':'Medicine deleted.';
 }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}

/** Shared medicine identity is edited together; delivery details stay per record. */
function update_medicine_details(PDO $pdo,int $id,array $d): void {
 $pdo->beginTransaction();
 try {
  $stmt=$pdo->prepare('SELECT * FROM medicines WHERE id=? AND archived_at IS NULL FOR UPDATE');$stmt->execute([$id]);$base=$stmt->fetch();
  if(!$base)throw new InvalidArgumentException('Medicine not found or removed from inventory. Refresh the medicine list.');
  $ids=[$id];
  if(!empty($base['reorder_group_id'])){
   $stmt=$pdo->prepare('SELECT * FROM medicines WHERE reorder_group_id=? AND archived_at IS NULL ORDER BY id FOR UPDATE');$stmt->execute([$base['reorder_group_id']]);$members=$stmt->fetchAll();
   foreach($members as $member)if(medicine_identity($member)!==medicine_identity($base))throw new InvalidArgumentException('This medicine group has inconsistent details. Ask an administrator to review it before editing.');
   $ids=array_column($members,'id');
  }
  $stmt=$pdo->prepare('UPDATE medicines SET name=?,generic_name=?,brand=?,description=?,category=?,unit=?,reorder_point=? WHERE id IN ('.implode(',',array_fill(0,count($ids),'?')).')');
  $stmt->execute(array_merge([$d['name'],$d['generic_name'],$d['brand'],$d['description'],$d['category'],$d['unit'],$d['reorder_point']],$ids));
  $pdo->prepare('UPDATE medicines SET supplier=?,batch_number=?,expiration_date=?,unit_cost=? WHERE id=?')->execute([$d['supplier'],$d['batch_number'],$d['expiration_date'],$d['unit_cost'],$id]);
  if(!empty($base['reorder_group_id']))$pdo->prepare('UPDATE medicine_reorder_groups SET reorder_point=? WHERE id=?')->execute([$d['reorder_point'],$base['reorder_group_id']]);
  $pdo->commit();
 }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}
