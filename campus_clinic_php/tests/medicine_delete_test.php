<?php
require __DIR__.'/../includes/auth.php';
set_exception_handler(function(Throwable $e){fwrite(STDERR,$e->getMessage()."\n");exit(1);});
function deletion_check($ok,$label){if(!$ok)throw new RuntimeException($label);echo 'PASS: '.$label.PHP_EOL;}
$pdo->exec("CREATE TEMPORARY TABLE medicines(id INT PRIMARY KEY,name VARCHAR(100),quantity INT,reorder_group_id INT NULL,archived_at DATETIME NULL) ENGINE=InnoDB");
$pdo->exec("CREATE TEMPORARY TABLE transactions(id INT PRIMARY KEY,medicine_id INT) ENGINE=InnoDB");
$pdo->exec("INSERT INTO medicines(id,name,quantity,reorder_group_id) VALUES(1,'Unused',0,NULL),(2,'History',0,NULL),(3,'Remaining',5,NULL),(4,'Grouped',0,10),(5,'Grouped',2,10),(6,'Empty group',0,20),(7,'Empty group',0,20)");
$pdo->exec('INSERT INTO transactions VALUES(1,2),(2,6)');
try{delete_empty_medicine($pdo,1,false);deletion_check(false,'staff blocked');}catch(InvalidArgumentException $e){deletion_check(true,'staff cannot delete');}
delete_empty_medicine($pdo,1,true);deletion_check(!$pdo->query('SELECT id FROM medicines WHERE id=1')->fetch(),'unused empty entry permanently deleted');
delete_empty_medicine($pdo,2,true);deletion_check((bool)$pdo->query('SELECT archived_at FROM medicines WHERE id=2')->fetchColumn() && (int)$pdo->query('SELECT COUNT(*) FROM transactions WHERE medicine_id=2')->fetchColumn()===1,'entry with history hidden; history preserved');
foreach([3,4] as $id){try{delete_empty_medicine($pdo,$id,true);deletion_check(false,'stock blocked');}catch(InvalidArgumentException $e){deletion_check(true,'remaining stock blocks removal for ID '.$id);}}
delete_empty_medicine($pdo,6,true);deletion_check((int)$pdo->query('SELECT COUNT(*) FROM medicines WHERE reorder_group_id=20 AND archived_at IS NULL')->fetchColumn()===0,'all empty expiry records removed from active group');
deletion_check((int)$pdo->query('SELECT COUNT(*) FROM transactions')->fetchColumn()===2,'all historical movements preserved');
