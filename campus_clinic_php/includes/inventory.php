<?php
// Additive upgrade: existing movements remain unchanged; old balances stay unknown.
function ensure_inventory_schema(PDO $pdo) {
    $cols=[];foreach($pdo->query('SHOW COLUMNS FROM transactions')->fetchAll() as $c)$cols[$c['Field']]=$c;
    if(strpos($cols['type']['Type'],"'Disposal'")===false) $pdo->exec("ALTER TABLE transactions MODIFY type ENUM('Stock In','Stock Out','Disposal','Adjustment In','Adjustment Out') NOT NULL");
    foreach(['balance_before'=>'INT NULL','balance_after'=>'INT NULL','request_token'=>'VARCHAR(64) NULL'] as $name=>$definition) if(!isset($cols[$name]))$pdo->exec("ALTER TABLE transactions ADD COLUMN $name $definition");
    $indexes=$pdo->query('SHOW INDEX FROM transactions')->fetchAll();$exists=false;foreach($indexes as $i)if($i['Key_name']==='uq_inventory_request')$exists=true;
    if(!$exists)$pdo->exec('ALTER TABLE transactions ADD UNIQUE KEY uq_inventory_request (request_token)');
}
function inventory_integer($value,$min,$max,$label) {
    if(!is_scalar($value) || is_bool($value) || !preg_match('/^[0-9]+$/',(string)$value))throw new InvalidArgumentException("Enter a whole number for $label.");
    $n=filter_var($value,FILTER_VALIDATE_INT);if($n===false || $n<$min || $n>$max)throw new InvalidArgumentException("$label must be between $min and $max.");return $n;
}
function inventory_movement(PDO $pdo,array $input,array $staff) {
    $id=inventory_integer($input['medicine_id'] ?? '',1,PHP_INT_MAX,'Medicine');
    $type=$input['type'] ?? '';if(!is_string($type) || !in_array($type,['Stock In','Stock Out','Disposal','Physical Count'],true))throw new InvalidArgumentException('Choose a valid movement.');
    if($type==='Physical Count' && ($staff['role'] ?? '')!=='admin')throw new InvalidArgumentException('Only an administrator can confirm a stock-count adjustment.');
    $ref=$input['reference_no'] ?? '';$notes=$input['notes'] ?? '';$token=$input['request_token'] ?? '';
    if(!is_string($ref)||mb_strlen($ref)>100||!is_string($notes)||mb_strlen($notes)>1000)throw new InvalidArgumentException('Reference is limited to 100 characters; notes to 1,000.');
    $ref=trim($ref);$notes=trim($notes);
    if(!is_string($token)||!preg_match('/^[a-f0-9]{64}$/',$token))throw new InvalidArgumentException('Refresh the page before recording a movement.');
    if(in_array($type,['Disposal','Physical Count'],true) && $notes==='')throw new InvalidArgumentException('A reason is required for disposal or a stock-count adjustment.');
    $qty=$type==='Physical Count'?inventory_integer($input['actual_count'] ?? '',0,1000000,'Actual count'):inventory_integer($input['quantity'] ?? '',1,100000,'Quantity');
    $expected=$type==='Physical Count'?inventory_integer($input['expected_balance'] ?? '',0,1000000,'Displayed balance'):null;
    $own=!$pdo->inTransaction();if($own)$pdo->beginTransaction();
    try {
        $stmt=$pdo->prepare('SELECT * FROM medicines WHERE id=? FOR UPDATE');$stmt->execute([$id]);$m=$stmt->fetch();if(!$m || !empty($m['archived_at']))throw new InvalidArgumentException('Medicine not found or removed from inventory.');
        $stmt=$pdo->prepare('SELECT id,medicine_id,user_id,balance_after,type,quantity FROM transactions WHERE request_token=?');$stmt->execute([$token]);$old=$stmt->fetch();
        if($old){if((int)$old['medicine_id']!==$id || (int)$old['user_id']!==(int)$staff['id'])throw new InvalidArgumentException('This form has already been used. Refresh the page.');if($own)$pdo->commit();return ['duplicate'=>true,'balance'=>(int)$old['balance_after'],'name'=>$m['name'],'type'=>$old['type'],'quantity'=>(int)$old['quantity'],'batch'=>$m['batch_number'] ?? '', 'unit'=>$m['unit'] ?? 'units'];}
        $before=(int)$m['quantity'];
        if($type==='Physical Count') {
            if($expected!==$before)throw new InvalidArgumentException('Stock changed since this page opened. Refresh, recount and submit again.');
            if($qty===$before)throw new InvalidArgumentException('The count matches the system balance; no adjustment is needed.');
            $after=$qty;$qty=abs($after-$before);$type=$after>$before?'Adjustment In':'Adjustment Out';
        }else{
            if($type==='Stock Out' && $m['expiration_date']<date('Y-m-d'))throw new InvalidArgumentException('Expired medicine cannot be issued. Use Disposal to record its removal.');
            $after=$before+($type==='Stock In'?$qty:-$qty);
            if($after<0)throw new InvalidArgumentException('Not enough stock. Available: '.$before.'.');
            if($after>1000000)throw new InvalidArgumentException('The resulting balance cannot exceed 1,000,000.');
        }
        $pdo->prepare('UPDATE medicines SET quantity=? WHERE id=?')->execute([$after,$id]);
        $pdo->prepare('INSERT INTO transactions(medicine_id,type,quantity,reference_no,notes,user_id,balance_before,balance_after,request_token,transaction_date) VALUES(?,?,?,?,?,?,?,?,?,NOW())')->execute([$id,$type,$qty,$ref,$notes,$staff['id'],$before,$after,$token]);
        if($own)$pdo->commit();return ['duplicate'=>false,'balance'=>$after,'name'=>$m['name'],'type'=>$type,'quantity'=>$qty,'batch'=>$m['batch_number'] ?? '', 'unit'=>$m['unit'] ?? 'units'];
    }catch(Throwable $e){if($own && $pdo->inTransaction())$pdo->rollBack();throw $e;}
}

