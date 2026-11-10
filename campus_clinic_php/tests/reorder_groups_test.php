<?php
require __DIR__.'/../includes/auth.php';
set_exception_handler(function(Throwable $e){fwrite(STDERR,$e->getMessage()."\n");exit(1);});
function groupcheck($ok,$label){if(!$ok)throw new RuntimeException('FAIL: '.$label);echo 'PASS: '.$label."\n";}
$pdo->exec("CREATE TEMPORARY TABLE medicine_reorder_groups(id INT AUTO_INCREMENT PRIMARY KEY,reorder_point INT,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB");
$pdo->exec("CREATE TEMPORARY TABLE medicines(id INT AUTO_INCREMENT PRIMARY KEY,name VARCHAR(150),generic_name VARCHAR(150) DEFAULT '',brand VARCHAR(150) DEFAULT '',description VARCHAR(255) DEFAULT '',category VARCHAR(100),supplier VARCHAR(150),unit VARCHAR(50),unit_cost DECIMAL(12,2),quantity INT,reorder_point INT,expiration_date DATE,batch_number VARCHAR(100),created_at DATETIME,reorder_group_id INT NULL,KEY idx_medicine_reorder_group(reorder_group_id)) ENGINE=InnoDB");
$pdo->exec("CREATE TEMPORARY TABLE transactions(id INT AUTO_INCREMENT PRIMARY KEY,medicine_id INT,type VARCHAR(30),quantity INT,reference_no VARCHAR(100),notes TEXT,user_id INT,transaction_date TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB");
$pdo->exec("INSERT INTO medicines(id,name,category,unit,unit_cost,quantity,reorder_point,expiration_date,batch_number,created_at) VALUES(1,'Fictional 500mg','Test','tablet',2,0,10,'2099-01-01','A',DATE_SUB(NOW(),INTERVAL 100 DAY)),(2,'Fictional 500mg','Test','tablet',3,100,10,'2099-01-01','B',DATE_SUB(NOW(),INTERVAL 100 DAY)),(3,'Fictional 250mg','Test','tablet',1,10,10,'2099-01-01','C',NOW())");
$before=$pdo->query('SELECT id,quantity FROM medicines ORDER BY id')->fetchAll();
$rows=reorder_list();groupcheck(count($rows)===3,'similar or identical names are not merged without confirmation');
$rejected=false;try{group_medicine_batches($pdo,[1,2],10,false);}catch(InvalidArgumentException $e){$rejected=true;}groupcheck($rejected,'explicit confirmation required');
$rejected=false;try{group_medicine_batches($pdo,[1,3],10,true);}catch(InvalidArgumentException $e){$rejected=true;}groupcheck($rejected,'different strengths are rejected');
$group=group_medicine_batches($pdo,[1,2],10,true);$rows=reorder_list();$found=null;foreach($rows as $r)if(count($r['batch_ids'])===2)$found=$r;
groupcheck(count($rows)===2 && $found['usable_qty']===100 && !$found['advice']['needs'],'0 + 100 usable units evaluated once as 100, without false reorder');
groupcheck($before===$pdo->query('SELECT id,quantity FROM medicines ORDER BY id')->fetchAll(),'grouping preserves every batch quantity');
$rejected=false;try{group_medicine_batches($pdo,[1,3],10,true);}catch(InvalidArgumentException $e){$rejected=true;}groupcheck($rejected,'partial existing groups cannot be silently split');
$pdo->exec("UPDATE medicines SET expiration_date='2000-01-01' WHERE id=2");$rows=reorder_list();foreach($rows as $r)if(count($r['batch_ids'])===2)$found=$r;groupcheck($found['usable_qty']===0 && $found['expired_qty']===100 && $found['advice']['needs'],'expired stock excluded from group availability');
$pdo->exec("UPDATE medicines SET expiration_date='2099-01-01',quantity=5 WHERE id=2");
$pdo->exec("INSERT INTO transactions(medicine_id,type,quantity,transaction_date) VALUES(1,'Stock Out',6,NOW()),(2,'Stock Out',9,NOW()),(2,'Disposal',80,NOW()),(2,'Adjustment Out',10,NOW())");
$rows=reorder_list();foreach($rows as $r)if(count($r['batch_ids'])===2)$found=$r;groupcheck($found['used30']===15 && $found['used90']===15 && $found['advice']['order_qty']===15,'combined issues drive one recommendation; disposal and adjustments excluded');
$pdo->exec("UPDATE medicines SET unit='capsule' WHERE id=2");groupcheck(count(reorder_list())===3,'later incompatible metadata edits cannot mix units');
echo "Combined reorder checks passed with temporary tables only.\n";

