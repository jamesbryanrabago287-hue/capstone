<?php
function clinic_consultation_input(array $input) {
    $result=[];
    foreach(['findings'=>3000,'next_steps'=>2000,'outcome'=>40] as $key=>$limit){$value=$input[$key] ?? '';if(!is_string($value) || mb_strlen($value)>$limit)throw new InvalidArgumentException('Please check '.str_replace('_',' ',$key).' (maximum '.$limit.' characters).');$result[$key]=trim($value);}
    if(!in_array($result['outcome'],['','Returned to class','Follow-up advised','Referred for further care','Other'],true))throw new InvalidArgumentException('Choose a valid consultation outcome.');return $result;
}
function clinic_start_consultation(PDO $pdo,$assessmentId,$studentId,array $staff,$verified) {
    if(!$verified)throw new InvalidArgumentException('Confirm that you verified the student identity in person.');
    if($assessmentId<1 || $studentId<1)throw new InvalidArgumentException('Choose a valid assessment and student record.');
    $own=!$pdo->inTransaction();if($own)$pdo->beginTransaction();
    try {
        $stmt=$pdo->prepare('SELECT id FROM chatbot_sessions WHERE id=? FOR UPDATE');$stmt->execute([$assessmentId]);if(!$stmt->fetch())throw new InvalidArgumentException('Assessment not found.');
        $stmt=$pdo->prepare('SELECT * FROM clinic_consultations WHERE assessment_id=? FOR UPDATE');$stmt->execute([$assessmentId]);$existing=$stmt->fetch();
        if($existing){if((int)$existing['student_id']!==(int)$studentId)throw new InvalidArgumentException('This assessment already belongs to a different verified student.');if($own)$pdo->commit();return (int)$existing['id'];}
        $stmt=$pdo->prepare('SELECT id,student_no,full_name FROM students WHERE id=?');$stmt->execute([$studentId]);$student=$stmt->fetch();if(!$student)throw new InvalidArgumentException('Student record not found. Register the student first.');
        $pdo->prepare('INSERT INTO chatbot_reviews(session_id,student_id,reviewed_by,reviewed_at) VALUES(?,?,?,NOW()) ON DUPLICATE KEY UPDATE student_id=VALUES(student_id),reviewed_by=COALESCE(reviewed_by,VALUES(reviewed_by)),reviewed_at=COALESCE(reviewed_at,VALUES(reviewed_at))')->execute([$assessmentId,$studentId,$staff['id']]);
        $pdo->prepare('INSERT INTO clinic_consultations(assessment_id,student_id,student_no_snapshot,student_name_snapshot,recorded_by,recorded_by_name,status,started_at) VALUES(?,?,?,?,?,?,?,NOW())')->execute([$assessmentId,$studentId,$student['student_no'],$student['full_name'],$staff['id'],$staff['full_name'],'In progress']);
        $id=(int)$pdo->lastInsertId();if($own)$pdo->commit();return $id;
    }catch(Throwable $e){if($own && $pdo->inTransaction())$pdo->rollBack();throw $e;}
}
function clinic_save_consultation(PDO $pdo,$assessmentId,array $input,array $staff,$complete,$visitId=null) {
    $data=clinic_consultation_input($input);
    if($complete && ($data['findings']==='' || $data['next_steps']==='' || $data['outcome']===''))throw new InvalidArgumentException('Record staff findings, next steps and an outcome before completing the consultation.');
    $own=!$pdo->inTransaction();if($own)$pdo->beginTransaction();
    try {
        $stmt=$pdo->prepare('SELECT * FROM clinic_consultations WHERE '.($visitId!==null?'id':'assessment_id').'=? FOR UPDATE');$stmt->execute([$visitId ?? $assessmentId]);$visit=$stmt->fetch();
        if(!$visit)throw new InvalidArgumentException('Verify the student and start a consultation first.');
        if($visit['status']==='Completed')throw new InvalidArgumentException('This consultation is completed and read-only.');
        $version=filter_var($input['version'] ?? '',FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
        if(!$version || $version!==(int)$visit['version'])throw new InvalidArgumentException('This visit was updated elsewhere. Reload the page before saving your changes.');
        $pdo->prepare('UPDATE clinic_consultations SET findings=?,next_steps=?,outcome=?,recorded_by=?,recorded_by_name=?,status=?,completed_at=IF(?=1,NOW(),NULL),updated_at=NOW(),version=version+1 WHERE id=?')->execute([$data['findings'],$data['next_steps'],$data['outcome'],$staff['id'],$staff['full_name'],$complete?'Completed':'In progress',$complete?1:0,$visit['id']]);
        if($own)$pdo->commit();return (int)$visit['id'];
    }catch(Throwable $e){if($own && $pdo->inTransaction())$pdo->rollBack();throw $e;}
}
function assessment_receipt($id) {
    return ['saved'=>true,'reference'=>(int)$id,'reference_label'=>'CC-'.str_pad((string)$id,6,'0',STR_PAD_LEFT),'handoff'=>'Your assessment is saved for your clinic visit. Keep this reference and show it with your student ID when you attend. Staff will verify your identity, review your answers and make their own assessment. Sending this assessment does not book a visit or guarantee a reply. Staff review it when you visit. If advised to seek care, go directly and show your reference number and student ID. Do not wait for staff to review your submission. Submissions are not continuously monitored or emergency alerts.'];
}

function clinic_start_walkin(PDO $pdo,$studentId,array $staff,$verified,$token) {
 if(!$verified)throw new InvalidArgumentException('Verify the student identity before starting the visit.');
 if(!is_string($token)||!preg_match('/^[a-f0-9]{64}$/',$token))throw new InvalidArgumentException('Refresh before starting a visit.');
 $pdo->beginTransaction();
 try {
  $stmt=$pdo->prepare('SELECT id,student_no,full_name FROM students WHERE id=? FOR UPDATE');$stmt->execute([$studentId]);$student=$stmt->fetch();if(!$student)throw new InvalidArgumentException('Choose an official student record.');
  $stmt=$pdo->prepare('SELECT id,student_id FROM clinic_consultations WHERE start_token=?');$stmt->execute([$token]);$old=$stmt->fetch();
  if($old){if((int)$old['student_id']!==(int)$studentId)throw new InvalidArgumentException('This form was already used for another student.');$pdo->commit();return (int)$old['id'];}
  $stmt=$pdo->prepare("SELECT id FROM clinic_consultations WHERE student_id=? AND status='In progress' ORDER BY id DESC LIMIT 1");$stmt->execute([$studentId]);$active=$stmt->fetchColumn();if($active){$pdo->commit();return (int)$active;}
  $pdo->prepare("INSERT INTO clinic_consultations(assessment_id,student_id,student_no_snapshot,student_name_snapshot,recorded_by,recorded_by_name,status,start_token) VALUES(NULL,?,?,?,?,?,'In progress',?)")->execute([$studentId,$student['student_no'],$student['full_name'],$staff['id'],$staff['full_name'],$token]);
  $id=(int)$pdo->lastInsertId();$pdo->commit();return $id;
 }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}
