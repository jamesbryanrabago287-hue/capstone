<?php
require_once __DIR__.'/includes/auth.php';
header('Cache-Control: no-store'); header('Referrer-Policy: same-origin');
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Student Symptom Check | Campus Clinic</title><link rel="stylesheet" href="assets/style.css"><link rel="stylesheet" href="assets/chatbot.css"></head>
<body data-open-assessment="yes"><main class="assessment-public panel"><span class="eyebrow">ISAT U Campus Clinic</span><h1>Student Symptom Check</h1>
<p>Feeling unwell but unsure whether you need to visit the clinic? Start by telling us what is bothering you. Answer symptom-specific questions to receive preliminary guidance about seeking care or, for selected mild adult symptoms, self-care and monitoring. No name, student number or submission is required.</p>
<p><strong>Prototype for clinic review.</strong> It does not diagnose illness, prescribe medicines, book appointments, or dispatch emergency help. If you may be having an emergency, seek medical help immediately.</p>
<p>Use the Student symptom check button to begin. No staff account is needed. Start by typing your main concern or choosing symptoms from a list. After AI consent, describe your symptoms and answer relevant follow-up questions. The check ends with preliminary care advice when enough information is available. Guided choices remain available. Choose Other for an unlisted symptom. Choices work without an AI provider. The check ends after guidance. It does not create a student record or send a submission to staff.</p><p><a href="login.php">Clinic staff sign in</a></p></main>
<?php include __DIR__.'/includes/chatbot.php'; ?>
<noscript><p class="assessment-public">Enable JavaScript to use the questionnaire, or visit the clinic directly. Do not wait for this service in an emergency.</p></noscript></body></html>