$pdo->exec("UPDATE medicines SET unit='tablet' WHERE id=2");
$new=add_medicine_batch($pdo,1,['quantity'=>30,'batch_number'=>'NEW','supplier'=>'Fictional','expiration_date'=>'2050-01-01','unit_cost'=>'2.50'],['id'=>1]);
$rows=$pdo->query('SELECT * FROM medicines ORDER BY id')->fetchAll();$listing=medicine_listing_groups($rows,'NEW');
groupcheck(count($listing)===1 && count($listing[0]['batches'])===3 && $listing[0]['usable_total']===35,'new batch joins one medicine listing and search retains every batch');
$stock=$pdo->query('SELECT quantity FROM medicines WHERE id=1')->fetchColumn();groupcheck((int)$stock===0,'adding batch preserves source batch balance');
$movement=$pdo->query('SELECT medicine_id,quantity FROM transactions ORDER BY id DESC LIMIT 1')->fetch();groupcheck((int)$movement['medicine_id']===$new && (int)$movement['quantity']===30,'new batch starting stock recorded against new batch');
$count=(int)$pdo->query('SELECT COUNT(*) FROM medicines')->fetchColumn();$rejected=false;try{add_medicine_batch($pdo,1,['quantity'=>30,'batch_number'=>'NEW','supplier'=>'','expiration_date'=>'2050-01-01','unit_cost'=>'2'],['id'=>1]);}catch(InvalidArgumentException $e){$rejected=true;}groupcheck($rejected && (int)$pdo->query('SELECT COUNT(*) FROM medicines')->fetchColumn()===$count,'duplicate lot rejected without stock changes');
$pdo->exec("UPDATE medicines SET expiration_date='2000-01-01' WHERE id=".$new);$listing=medicine_listing_groups($pdo->query('SELECT * FROM medicines')->fetchAll(),'NEW');groupcheck($listing[0]['usable_total']===5 && $listing[0]['expired_total']===30,'listing separates expired quantities from usable total');

require_once __DIR__.'/../includes/inventory.php';ensure_inventory_schema($pdo);
$token=bin2hex(random_bytes(32));$input=['medicine_id'=>1,'quantity'=>20,'expiration_date'=>'2051-01-01','request_token'=>$token,'reference_no'=>'DEMO-DELIVERY','notes'=>''];
$before=$pdo->query('SELECT quantity,expiration_date FROM medicines WHERE id=1')->fetch();
$r=inventory_receive_by_expiry($pdo,$input,['id'=>1,'role'=>'staff']);
groupcheck($r['balance']===20 && $r['medicine_id']!==1,'receiving new expiry creates linked expiry stock');
groupcheck($before===$pdo->query('SELECT quantity,expiration_date FROM medicines WHERE id=1')->fetch(),'old expiry and quantity preserved');
$r=inventory_receive_by_expiry($pdo,$input,['id'=>1,'role'=>'staff']);groupcheck($r['duplicate'],'receiving retry does not double-count');
$input['quantity']=5;$input['request_token']=bin2hex(random_bytes(32));$r=inventory_receive_by_expiry($pdo,$input,['id'=>1,'role'=>'staff']);groupcheck($r['balance']===25,'same expiry receipt reuses its stock record');
groupcheck((int)$pdo->query("SELECT COUNT(*) FROM medicines WHERE expiration_date='2051-01-01'")->fetchColumn()===1,'no duplicate expiry record for repeat receipt');
$input['expiration_date']='2000-01-01';$input['request_token']=bin2hex(random_bytes(32));$rejected=false;try{inventory_receive_by_expiry($pdo,$input,['id'=>1,'role'=>'staff']);}catch(InvalidArgumentException $e){$rejected=true;}groupcheck($rejected,'past-expiry delivery rejected');

