<?php
require is_file(__DIR__.'/../includes/config.php') ? __DIR__.'/../includes/config.php' : __DIR__.'/../includes/config.example.php';
set_exception_handler(function(Throwable $e){fwrite(STDERR,$e->getMessage().PHP_EOL);exit(1);});
require __DIR__.'/../includes/assessment.php';
require __DIR__.'/../includes/consultations.php';
function check($ok,$label){if(!$ok)throw new RuntimeException('FAIL: '.$label);echo 'PASS: '.$label."\n";}
function rejects(callable $fn,$label){try{$fn();}catch(InvalidArgumentException $e){check(true,$label);return;}throw new RuntimeException('FAIL: '.$label);}
ensure_assessment_tables($pdo);
$staff=$pdo->query("SELECT id,full_name FROM users WHERE status='Active' LIMIT 1")->fetch();if(!$staff)throw new RuntimeException('An active staff account is needed for this test.');
$pdo->beginTransaction();
try{
 $marker='TEST-CONSULT-'.bin2hex(random_bytes(6));
 $stmt=$pdo->prepare('INSERT INTO students(student_no,full_name) VALUES(?,?)');$stmt->execute([$marker,$marker]);$student=(int)$pdo->lastInsertId();
 $stmt->execute([$marker.'-B',$marker.'-B']);$other=(int)$pdo->lastInsertId();
 $pdo->prepare('INSERT INTO chatbot_sessions(student_no,student_name,symptoms,assessment,advice) VALUES(?,?,?,?,?)')->execute([$marker,$marker,'Synthetic symptoms','Synthetic preliminary assessment','Synthetic guidance']);$assessment=(int)$pdo->lastInsertId();
 rejects(function()use($pdo,$assessment,$student,$staff){clinic_start_consultation($pdo,$assessment,$student,$staff,false);},'identity confirmation required');
 $id=clinic_start_consultation($pdo,$assessment,$student,$staff,true);
 check($id>0,'verified student visit starts');
 check(clinic_start_consultation($pdo,$assessment,$student,$staff,true)===$id,'repeated start returns same consultation');
 rejects(function()use($pdo,$assessment,$other,$staff){clinic_start_consultation($pdo,$assessment,$other,$staff,true);},'existing consultation cannot be assigned to another student');
 $data=['findings'=>'Synthetic staff findings <script>test</script>','next_steps'=>'Synthetic next steps','outcome'=>'Follow-up advised','version'=>'1'];
 clinic_save_consultation($pdo,$assessment,$data,$staff,false);
 $get=$pdo->prepare('SELECT * FROM clinic_consultations WHERE id=?');$get->execute([$id]);$visit=$get->fetch();
 check($visit['status']==='In progress' && (int)$visit['version']===2 && $visit['completed_at']===null,'draft retains in-progress state and increments revision');
 rejects(function()use($pdo,$assessment,$data,$staff){clinic_save_consultation($pdo,$assessment,$data,$staff,false);},'stale draft cannot overwrite newer changes');
 rejects(function()use($pdo,$assessment,$staff){clinic_save_consultation($pdo,$assessment,['findings'=>'','next_steps'=>'','outcome'=>'','version'=>'2'],$staff,true);},'completion requires staff findings and next steps');
 $data['version']='2';clinic_save_consultation($pdo,$assessment,$data,$staff,true);$get->execute([$id]);$visit=$get->fetch();
 check($visit['status']==='Completed' && $visit['completed_at']!==null && (int)$visit['student_id']===$student,'completed consultation links to verified student history');
 check($visit['student_name_snapshot']===$marker && (int)$visit['recorded_by']===(int)$staff['id'],'identity snapshot and recorder retained');
 $data['version']='3';rejects(function()use($pdo,$assessment,$data,$staff){clinic_save_consultation($pdo,$assessment,$data,$staff,false);},'completed visit is read-only');
 try{$pdo->prepare('DELETE FROM students WHERE id=?')->execute([$student]);throw new RuntimeException('FAIL: student history was deleted');}catch(PDOException $e){check($e->getCode()==='23000','consultation history prevents student deletion');}
 $receipt=assessment_receipt($assessment);check(strpos($receipt['reference_label'],'CC-')===0 && strpos($receipt['handoff'],'student ID')!==false,'receipt supplies reference and visit instructions');
 check(strpos(assessment_evaluate(['warning'=>'yes'])['next_action'],'not required')!==false,'emergency result does not require submission');
 rejects(function(){clinic_consultation_input(['findings'=>[]]);},'array input rejected');
 echo "Consultation workflow tests passed.\n";
}finally{$pdo->rollBack();echo "Synthetic records rolled back.\n";}
