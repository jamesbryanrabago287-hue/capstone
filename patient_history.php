<?php
require_once __DIR__.'/includes/auth.php';require_login();
require_once __DIR__.'/includes/patients.php';ensure_patient_schema($pdo);
require_once __DIR__.'/includes/assessment.php';ensure_assessment_tables($pdo);
require_once __DIR__.'/includes/list_filters.php';
require_once __DIR__.'/includes/patient_issuing.php';ensure_inventory_schema($pdo);ensure_patient_issues($pdo);
require_once __DIR__.'/includes/dispensing.php';ensure_dispensing_table($pdo);
$id=filter_var($_GET['id'] ?? '',FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
if(!$id){http_response_code(400);exit('Choose a patient from the patient list.');}
$stmt=$pdo->prepare('SELECT * FROM students WHERE id=?');$stmt->execute([$id]);$patient=$stmt->fetch();
if(!$patient){http_response_code(404);exit('Patient not found.');}
$stmt=$pdo->prepare('SELECT COUNT(*) FROM clinic_consultations WHERE student_id=?');$stmt->execute([$id]);$count=(int)$stmt->fetchColumn();$pager=clinic_pager($count,$_GET['page'] ?? 1,20);
$stmt=$pdo->prepare('SELECT id,status,started_at,completed_at FROM clinic_consultations WHERE student_id=? ORDER BY started_at DESC,id DESC LIMIT 20 OFFSET '.$pager['offset']);$stmt->execute([$id]);$visits=$stmt->fetchAll();
$givenQuery="SELECT t.id AS transaction_id,t.transaction_date,t.quantity,i.medicine_name,i.unit,i.issued_by_name,NULL AS consultation_id FROM transactions t JOIN inventory_patient_issues i ON i.transaction_id=t.id WHERE i.patient_id=? UNION ALL SELECT t.id AS transaction_id,t.transaction_date,t.quantity,d.medicine_name,d.unit,d.issued_by_name,d.consultation_id FROM transactions t JOIN clinic_dispenses d ON d.transaction_id=t.id JOIN clinic_consultations v ON v.id=d.consultation_id WHERE v.student_id=?";
$stmt=$pdo->prepare('SELECT COUNT(*) FROM ('.$givenQuery.') given_records');$stmt->execute([$id,$id]);$givenCount=(int)$stmt->fetchColumn();$givenPager=clinic_pager($givenCount,$_GET['medicine_page'] ?? 1,10);
$stmt=$pdo->prepare('SELECT * FROM ('.$givenQuery.') given_records ORDER BY transaction_date DESC,transaction_id DESC LIMIT 10 OFFSET '.$givenPager['offset']);$stmt->execute([$id,$id]);$given=$stmt->fetchAll();
$page_title='Patient Visit History';include __DIR__.'/includes/header.php';
?>
<section class="panel">
<div class="list-heading-actions"><h2><?= h($patient['full_name']) ?></h2><div class="action-stack"><a class="btn primary" href="transactions.php?movement=Stock%20Out&patient_id=<?= $id ?>#record-movement">Give medicine</a><a class="btn secondary" href="patients.php">Back to Patients</a></div></div>
<p><?= h($patient['patient_type']) ?> &middot; Student / Employee ID: <?= h($patient['student_no'] ?? 'Not provided') ?></p>
<?php if($patient['patient_type']==='Student'): ?><p><a href="assessments.php?student_id=<?= $id ?>">Student pre-assessments</a></p><?php endif; ?>
<h3 id="patient-medicine-history">Medicines given</h3>
<?php if(!$given): ?><p>No medicines have been linked to this patient. Older unlinked stock records are not assigned automatically.</p><?php else: ?>
<table><thead><tr><th>Date</th><th>Medicine</th><th>Quantity</th><th>Recorded by</th><th>Source</th></tr></thead><tbody>
<?php foreach($given as $issue): ?><tr><td><?= h($issue['transaction_date']) ?></td><td><?= h($issue['medicine_name']) ?></td><td><?= (int)$issue['quantity'] ?> <?= h($issue['unit']) ?></td><td><?= h($issue['issued_by_name']) ?></td><td><?php if($issue['consultation_id']): ?><a href="consultation.php?visit_id=<?= (int)$issue['consultation_id'] ?>">Consultation #<?= (int)$issue['consultation_id'] ?></a><?php else: ?>Manage Inventory<?php endif; ?></td></tr><?php endforeach; ?></tbody></table>
<?php endif; ?>
<?php if($givenPager['pages']>1): ?><nav class="list-pagination" aria-label="Patient medicine pages"><span>Page <?= $givenPager['page'] ?> of <?= $givenPager['pages'] ?></span><?php foreach(['Previous'=>-1,'Next'=>1] as $label=>$delta):$target=$givenPager['page']+$delta;if($target<1||$target>$givenPager['pages'])continue; ?><a class="btn secondary" href="?id=<?= $id ?>&medicine_page=<?= $target ?>#patient-medicine-history"><?= $label ?></a><?php endforeach; ?></nav><?php endif; ?>
<h3>Visit history</h3>
<?php if(!$visits): ?><p>No clinic visits have been recorded for this patient.</p><?php else: ?>
<table><thead><tr><th>Visit</th><th>Started</th><th>Status</th><th>Completed</th><th>Action</th></tr></thead><tbody>
<?php foreach($visits as $visit): ?><tr><td>#<?= (int)$visit['id'] ?></td><td><?= h($visit['started_at']) ?></td><td><?= h($visit['status']) ?></td><td><?= h($visit['completed_at'] ?? 'Not yet completed') ?></td><td><a class="btn small secondary" href="consultation.php?visit_id=<?= (int)$visit['id'] ?>">View visit</a></td></tr><?php endforeach; ?>
</tbody></table>
<?php endif; ?>
<?php if($pager['pages']>1): ?><nav class="list-pagination" aria-label="Patient visit pages"><span>Page <?= $pager['page'] ?> of <?= $pager['pages'] ?></span><?php foreach(['Previous'=>-1,'Next'=>1] as $label=>$delta): $target=$pager['page']+$delta;if($target<1||$target>$pager['pages'])continue; ?><a class="btn secondary" href="?id=<?= $id ?>&page=<?= $target ?>"><?= $label ?></a><?php endforeach; ?></nav><?php endif; ?>
</section>
<?php include __DIR__.'/includes/footer.php'; ?>