$pdo->exec("INSERT INTO medicines(id,name,category,unit,unit_cost,quantity,reorder_point,expiration_date,batch_number,created_at) VALUES(7000,'FICTIONAL FULL FLOW','Test','tablet',1,0,10,'2055-01-01','',NOW())");
$staff=['id'=>1,'role'=>'staff'];$receipt=['medicine_id'=>7000,'quantity'=>40,'expiration_date'=>'2055-01-01','request_token'=>bin2hex(random_bytes(32)),'notes'=>'','reference_no'=>'FICTIONAL'];
$first=inventory_receive_by_expiry($pdo,$receipt,$staff);$receipt['quantity']=60;$receipt['expiration_date']='2056-01-01';$receipt['request_token']=bin2hex(random_bytes(32));$second=inventory_receive_by_expiry($pdo,$receipt,$staff);
$list=medicine_listing_groups($pdo->query('SELECT * FROM medicines')->fetchAll(),'FICTIONAL FULL FLOW');groupcheck(count($list)===1 && $list[0]['usable_total']===100 && count($list[0]['batches'])===2,'full flow: two receipts become one medicine with two expiry records');
inventory_movement($pdo,['medicine_id'=>7000,'type'=>'Stock Out','quantity'=>5,'request_token'=>bin2hex(random_bytes(32)),'notes'=>'','reference_no'=>'FICTIONAL'],$staff);
inventory_movement($pdo,['medicine_id'=>$second['medicine_id'],'type'=>'Disposal','quantity'=>10,'request_token'=>bin2hex(random_bytes(32)),'notes'=>'Fictional damaged stock','reference_no'=>'FICTIONAL'],$staff);
$list=medicine_listing_groups($pdo->query('SELECT * FROM medicines')->fetchAll(),'FICTIONAL FULL FLOW');groupcheck($list[0]['usable_total']===85,'full flow: 100 minus 5 issued minus 10 disposed equals 85');
foreach(reorder_list() as $item)if($item['name']==='FICTIONAL FULL FLOW')$planned=$item;
groupcheck($planned['usable_qty']===85 && $planned['used30']===5 && count($planned['batch_ids'])===2,'full flow: planning agrees with list and counts only issues as usage');
$pdo->exec("UPDATE medicines SET expiration_date='2000-01-01' WHERE id=".(int)$second['medicine_id']);
$list=medicine_listing_groups($pdo->query('SELECT * FROM medicines')->fetchAll(),'FICTIONAL FULL FLOW');groupcheck($list[0]['usable_total']===35 && $list[0]['expired_total']===50,'full flow: expired stock excluded without changing other expiry quantity');
inventory_movement($pdo,['medicine_id'=>$second['medicine_id'],'type'=>'Disposal','quantity'=>50,'request_token'=>bin2hex(random_bytes(32)),'notes'=>'Fictional expired stock','reference_no'=>'FICTIONAL'],$staff);
$list=medicine_listing_groups($pdo->query('SELECT * FROM medicines')->fetchAll(),'FICTIONAL FULL FLOW');groupcheck($list[0]['usable_total']===35 && $list[0]['expired_total']===0,'full flow: disposing expired stock leaves usable stock unchanged');
echo "Complete expiry-based inventory workflow passed using temporary tables.\n";

$alerts=medicine_attention_rows($pdo->query("SELECT * FROM medicines WHERE name='FICTIONAL FULL FLOW'")->fetchAll());groupcheck(count($alerts)===0,'alerts ignore empty expired records when combined usable stock is sufficient');
$pdo->exec('UPDATE medicines SET quantity=5 WHERE id=7000');$alerts=medicine_attention_rows($pdo->query("SELECT * FROM medicines WHERE name='FICTIONAL FULL FLOW'")->fetchAll());groupcheck(count($alerts)===1 && $alerts[0]['low'] && !$alerts[0]['exp'] && $alerts[0]['group_usable_qty']===5,'one low-stock alert uses the combined medicine balance');

$beforeEdit=$pdo->query('SELECT id,quantity,expiration_date,supplier,unit_cost FROM medicines WHERE id=7000 OR id='.(int)$second['medicine_id'].' ORDER BY id')->fetchAll();
$edit=$pdo->query('SELECT * FROM medicines WHERE id=7000')->fetch();
$edit['name']='FICTIONAL RENAMED';$edit['category']='Edited category';$edit['reorder_point']=12;$edit['supplier']='Edited supplier';$edit['expiration_date']='2057-01-01';$edit['unit_cost']=4;
update_medicine_details($pdo,7000,$edit);
$edited=$pdo->query('SELECT * FROM medicines WHERE id=7000 OR id='.(int)$second['medicine_id'].' ORDER BY id')->fetchAll();
groupcheck($edited[0]['name']==='FICTIONAL RENAMED' && $edited[1]['name']==='FICTIONAL RENAMED' && $edited[1]['category']==='Edited category','editing medicine identity updates every active expiry record');
groupcheck((int)$edited[0]['quantity']===5 && (int)$edited[1]['quantity']===0 && $edited[1]['expiration_date']===$beforeEdit[1]['expiration_date'] && $edited[1]['supplier']===$beforeEdit[1]['supplier'] && $edited[1]['unit_cost']===$beforeEdit[1]['unit_cost'],'editing leaves quantities and other expiry delivery details unchanged');
$editedList=medicine_listing_groups($edited);groupcheck(count($editedList)===1 && $editedList[0]['reorder_point']===12,'renamed medicine stays one row with a shared reorder point');
$pdo->exec('UPDATE medicines SET archived_at=NOW() WHERE id='.(int)$second['medicine_id']);
$rejected=false;try{update_medicine_details($pdo,(int)$second['medicine_id'],$edit);}catch(InvalidArgumentException $e){$rejected=true;}groupcheck($rejected,'stale edit cannot modify a removed medicine');
$rejected=false;try{group_medicine_batches($pdo,[7000,(int)$second['medicine_id']],12,true);}catch(InvalidArgumentException $e){$rejected=true;}groupcheck($rejected,'removed records cannot be linked back through duplicate maintenance');
