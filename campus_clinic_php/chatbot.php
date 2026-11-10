<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/assessment.php';
require_once __DIR__ . '/includes/consultations.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
function chat_response(array $data, $status=200) { http_response_code($status); echo json_encode($data,JSON_INVALID_UTF8_SUBSTITUTE); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Allow: POST'); chat_response(['error'=>'Use the assessment form.'],405); }
$token = $_POST['csrf'] ?? '';
if (!is_string($token) || !hash_equals(csrf_token(),$token)) chat_response(['error'=>'Your session expired. Refresh the page and start again.'],403);
$action = $_POST['action'] ?? '';
try {
    if(in_array($action,['chat_start','interpret','dialogue_start','dialogue_turn','dialogue_review','dialogue_confirm'],true)){
        require_once __DIR__.'/includes/chatbot_api.php';
        if(($_POST['ai_provider'] ?? 'ollama')!==clinic_ai_settings()['provider'])throw new InvalidArgumentException('The AI provider changed. Refresh and start again to review the current consent notice.');
    }
    if (in_array($action,['dialogue_start','dialogue_turn','dialogue_review','dialogue_confirm'],true)) {
        require_once __DIR__.'/includes/dialogue.php';
        chat_response(dialogue_action($action));
    }
    if (in_array($action,['chat_start','interpret'],true)) {
        require_once __DIR__.'/includes/conversation.php';
        chat_response(conversation_action($action));
    }
    if ($action === 'assess') {
        $raw = $_POST['answers'] ?? '';
        if (!is_string($raw) || strlen($raw)>5000) throw new InvalidArgumentException('Invalid answers.');
        $answers = json_decode($raw,true);
        if (!is_array($answers)) throw new InvalidArgumentException('Please complete the assessment.');
        if (!empty($_POST['chat_id'])) {
            require_once __DIR__.'/includes/conversation.php';
            $result=conversation_attach($answers,$_POST['chat_id']);
        } else { $result=assessment_evaluate($answers); }
        $_SESSION['_assessment'] = ['result'=>$result,'at'=>time(),'saved_id'=>null,'token'=>bin2hex(random_bytes(24))];
        chat_response(['result'=>$result,'assessment_token'=>$_SESSION['_assessment']['token']]);
    }
    if ($action === 'save') {
        $draft = $_SESSION['_assessment'] ?? null;
        if (!$draft || time()-$draft['at']>1800) throw new InvalidArgumentException('This assessment expired. Please start again.');
        $draftToken=$_POST['assessment_token'] ?? '';
        if (!is_string($draftToken) || !hash_equals($draft['token'],$draftToken)) throw new InvalidArgumentException('The assessment changed in another tab. Start again before sharing.');
        if (($draft['saved_id'] ?? null)!==null) chat_response(assessment_receipt($draft['saved_id']));
        if (($_POST['consent'] ?? '')!=='yes') throw new InvalidArgumentException('Please agree to share this assessment with authorized clinic staff.');
        $name = $_POST['student_name'] ?? ''; $number = $_POST['student_no'] ?? '';
        if (!is_string($name) || !is_string($number) || mb_strlen($name)>120 || mb_strlen($number)>50) throw new InvalidArgumentException('Please check your name and student number.');
        $name=trim($name); $number=trim($number);
        if ($name==='' || $number==='') throw new InvalidArgumentException('Name and student number are required only when sharing with the clinic.');
        $recent = array_filter($_SESSION['_assessment_saves'] ?? [],function($at){return $at>time()-3600;});
        if (count($recent)>=5) chat_response(['error'=>'Submission limit reached. Please contact the clinic directly.'],429);
        ensure_assessment_tables($pdo); $r=$draft['result'];
        $stmt=$pdo->prepare('INSERT INTO chatbot_sessions(student_no,student_name,symptoms,assessment,advice) VALUES(?,?,?,?,?)');
        $stmt->execute([$number,$name,$r['summary'],$r['assessment'],$r['advice']]);
        $id=(int)$pdo->lastInsertId(); $_SESSION['_assessment']['saved_id']=$id;
        $recent[]=time(); $_SESSION['_assessment_saves']=$recent;
        chat_response(assessment_receipt($id));
    }
    throw new InvalidArgumentException('Unknown assessment action.');
} catch (InvalidArgumentException $e) { chat_response(['error'=>$e->getMessage()],400); }
catch (RuntimeException $e) {
    if (in_array($e->getCode(),[502,503],true)) chat_response(['error'=>$e->getMessage()],$e->getCode());
    error_log('Assessment failed: '.get_class($e)); chat_response(['error'=>'The clinic service is unavailable. Please contact the clinic directly.'],503);
}
catch (Throwable $e) { error_log('Assessment failed: '.get_class($e)); chat_response(['error'=>'Clinic service unavailable. Your submission was not confirmed. Retry or contact the clinic directly; do not delay urgent care.'],503); }