// Receive into the entered expiry date without changing any existing expiry or balance.
function inventory_receive_by_expiry(PDO $pdo,array $input,array $staff) {
 $sourceId=inventory_integer($input['medicine_id'] ?? '',1,PHP_INT_MAX,'Medicine');$qty=inventory_integer($input['quantity'] ?? '',1,100000,'Quantity');
 $expiry=$input['expiration_date'] ?? ''; $date=is_string($expiry)?DateTimeImmutable::createFromFormat('!Y-m-d',$expiry):false;
 if(!$date||$date->format('Y-m-d')!==$expiry||$expiry<date('Y-m-d'))throw new InvalidArgumentException('Enter a valid expiry date that is not in the past.');
 $token=$input['request_token'] ?? '';if(!is_string($token)||!preg_match('/^[a-f0-9]{64}$/',$token))throw new InvalidArgumentException('Refresh before recording the delivery.');
 $pdo->beginTransaction();try{
  $stmt=$pdo->prepare('SELECT * FROM medicines WHERE id=? FOR UPDATE');$stmt->execute([$sourceId]);$source=$stmt->fetch();if(!$source || !empty($source['archived_at']))throw new InvalidArgumentException('Medicine not found or removed from inventory.');
  $stmt=$pdo->prepare('SELECT t.*,m.reorder_group_id,m.name,m.batch_number,m.unit,m.expiration_date,m.generic_name,m.brand,m.description,m.category FROM transactions t JOIN medicines m ON m.id=t.medicine_id WHERE t.request_token=?');$stmt->execute([$token]);$old=$stmt->fetch();
  if($old){$same=(int)$old['medicine_id']===$sourceId||(!empty($source['reorder_group_id']) && (int)$old['reorder_group_id']===(int)$source['reorder_group_id']);if(!$same||(int)$old['user_id']!==(int)$staff['id']||$old['type']!=='Stock In'||(int)$old['quantity']!==$qty||$old['expiration_date']!==$expiry)throw new InvalidArgumentException('This form was already used. Refresh before another delivery.');$pdo->commit();return ['duplicate'=>true,'type'=>'Stock In','name'=>$old['name'],'batch'=>$old['batch_number'],'unit'=>$old['unit'],'quantity'=>(int)$old['quantity'],'balance'=>(int)$old['balance_after'],'expiration_date'=>$expiry];}
  $group=$source['reorder_group_id'];$members=[$source];
  if($group){$stmt=$pdo->prepare('SELECT * FROM medicines WHERE reorder_group_id=? AND archived_at IS NULL ORDER BY id FOR UPDATE');$stmt->execute([$group]);$members=$stmt->fetchAll();foreach($members as $member)if(medicine_identity($member)!==medicine_identity($source))throw new InvalidArgumentException('Medicine group details need administrator review before receiving.');}
  $target=$source['expiration_date']===$expiry?$source:null;
  if(!$target)foreach($members as $member)if($member['expiration_date']===$expiry && trim((string)$member['batch_number'])===''){$target=$member;break;}
  if(!$target){
   if(!$group){$pdo->prepare('INSERT INTO medicine_reorder_groups(reorder_point) VALUES(?)')->execute([$source['reorder_point']]);$group=(int)$pdo->lastInsertId();$pdo->prepare('UPDATE medicines SET reorder_group_id=? WHERE id=?')->execute([$group,$sourceId]);}
   $stmt=$pdo->prepare('SELECT reorder_point FROM medicine_reorder_groups WHERE id=?');$stmt->execute([$group]);$point=(int)$stmt->fetchColumn();
   $pdo->prepare('INSERT INTO medicines(name,generic_name,brand,description,category,unit,supplier,batch_number,quantity,reorder_point,expiration_date,unit_cost,reorder_group_id) VALUES(?,?,?,?,?,?,?,?,0,?,?,?,?)')->execute([$source['name'],$source['generic_name'] ?? '',$source['brand'] ?? '',$source['description'] ?? '',$source['category'],$source['unit'],$source['supplier'],'',$point,$expiry,$source['unit_cost'],$group]);$target=['id'=>(int)$pdo->lastInsertId()];
  }
  $input['medicine_id']=$target['id'];$input['type']='Stock In';$result=inventory_movement($pdo,$input,$staff);$result['expiration_date']=$expiry;$result['medicine_id']=(int)$target['id'];$pdo->commit();return $result;
 }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}
