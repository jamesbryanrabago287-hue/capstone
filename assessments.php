<?php
require_once __DIR__.'/includes/complaint_report.php';require_once __DIR__.'/includes/auth.php';require_login();
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
<?php include __DIR__.'/includes/assessment_inbox_view.php'; include __DIR__.'/includes/footer.php'; ?>