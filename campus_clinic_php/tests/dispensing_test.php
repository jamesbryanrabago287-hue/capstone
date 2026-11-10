<?php
require __DIR__.'/../includes/auth.php';require __DIR__.'/../includes/dispensing.php';
set_exception_handler(function(Throwable $e){fwrite(STDERR,$e->getMessage()."\n");exit(1);});
function ok_dispense($ok,$label){if(!$ok)throw new RuntimeException('FAIL: '.$label);echo 'PASS: '.$label."\n";}
$pdo->exec("CREATE TEMPORARY TABLE medicines(id INT PRIMARY KEY,name VARCHAR(150),batch_number VARCHAR(100),unit VARCHAR(50),expiration_date DATE,quantity INT) ENGINE=InnoDB");
$pdo->exec("CREATE TEMPORARY TABLE transactions(id INT AUTO_INCREMENT PRIMARY KEY,medicine_id INT,type ENUM('Stock In','Stock Out'),quantity INT,reference_no VARCHAR(100),notes TEXT,user_id INT,transaction_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB");ensure_inventory_schema($pdo);
$pdo->exec("CREATE TEMPORARY TABLE clinic_consultations(id INT PRIMARY KEY,assessment_id INT UNIQUE,student_id INT,status VARCHAR(30)) ENGINE=InnoDB");
$pdo->exec("CREATE TEMPORARY TABLE clinic_dispenses(id INT AUTO_INCREMENT PRIMARY KEY,consultation_id INT,transaction_id INT UNIQUE,medicine_name VARCHAR(150),batch_number VARCHAR(100),unit VARCHAR(50),expiration_date DATE,issued_by_name VARCHAR(120)) ENGINE=InnoDB");
$pdo->exec("INSERT INTO medicines VALUES(1,'FICTIONAL MEDICINE','TEST-A','tablet','2099-01-01',10),(2,'EXPIRED TEST','TEST-B','tablet','2000-01-01',10)");
$pdo->exec("INSERT INTO clinic_consultations VALUES(1,101,55,'In progress'),(2,102,56,'Completed')");
$staff=['id'=>1,'role'=>'staff','full_name'=>'Fictional test staff'];
function issue_test($changes=[],$assessment=101){global $pdo,$staff;return clinic_issue_medicine($pdo,$assessment,array_merge(['medicine_id'=>1,'quantity'=>2,'notes'=>'Fictional test','request_token'=>bin2hex(random_bytes(32))],$changes),$staff);}
function reject_issue($changes=[],$assessment=101){try{issue_test($changes,$assessment);return false;}catch(InvalidArgumentException $e){return true;}}
ok_dispense(reject_issue([],999),'unverified/unstarted visit rejected');ok_dispense(reject_issue([],102),'completed visit rejected');ok_dispense(reject_issue(['medicine_id'=>2]),'expired batch rejected');ok_dispense(reject_issue(['quantity'=>11]),'insufficient stock rejected');ok_dispense(reject_issue(['quantity'=>'1.5']),'fractional issue rejected');
$token=bin2hex(random_bytes(32));$r=issue_test(['request_token'=>$token]);ok_dispense($r['balance']===8,'issue deducts stock');
ok_dispense((int)$pdo->query('SELECT COUNT(*) FROM clinic_dispenses WHERE consultation_id=1')->fetchColumn()===1,'issue linked to verified visit');
$r=issue_test(['request_token'=>$token]);ok_dispense($r['duplicate'] && (int)$pdo->query('SELECT COUNT(*) FROM transactions')->fetchColumn()===1,'retry creates no duplicate deduction');
ok_dispense(reject_issue(['request_token'=>$token,'quantity'=>3]),'reused token with changed quantity rejected');
$pdo->exec("UPDATE medicines SET name='RENAMED',batch_number='NEW' WHERE id=1");$snap=$pdo->query('SELECT * FROM clinic_dispenses')->fetch();ok_dispense($snap['medicine_name']==='FICTIONAL MEDICINE' && $snap['batch_number']==='TEST-A','issued batch identity retained as snapshot');
$pdo->exec("ALTER TABLE clinic_dispenses ADD UNIQUE KEY test_force_link_failure (issued_by_name)");$failed=false;try{issue_test();}catch(PDOException $e){$failed=true;}
ok_dispense($failed && (int)$pdo->query('SELECT quantity FROM medicines WHERE id=1')->fetchColumn()===8 && (int)$pdo->query('SELECT COUNT(*) FROM transactions')->fetchColumn()===1,'link failure rolls back both stock and transaction');
$pdo->exec("UPDATE clinic_consultations SET status='Completed' WHERE id=1");$r=issue_test(['request_token'=>$token]);ok_dispense($r['duplicate'],'old retry after completion stays idempotent');ok_dispense(reject_issue(),'new issue after completion rejected');
echo "Dispensing checks passed using temporary tables only.\n";
