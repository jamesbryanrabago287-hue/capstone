<?php
require_once __DIR__.'/includes/complaint_report.php';require_once __DIR__.'/includes/auth.php'; require_login();
require_once __DIR__.'/includes/assessment.php';
require_once __DIR__.'/includes/consultations.php';
require_once __DIR__.'/includes/dispensing.php';
require_once __DIR__.'/includes/student_lookup.php';
header('Cache-Control: no-store'); ensure_assessment_tables($pdo);ensure_inventory_schema($pdo);ensure_dispensing_table($pdo);
$visitId=filter_var($_GET['visit_id'] ?? '',FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]) ?: null;
$id=filter_var($_GET['assessment_id'] ?? '',FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]) ?: null;
$visit=null;$assessment=null;
if($visitId){$stmt=$pdo->prepare('SELECT * FROM clinic_consultations WHERE id=?');$stmt->execute([$visitId]);$visit=$stmt->fetch();if(!$visit){http_response_code(404);exit('Visit not found.');}$id=$visit['assessment_id'];}
if($id){$stmt=$pdo->prepare('SELECT c.*,r.student_id AS linked_student_id FROM chatbot_sessions c LEFT JOIN chatbot_reviews r ON r.session_id=c.id WHERE c.id=?');$stmt->execute([$id]);$assessment=$stmt->fetch();if(!$assessment){http_response_code(404);exit('Assessment not found.');}}
if(!$visitId && !$id){redirect('assessments.php');}
$visitUrl='consultation.php?'.($visitId?'visit_id='.$visitId:'assessment_id='.$id);
$context=$visitId?'Visit #'.$visitId:'Assessment #'.$id;
$error='';$entered=[];
if($_SERVER['REQUEST_METHOD']==='POST'){
    verify_csrf();$action=$_POST['action'] ?? '';
    try{
        if($action==='start'){
            $studentId=filter_var($_POST['student_id'] ?? '',FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
            clinic_start_consultation($pdo,$id,$studentId ?: 0,$_SESSION['user'],($_POST['verified'] ?? '')==='yes');
            record_activity($_SESSION['user']['id'],$_SESSION['user']['username'],'start_consultation',$context.'; verified student ID '.$studentId);
            flash('success','Consultation started for the verified student.');
        }elseif($action==='issue'){
            $issued=clinic_issue_medicine($pdo,$id,$_POST,$_SESSION['user'],$visitId);
            if(!$issued['duplicate'])record_activity($_SESSION['user']['id'],$_SESSION['user']['username'],'dispense_medicine',$context.'; stock transaction #'.$issued['transaction_id']);
            $details=$pdo->prepare('SELECT t.quantity,t.balance_after,d.medicine_name,d.batch_number,d.unit FROM clinic_dispenses d JOIN transactions t ON t.id=d.transaction_id WHERE t.id=?');$details->execute([$issued['transaction_id']]);$receipt=$details->fetch();
            flash('success',($issued['duplicate']?'Already recorded: ':'Issued ').$receipt['quantity'].' '.$receipt['unit'].' of '.$receipt['medicine_name'].'; batch '.($receipt['batch_number'] ?: 'not specified').'. Balance after this issue: '.$receipt['balance_after'].'. Do not record this issue again in Manage Inventory.');
        }elseif(in_array($action,['save','complete'],true)){
            $entered=clinic_consultation_input($_POST);
            clinic_save_consultation($pdo,$id,$_POST,$_SESSION['user'],$action==='complete',$visitId);
            record_activity($_SESSION['user']['id'],$_SESSION['user']['username'],$action==='complete'?'complete_consultation':'save_consultation',$context);
            flash('success',$action==='complete'?'Consultation completed and recorded in the student history.':'Consultation draft saved.');
        }else throw new InvalidArgumentException('Unknown consultation action.');
        redirect($visitUrl);
    }catch(InvalidArgumentException $e){$error=$e->getMessage();}
    catch(Throwable $e){error_log('Consultation failed: '.get_class($e));$error='Could not save the consultation. Please try again.';}
}
$stmt=$pdo->prepare('SELECT * FROM clinic_consultations WHERE '.($visitId?'id':'assessment_id').'=?');$stmt->execute([$visitId ?? $id]);$visit=$stmt->fetch();
$dispenses=[];$batches=[];
if($visit){
 $stmt=$pdo->prepare('SELECT d.*,t.quantity,t.transaction_date,t.notes,t.id AS stock_transaction_id FROM clinic_dispenses d JOIN transactions t ON t.id=d.transaction_id WHERE d.consultation_id=? ORDER BY t.id');$stmt->execute([$visit['id']]);$dispenses=$stmt->fetchAll();
 if($visit['status']==='In progress')$batches=$pdo->query('SELECT id,name,batch_number,expiration_date,quantity,unit FROM medicines WHERE quantity>0 AND expiration_date>=CURDATE() ORDER BY name,expiration_date,id')->fetchAll();
}
$lookup=$_GET['student_no'] ?? ($assessment['student_no'] ?? '');if(!is_string($lookup))$lookup='';$lookup=mb_substr(trim($lookup),0,80);
$candidate=null;$matches=[];
if(!$visit && $lookup!==''){
 $matches=clinic_find_students($pdo,$lookup);
 $selected=filter_var($_GET['student_id'] ?? '',FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
 if($selected){foreach($matches as $match)if((int)$match['id']===$selected)$candidate=$match;}
 elseif(count($matches)===1)$candidate=$matches[0];
}
$page_title='Consultation';include __DIR__.'/includes/header.php';render_flash();
$reference=$id?'CC-'.str_pad((string)$id,6,'0',STR_PAD_LEFT):'';
?>
<link rel="stylesheet" href="assets/chatbot.css">
<p><a href="assessments.php">&larr; Back to assessment inbox</a><?php if($visit): ?> &middot; <a href="assessments.php?student_id=<?= (int)$visit['student_id'] ?>">Student assessment / visit history</a><?php endif; ?></p>
<?php if($error): ?><div class="alert danger" role="alert"><?= h($error) ?></div><?php endif; ?>
<?php if($assessment): ?><section class="panel"><h2>Assessment <?= h($reference) ?></h2><p>Submitted <?= h($assessment['created_at']) ?> by <?= h($assessment['student_name']) ?>, student number <?= h($assessment['student_no']) ?>. These identity details are self-reported until verified.</p>
<details><summary>View student answers and preliminary guidance</summary><div class="assessment-review-grid"><div><h3>Student answers</h3><?php render_complaint_report($assessment['symptoms'],true); ?></div><div><h3>Preliminary result: <?= h($assessment['assessment']) ?></h3><p class="assessment-record"><?= h($assessment['advice']) ?></p></div></div></details></section>
<?php else: ?><section class="panel"><h2>Walk-in visit</h2><p>Previously saved visit.</p></section><?php endif; ?>
<?php if(!$visit): ?>
<section class="panel"><h2>1. Verify the student</h2><p>Check the student ID and match the official record in person. A submitted assessment is not an appointment or proof of identity.</p>
<form method="get"><input type="hidden" name="assessment_id" value="<?= $id ?>"><label>Student name or number<input name="student_no" value="<?= h($lookup) ?>" maxlength="80" required></label><button type="submit">Search students</button></form>
<?php if(count($matches)>1): ?><p>Select the matching official record, then check the student ID. Showing up to 20 matches; narrow the search if needed.</p><table><thead><tr><th>Student number</th><th>Name</th><th>College / course</th><th>Action</th></tr></thead><tbody><?php foreach(array_slice($matches,0,20) as $match): ?><tr><td><?= h($match['student_no']) ?></td><td><?= h($match['full_name']) ?></td><td><?= h($match['college']) ?> / <?= h($match['course']) ?></td><td><a href="consultation.php?<?= h(http_build_query(['assessment_id'=>$id,'student_no'=>$lookup,'student_id'=>$match['id']])) ?>">Select student</a></td></tr><?php endforeach; ?></tbody></table><?php endif; ?>
<?php if($candidate): ?><div class="alert"><strong><?= h($candidate['full_name']) ?></strong> &middot; <?= h($candidate['student_no']) ?><br><?= h($candidate['college']) ?> &middot; <?= h($candidate['course']) ?></div>
<form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="start"><input type="hidden" name="student_id" value="<?= (int)$candidate['id'] ?>"><label class="verification-check"><input type="checkbox" name="verified" value="yes" required> I verified this student's identity in person and confirmed this is the correct assessment.</label><button type="submit">Verify and start consultation</button></form>
<?php elseif(!$matches): ?><p>No matching official student record. Check the number or <a href="students.php">register the student on Students</a>, then return here.</p><?php endif; ?></section>
<?php else: ?>
<section class="panel"><h2>2. Record findings <span class="badge"><?= h($visit['status']) ?></span></h2><p><strong><?= h($visit['student_name_snapshot']) ?></strong> &middot; <?= h($visit['student_no_snapshot']) ?><br>Started <?= h($visit['started_at']) ?>. Last recorded by <?= h($visit['recorded_by_name']) ?> at <?= h($visit['updated_at']) ?>.</p>
<p>Record your findings and next steps below. Save notes before recording any medicine issued, then complete the visit when finished.</p>
<?php if($visit['status']==='Completed'): ?><p><strong>Completed <?= h($visit['completed_at']) ?></strong></p><div class="assessment-review-grid"><div><h3>Staff findings</h3><p class="assessment-record"><?= h($visit['findings']) ?></p></div><div><h3>Next steps communicated to student</h3><p class="assessment-record"><?= h($visit['next_steps']) ?></p></div></div><p>Outcome: <strong><?= h($visit['outcome']) ?></strong></p><p>This completed visit is read-only.</p>
<?php else: $values=$entered ?: $visit; $formVersion=$error!=='' && in_array($_POST['action'] ?? '',['save','complete'],true) ? (int)(filter_var($_POST['version'] ?? '',FILTER_VALIDATE_INT) ?: 0) : (int)$visit['version']; ?>
<form method="post" id="consultation-notes" data-unsaved-notes><?= csrf_field() ?><input type="hidden" name="version" value="<?= $formVersion ?>">
<label>Staff observations / findings<textarea data-saved-value="<?= h($visit['findings'] ?? '') ?>" name="findings" maxlength="3000" rows="6"><?= h($values['findings'] ?? '') ?></textarea></label>
<label>Next steps communicated to student<textarea data-saved-value="<?= h($visit['next_steps'] ?? '') ?>" name="next_steps" maxlength="2000" rows="4"><?= h($values['next_steps'] ?? '') ?></textarea></label>
<label>Visit outcome<select data-saved-value="<?= h($visit['outcome'] ?? '') ?>" name="outcome"><option value="">Choose when completing the visit</option><?php foreach(['Returned to class','Follow-up advised','Referred for further care','Other'] as $outcome): ?><option value="<?= h($outcome) ?>" <?= ($values['outcome'] ?? '')===$outcome?'selected':'' ?>><?= h($outcome) ?></option><?php endforeach; ?></select></label>
<p id="notes-save-status" role="status">Notes are saved. Changes will be marked here.</p><p><button type="submit" name="action" value="save">Save findings</button></p><small>Completion requires findings, next steps and an outcome. Completed records become read-only.</small></form>
<?php endif; ?></section>
<?php endif; ?>

<?php if($visit): ?>
<section class="panel"><h2>3. Record medicine issued (if any)</h2>
<p>Record medicine actually issued by authorized clinic staff. Each saved issue automatically deducts stock. Do not enter the same issue again in Manage Inventory. Save consultation notes before using this separate form.</p>
<?php if($dispenses): ?><table><thead><tr><th>Date</th><th>Medicine / batch</th><th>Expiry</th><th>Quantity</th><th>Issued by</th><th>Notes</th><th>Stock movement</th></tr></thead><tbody>
<?php foreach($dispenses as $d): ?><tr><td><?= h($d['transaction_date']) ?></td><td><?= h($d['medicine_name']) ?><br>Batch <?= h($d['batch_number'] ?: 'Not specified') ?></td><td><?= h($d['expiration_date']) ?></td><td><?= (int)$d['quantity'] ?> <?= h($d['unit']) ?></td><td><?= h($d['issued_by_name']) ?></td><td><?= h($d['notes']) ?></td><td>#<?= (int)$d['stock_transaction_id'] ?></td></tr><?php endforeach; ?></tbody></table>
<?php else: ?><p>No medicines recorded for this visit.</p><?php endif; ?>
<?php if($visit['status']==='In progress' && $batches): ?>
<form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="issue"><input type="hidden" name="request_token" value="<?= h(bin2hex(random_bytes(32))) ?>">
<label>Medicine batch<select name="medicine_id" required><option value="">Select the batch actually issued</option><?php foreach($batches as $b): ?><option value="<?= (int)$b['id'] ?>"><?= h($b['name']) ?> | Batch <?= h($b['batch_number'] ?: 'Not specified') ?> | Expiry <?= h($b['expiration_date']) ?> | <?= (int)$b['quantity'] ?> <?= h($b['unit']) ?> available</option><?php endforeach; ?></select></label>
<label>Quantity issued<input name="quantity" type="number" min="1" max="100000" required></label><label>Issuance notes (optional)<textarea name="notes" maxlength="1000" rows="2"></textarea></label>
<button type="submit">Record issue and deduct stock</button></form>
<?php elseif($visit['status']==='In progress'): ?><p>No usable medicine batches are available.</p><?php else: ?><p>Completed visit: medicine history is read-only.</p><?php endif; ?></section>
<?php endif; ?>
<?php if($visit && $visit['status']==='In progress'): ?><section class="panel"><h2>4. Complete the consultation</h2><p>Check the findings, next steps and outcome above. Record any medicine issued before completing. A visit can be completed without issuing medicine.</p><button type="submit" name="action" value="complete" form="consultation-notes">Complete consultation</button><p>Completion saves the current findings and makes this visit read-only.</p></section><?php endif; ?>
<?php include __DIR__.'/includes/footer.php'; ?>
