<?php
require_once __DIR__.'/assessment.php';
require_once __DIR__.'/chatbot_api.php';
function conversation_question($key) {
    foreach (assessment_questions() as $q) if ($q['key']===$key) return $q;
    throw new InvalidArgumentException('Unknown follow-up question. Please restart the chat.');
}
function conversation_session($id) {
    $s=$_SESSION['_free_chat'] ?? null;
    if (!is_string($id) || !$s || !hash_equals($s['id'],$id) || time()-$s['at']>1800) throw new InvalidArgumentException('This chat expired or changed in another tab. Please restart it.');
    return $s;
}
function conversation_action($action) {
    if ($action==='chat_start') {
        if (($_POST['ai_consent'] ?? '')!=='yes') throw new InvalidArgumentException('Please agree before AI processes your typed symptom replies.');
        clinic_ai_validate_settings();
        $id=bin2hex(random_bytes(24));
        $_SESSION['_free_chat']=['id'=>$id,'at'=>time(),'turns'=>[],'needs_review'=>false,'safety'=>'none'];
        return ['chat_id'=>$id];
    }
    $s=conversation_session($_POST['chat_id'] ?? null);
    if ($action!=='interpret') throw new InvalidArgumentException('Unknown chat action.');
    $key=$_POST['question'] ?? null;
    if (!is_string($key)) throw new InvalidArgumentException('Invalid question.');
    $q=conversation_question($key);$message=$_POST['message'] ?? null;
    if (!is_string($message) || trim($message)==='' || mb_strlen($message,'UTF-8')>500 || strlen($message)>2000) throw new InvalidArgumentException('Type a message of up to 500 characters.');
    if (count($s['turns'])>=30) throw new InvalidArgumentException('This chat reached its message limit. Please contact the clinic or start a new questionnaire.');
    $recent=array_values(array_filter($_SESSION['_ai_chat_requests'] ?? [],function($at){return $at>time()-60;}));
    if (count($recent)>=20) throw new RuntimeException('Please wait a minute before sending another chat message.',503);
    $_SESSION['_ai_chat_requests']=array_merge($recent,[time()]);
    $result=clinic_ai_interpret($q,trim($message));
    $s['turns'][]=['question'=>$q['text'],'message'=>trim($message),'suggested_option'=>$result['option']];
    $s['needs_review']=$s['needs_review'] || $result['needs_review'];
    if ($result['safety']==='emergency' || ($result['safety']==='urgent' && $s['safety']!=='emergency')) $s['safety']=$result['safety'];
    $_SESSION['_free_chat']=$s;
    return ['interpretation'=>$result];
}
function conversation_attach(array $answers,$id) {
    $s=conversation_session($id);
    if ($s['safety']==='emergency') $answers=['warning'=>'yes'];
    elseif ($s['safety']==='urgent' && ($answers['warning'] ?? '')!=='yes') $answers=['warning'=>'unsure'];
    elseif ($s['needs_review']) {
        if (isset($answers['details']) && !is_string($answers['details'])) throw new InvalidArgumentException('Invalid additional notes.');
        $answers['details']=mb_substr(trim($answers['details'] ?? ''),0,400).' Additional concerns in free-text messages require clinic review.';
    }
    $result=assessment_evaluate($answers);
    if ($s['turns']) {
        $result['summary'].="\n\nFree-text chat (student messages; AI suggestions may be unconfirmed and are not clinically verified):";
        foreach ($s['turns'] as $turn) $result['summary'].="\nQuestion: ".$turn['question']."\nStudent: ".$turn['message']."\nAI suggested option: ".$turn['suggested_option'];
    }
    $result['rule_version']='draft-3-ai-intake';
    return $result;
}
