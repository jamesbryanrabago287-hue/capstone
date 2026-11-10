<?php
require __DIR__.'/../includes/auth.php';require __DIR__.'/../includes/inventory.php';
set_exception_handler(function(Throwable $e){fwrite(STDERR,$e->getMessage()."\n");exit(1);});
function check_inventory($ok,$label){if(!$ok)throw new RuntimeException('FAIL: '.$label);echo 'PASS: '.$label."\n";}
// Connection-local temporary tables shadow real tables; no real stock is changed.
$pdo->exec("CREATE TEMPORARY TABLE medicines (id INT AUTO_INCREMENT PRIMARY KEY,name VARCHAR(150),category VARCHAR(100),quantity INT,reorder_point INT,expiration_date DATE,unit_cost DECIMAL(12,2)) ENGINE=InnoDB");
$pdo->exec("CREATE TEMPORARY TABLE transactions (id INT AUTO_INCREMENT PRIMARY KEY,medicine_id INT,type ENUM('Stock In','Stock Out') NOT NULL,quantity INT,reference_no VARCHAR(100),notes TEXT,user_id INT,transaction_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB");
ensure_inventory_schema($pdo);ensure_inventory_schema($pdo);
$pdo->exec("INSERT INTO medicines(name,category,quantity,reorder_point,expiration_date,unit_cost) VALUES('FICTIONAL TEST','Test',100,10,'2000-01-01',1)");$id=(int)$pdo->lastInsertId();
$staff=['id'=>1,'role'=>'staff'];$admin=['id'=>1,'role'=>'admin'];
function move_inventory($override=[],$role=null){global $pdo,$id,$staff;return inventory_movement($pdo,array_merge(['medicine_id'=>$id,'type'=>'Disposal','quantity'=>'20','reference_no'=>'TEST','notes'=>'Expired test batch','request_token'=>bin2hex(random_bytes(32))],$override),$role ?? $staff);}
function rejected_inventory($override,$role=null){try{move_inventory($override,$role);return false;}catch(InvalidArgumentException $e){return true;}}
check_inventory(rejected_inventory(['type'=>'Stock Out']),'expired stock issuance blocked');
check_inventory(rejected_inventory(['notes'=>' ']),'disposal requires a reason');
check_inventory(rejected_inventory(['quantity'=>'101']),'disposal cannot exceed stock');
$token=bin2hex(random_bytes(32));$r=move_inventory(['request_token'=>$token]);check_inventory($r['balance']===80,'expired disposal reduces stock');
$r=move_inventory(['request_token'=>$token]);check_inventory($r['duplicate'] && $r['balance']===80 && (int)$pdo->query('SELECT COUNT(*) FROM transactions')->fetchColumn()===1,'repeated form does not remove stock twice');
check_inventory(rejected_inventory(['type'=>'Physical Count','actual_count'=>'75','expected_balance'=>'80']),'staff cannot adjust stock counts');
check_inventory(rejected_inventory(['type'=>'Physical Count','actual_count'=>'75','expected_balance'=>'100'],$admin),'stale stock count rejected');
$r=move_inventory(['type'=>'Physical Count','actual_count'=>'75','expected_balance'=>'80'],$admin);check_inventory($r['balance']===75 && $r['type']==='Adjustment Out','count decrease records adjustment');
$r=move_inventory(['type'=>'Physical Count','actual_count'=>'90','expected_balance'=>'75'],$admin);check_inventory($r['balance']===90 && $r['type']==='Adjustment In','count increase records adjustment');
check_inventory(rejected_inventory(['type'=>'Physical Count','actual_count'=>'90','expected_balance'=>'90'],$admin),'matching count does not create misleading adjustment');
check_inventory(rejected_inventory(['quantity'=>'1.5']),'fractional quantities rejected');
check_inventory(rejected_inventory(['quantity'=>['20']]),'array quantities rejected');
$pdo->exec("UPDATE medicines SET expiration_date='2099-01-01'");
move_inventory(['type'=>'Stock Out','quantity'=>'5']);move_inventory(['type'=>'Stock In','quantity'=>'10']);
check_inventory((int)$pdo->query("SELECT SUM(quantity) FROM transactions WHERE type='Stock Out'")->fetchColumn()===5,'usage excludes disposal and adjustments');
$rows=$pdo->query('SELECT * FROM transactions ORDER BY id')->fetchAll();$balance=100;foreach($rows as $row){check_inventory((int)$row['balance_before']===$balance,'movement opening balance retained');$balance+=(in_array($row['type'],['Stock In','Adjustment In'],true)?1:-1)*(int)$row['quantity'];check_inventory((int)$row['balance_after']===$balance,'movement closing balance reconciles');}
check_inventory($balance===(int)$pdo->query('SELECT quantity FROM medicines')->fetchColumn(),'ledger matches final stock');
$r=move_inventory(['type'=>'Physical Count','actual_count'=>'0','expected_balance'=>(string)$balance],$admin);check_inventory($r['balance']===0,'zero physical count allowed');
echo "All inventory checks passed using temporary tables only.\n";
