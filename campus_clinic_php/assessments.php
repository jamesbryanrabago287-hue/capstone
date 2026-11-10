<?php
require_once __DIR__.'/includes/auth.php';require_login();
require_once __DIR__.'/includes/assessment.php';
header('Cache-Control: no-store');ensure_assessment_tables($pdo);
if($_SERVER['REQUEST_METHOD']==='POST'){
    verify_csrf();$id=filter_var($_POST['id'] ?? '',FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
    if(!$id || ($_POST['action'] ?? '')!=='review'){http_response_code(400);exit('Invalid review request.');}
    $stmt=$pdo->prepare('SELECT id FROM chatbot_sessions WHERE id=?');$stmt->execute([$id]);if(!$stmt->fetch()){http_response_code(404);exit('Assessment not found.');}
    $pdo->prepare('INSERT INTO chatbot_reviews(session_id,reviewed_by,reviewed_at) VALUES(?,?,NOW()) ON DUPLICATE KEY UPDATE reviewed_by=COALESCE(reviewed_by,VALUES(reviewed_by)),reviewed_at=COALESCE(reviewed_at,VALUES(reviewed_at))')->execute([$id,$_SESSION['user']['id']]);
    record_activity($_SESSION['user']['id'],$_SESSION['user']['username'],'review_assessment','Reviewed assessment #'.$id);flash('success','Assessment marked reviewed. Start a consultation when the student attends.');redirect('assessments.php');
}
$page_title='Student Pre-Assessments';
$filter=filter_var($_GET['student_id'] ?? '',FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
$status=is_string($_GET['status'] ?? null)?$_GET['status']:'all';if(!in_array($status,['all','New','Reviewed','In progress','Completed'],true))$status='all';
$search=is_string($_GET['q'] ?? null)?mb_substr(trim($_GET['q']),0,80):'';
$case="CASE WHEN v.status='Completed' THEN 'Completed' WHEN v.id IS NOT NULL THEN 'In progress' WHEN r.reviewed_at IS NOT NULL THEN 'Reviewed' ELSE 'New' END";
$joins=' FROM chatbot_sessions c LEFT JOIN chatbot_reviews r ON r.session_id=c.id LEFT JOIN clinic_consultations v ON v.assessment_id=c.id';
$conditions=[];$params=[];
if($filter){$conditions[]='(r.student_id=? OR v.student_id=?)';$params[]=$filter;$params[]=$filter;}
if($status!=='all'){$conditions[]=$case.'=?';$params[]=$status;}
if($search!==''){
    if(preg_match('/^(?:CC-)?([0-9]+)$/i',$search,$m)){$conditions[]='c.id=?';$params[]=(int)$m[1];}
    else{$conditions[]="c.student_no LIKE ? ESCAPE '!'";$params[]='%'.str_replace(['!','%','_'],['!!','!%','!_'],$search).'%';}
}
$where=$conditions?' WHERE '.implode(' AND ',$conditions):'';
$count=$pdo->prepare('SELECT COUNT(*)'.$joins.$where);$count->execute($params);$total=(int)$count->fetchColumn();
$page=max(1,(int)($_GET['page'] ?? 1));$page=min($page,max(1,(int)ceil($total/20)));$offset=($page-1)*20;
$stmt=$pdo->prepare('SELECT c.*,r.reviewed_at,r.student_id,v.id AS consultation_id,v.status AS consultation_status,v.completed_at,'.$case.' AS workflow_status,u.full_name AS reviewer,s.full_name AS linked_name'.$joins.' LEFT JOIN users u ON u.id=r.reviewed_by LEFT JOIN students s ON s.id=COALESCE(v.student_id,r.student_id)'.$where.' ORDER BY c.id DESC LIMIT 20 OFFSET '.$offset);$stmt->execute($params);$rows=$stmt->fetchAll();
$counts=['New'=>0,'Reviewed'=>0,'In progress'=>0,'Completed'=>0];foreach($pdo->query('SELECT '.$case.' AS workflow_status,COUNT(*) AS n'.$joins.' GROUP BY workflow_status')->fetchAll() as $row)$counts[$row['workflow_status']]=(int)$row['n'];
include __DIR__.'/includes/header.php';render_flash();
?>
<link rel="stylesheet" href="assets/chatbot.css">
<div class="cards"><?php foreach($counts as $label=>$n): ?><a class="card" href="assessments.php?<?= h(http_build_query(['status'=>$label])) ?>"><span><?= h($label) ?></span><strong><?= $n ?></strong></a><?php endforeach; ?></div>
<section class="panel"><h2>Assessment and consultation inbox</h2><p>Visit-based review: when a student arrives, find their reference or student number, verify identity, review the submitted answers, and start the consultation. Record staff findings and next steps, then complete the visit. Submitting does not book a visit or guarantee a reply. This inbox is not continuously monitored or an emergency-alert service.</p>
<form method="get" class="assessment-filters"><?php if($filter): ?><input type="hidden" name="student_id" value="<?= $filter ?>"><?php endif; ?><label>Reference or student number<input name="q" value="<?= h($search) ?>" maxlength="80" placeholder="CC-000123 or student number"></label><label>Status<select name="status"><?php foreach(['all','New','Reviewed','In progress','Completed'] as $s): ?><option value="<?= h($s) ?>" <?= $status===$s?'selected':'' ?>><?= $s==='all'?'All statuses':h($s) ?></option><?php endforeach; ?></select></label><button type="submit">Filter</button><a href="assessments.php">Reset</a></form>
<p><?= $total ?> matching submission(s) &middot; <a href="pre_assessment.php">Open student symptom check</a><?php if($filter): ?> &middot; Verified student history<?php endif; ?></p></section>
<?php if(!$rows): ?><section class="panel"><p>No matching assessments.</p></section><?php endif; ?>
<?php foreach($rows as $r):$reference='CC-'.str_pad((string)$r['id'],6,'0',STR_PAD_LEFT); ?>
<section class="panel"><div class="panel-head"><h2><?= h($reference) ?></h2><span class="badge"><?= h($r['workflow_status']) ?></span></div>
<p><strong><?= h($r['student_name']) ?></strong> &middot; Submitted student number <?= h($r['student_no']) ?> &middot; <?= h($r['created_at']) ?></p>
<p><strong>Preliminary chatbot result:</strong> <?= h($r['assessment']) ?></p>
<?php if($r['linked_name']): ?><p>Linked official record: <?= h($r['linked_name']) ?> &middot; <a href="assessments.php?student_id=<?= (int)$r['student_id'] ?>">View history</a></p><?php else: ?><p>Identity not yet verified against an official student record.</p><?php endif; ?>
<details><summary>Student answers and preliminary guidance</summary><div class="assessment-review-grid"><div><h3>Student answers</h3><p class="assessment-record"><?= h($r['symptoms']) ?></p></div><div><h3>Chatbot guidance</h3><p class="assessment-record"><?= h($r['advice']) ?></p></div></div></details>
<?php if($r['reviewed_at']): ?><p>Reviewed <?= h($r['reviewed_at']) ?> by <?= h($r['reviewer'] ?? 'Clinic staff') ?>.</p><?php else: ?><form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="action" value="review"><button type="submit">Mark answers reviewed</button></form><?php endif; ?>
<p><a class="btn primary" href="consultation.php?assessment_id=<?= (int)$r['id'] ?>"><?= $r['workflow_status']==='Completed'?'View completed consultation':($r['consultation_id']?'Continue consultation':'Verify student / start consultation') ?></a></p>
<?php if($r['completed_at']): ?><p>Visit completed <?= h($r['completed_at']) ?>. Staff findings are recorded separately from the chatbot result.</p><?php endif; ?></section>
<?php endforeach; ?>
<p>Page <?= $page ?> of <?= max(1,(int)ceil($total/20)) ?>
<?php if($page>1): ?><a href="?<?= h(http_build_query(['page'=>$page-1,'status'=>$status,'q'=>$search,'student_id'=>$filter ?: ''])) ?>">Previous</a><?php endif; ?>
<?php if($offset+20<$total): ?><a href="?<?= h(http_build_query(['page'=>$page+1,'status'=>$status,'q'=>$search,'student_id'=>$filter ?: ''])) ?>">Next</a><?php endif; ?></p>
<?php include __DIR__.'/includes/footer.php'; ?>
