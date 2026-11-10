<?php
require __DIR__.'/../includes/auth.php';
set_exception_handler(function(Throwable $e){fwrite(STDERR,$e->getMessage()."\n");exit(1);});
$pdo->exec('CREATE TEMPORARY TABLE clinic_settings(setting_key VARCHAR(80) PRIMARY KEY,setting_value VARCHAR(255) NOT NULL) ENGINE=InnoDB');
function expiry_check($ok,$label){if(!$ok)throw new RuntimeException($label);echo 'PASS: '.$label.PHP_EOL;}
expiry_check(expiry_warning_days()===30,'default is 30 days');
expiry_check(expiry_status(date('Y-m-d',strtotime('+31 days')))===null,'day 31 excluded by default');
foreach([60,90,45] as $days){save_expiry_warning_days($pdo,$days,true);expiry_check(expiry_warning_days()===$days && expiry_status(date('Y-m-d',strtotime('+'.$days.' days')))['label']==='Expiring soon' && expiry_status(date('Y-m-d',strtotime('+'.($days+1).' days')))===null,'preset/custom boundary '.$days.' days');}
expiry_check(expiry_status(date('Y-m-d',strtotime('-1 day')))['label']==='Expired','expired status independent of warning period');
foreach([0,366,-1,'abc','2.5',[],true] as $bad){$blocked=false;try{save_expiry_warning_days($pdo,$bad,true);}catch(InvalidArgumentException $e){$blocked=true;}expiry_check($blocked,'invalid setting rejected');}
$blocked=false;try{save_expiry_warning_days($pdo,60,false);}catch(InvalidArgumentException $e){$blocked=true;}expiry_check($blocked && expiry_warning_days()===45,'staff cannot change setting');
$row=['id'=>1,'name'=>'Fictional','generic_name'=>'','brand'=>'','description'=>'','category'=>'Test','unit'=>'tablet','quantity'=>5,'reorder_point'=>0,'expiration_date'=>date('Y-m-d',strtotime('+40 days')),'reorder_group_id'=>null];
expiry_check(count(medicine_attention_rows([$row]))===1,'alerts use custom period');
save_expiry_warning_days($pdo,30,true);expiry_check(count(medicine_attention_rows([$row]))===0,'alerts update when period changes');
