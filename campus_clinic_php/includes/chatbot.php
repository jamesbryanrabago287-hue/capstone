<?php require_once __DIR__ . '/assessment.php'; require_once __DIR__ . '/chatbot_api.php'; ?>
<link rel="stylesheet" href="assets/chatbot.css?v=<?= (int)filemtime(__DIR__ . '/../assets/chatbot.css') ?>">
<button type="button" id="clinic-chat-toggle" aria-expanded="false" aria-controls="clinic-chat">Student symptom check</button>
<section id="clinic-chat" aria-label="Student pre-diagnostic assessment" hidden data-csrf="<?= h(csrf_token()) ?>">
<div class="clinic-chat-heading"><div><strong>Student Symptom Check</strong><small>Not sure whether to visit the clinic?</small></div><button type="button" id="clinic-chat-close" aria-label="Close assessment">&times;</button></div>
<p class="clinic-chat-notice">If you may be having an emergency, seek immediate medical help. Do not wait to finish this chat.</p><div id="clinic-chat-messages" role="log" aria-live="polite" aria-relevant="additions" tabindex="0"></div>
<div id="clinic-chat-controls"></div>
<div class="clinic-chat-bottom"><button type="button" id="clinic-chat-restart">Start over</button><small>Not monitored for emergencies.</small></div>
</section>
<script type="application/json" id="clinic-ai-config"><?= json_encode(['provider'=>clinic_ai_settings()['provider'],'disclosure'=>clinic_ai_disclosure()],JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?></script>
<script type="application/json" id="clinic-chat-questions"><?= json_encode(assessment_intake_questions(),JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?></script>
<script src="assets/chatbot.js?v=<?= (int)filemtime(__DIR__ . '/../assets/chatbot.js') ?>" defer></script>
