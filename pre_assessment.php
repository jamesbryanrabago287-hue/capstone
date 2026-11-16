<?php
require_once __DIR__.'/includes/auth.php';require_once __DIR__.'/includes/symptom_intake.php';
header('Cache-Control: no-store');header('Referrer-Policy: same-origin');
$intakeToken=is_string($_GET['draft'] ?? null)?$_GET['draft']:'';$intakeDraft=null;$intakeError='';
if($intakeToken!=='')try{$intakeDraft=symptom_intake_load($intakeToken,true);}catch(InvalidArgumentException $e){http_response_code(410);$intakeError=$e->getMessage();}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Student symptom check | Campus Clinic</title><link rel="stylesheet" href="assets/style.css"><link rel="stylesheet" href="assets/chatbot.css?v=<?= (int)filemtime(__DIR__.'/assets/chatbot.css') ?>"></head>
<body data-open-assessment="yes" data-assessment-layout="inline" <?= $intakeDraft?'data-assessment-stage="preassessment"':'' ?>>
<main class="assessment-public"><div class="assessment-page-heading"><div><span class="eyebrow">ISAT U CAMPUS CLINIC</span><h1>How are you feeling?</h1><p class="assessment-intro">A few questions. A summary for your clinic visit.</p></div><a href="login.php">Staff sign in &rarr;</a></div>
<?php if($intakeError!==''): ?><p role="alert"><?= h($intakeError) ?></p><a href="pre_assessment.php">Start again</a>
<?php else: include __DIR__.'/includes/chatbot.php'; endif; ?>
<p class="assessment-footnote">Student complaint intake &middot; Prototype for clinic review</p>
</main><noscript><p class="assessment-public">Enable JavaScript or contact the clinic directly. Do not delay urgent care.</p></noscript></body></html>
