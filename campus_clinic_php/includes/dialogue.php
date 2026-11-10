<?php
require_once __DIR__.'/assessment.php';
require_once __DIR__.'/chatbot_api.php';
require_once __DIR__.'/dialogue_progress.php';
require_once __DIR__.'/triage_prompt.php';
function dialogue_load($id) {
    $s=$_SESSION['_dialogue'] ?? null;
    if(!is_string($id) || !$s || !hash_equals($s['id'],$id) || time()-$s['at']>1800) throw new InvalidArgumentException('This conversation expired or changed in another tab. Start again.');
    return $s;
}
function dialogue_quota() {
    $recent=array_values(array_filter($_SESSION['_ai_chat_requests'] ?? [],function($at){return $at>time()-60;}));
    if(count($recent)>=20) throw new RuntimeException('Please wait a minute before sending another message.',503);
    $_SESSION['_ai_chat_requests']=array_merge($recent,[time()]);
}
function dialogue_schema() {
    return ['type'=>'object','additionalProperties'=>false,'properties'=>['reply'=>['type'=>'string'],'safety'=>['type'=>'string','enum'=>['none','urgent','emergency']],'safety_evidence'=>['type'=>'string'],'facts'=>dialogue_fact_schema()['properties']['facts']],'required'=>['reply','safety','safety_evidence','facts']];
}
function dialogue_messages(array $history,array $state=[]) {
    $system=clinic_triage_prompt();
    if($state){$catalog=[];foreach(assessment_questions() as $q)$catalog[$q['key']]=$q['options'];$system.=' Also return facts explicitly supported in the CURRENT student message as key/value/evidence, using this catalog: '.json_encode($catalog).'. Evidence must be a quote from the CURRENT student message; do not extract from your own questions or assume absent facts are negative. Omit unclear facts. Known facts: '.json_encode($state['answers'] ?? []).'. The detail currently being asked is: '.($state['pending'] ?? 'symptom').'.';}
    return array_merge([['role'=>'system','content'=>$system],['role'=>'user','content'=>'Hello'],['role'=>'assistant','content'=>json_encode(['reply'=>'Hi! What is bothering you today?','safety'=>'none','safety_evidence'=>'','facts'=>[]])]],array_slice($history,-10));
}
function dialogue_validate_reply(array $data,$message) {
    if(!is_string($data['reply'] ?? null) || !in_array($data['safety'] ?? null,['none','urgent','emergency'],true) || !is_string($data['safety_evidence'] ?? null)) throw new RuntimeException('The local model could not respond clearly. Please retry or use the answer choices.',502);
    $reply=trim($data['reply']);
    if($reply==='' || mb_strlen($reply)>1200) throw new RuntimeException('The local model could not respond clearly. Please retry.',502);
    $safety=$data['safety']; $needsReview=false;
    if($safety!=='none' && (trim($data['safety_evidence'])==='' || strpos($message,$data['safety_evidence'])===false || !preg_match('/\\b(severe|cannot|unable|can.t|collapse|faint|bleed|blue|swelling|paralysis|unconscious)\\b/i',$data['safety_evidence']))) { $safety='none'; $needsReview=true; }
    // Defense in depth, not a guarantee of clinically safe generated language.
    if(preg_match('/\b(you (?:have|likely have|probably have|are suffering)|sounds like|probably (?:a|an)|diagnosis is|take \d|ibuprofen|paracetamol|acetaminophen|antibiotics?|no need to (?:visit|go)|do not need (?:a|the) clinic)\b/i',$reply)) $reply='I cannot determine the cause or recommend treatment here. Could you describe how the discomfort is affecting your usual activities?';
    return ['reply'=>$reply,'safety'=>$safety,'needs_review'=>$needsReview];
}
function dialogue_explicit_warning($message) {
    if(preg_match('/\bI (?:cannot|can.t|am unable to) breathe\b/i',$message))return 'emergency';
    if(preg_match('/^\s*(?:I\s+)?(?:cannot|can.t|cant|can not|am unable to)\s+walk\b/i',$message))return 'urgent';
    return 'none';
}
function dialogue_followup_fallback(array $history) {
    $texts=[];foreach($history as $m)if($m['role']==='user')$texts[]=$m['content'];$text=implode(' ',$texts);
    if(preg_match('/\b(foot|feet|ankle)\b/i',$text)) {
        if(!preg_match('/\b(both|one|left|right)\b/i',$text))return 'Is the pain in one foot or both feet?';
        if(!preg_match('/\b(yesterday|today|since|hours?|days?|weeks?|minutes?)\b/i',$text))return 'When did the pain start?';
        if(!preg_match('/\\b(?:can walk|walk normally|walking normally|cannot walk|can.t walk)\\b/i',$text))return 'Are you able to walk normally, or is the pain stopping you?';
        if(!preg_match('/\\b(swelling|swollen|redness|numb|blue)\\b/i',$text))return 'Have you noticed any swelling, redness, numbness or change in skin colour?';
        return 'How much is the pain affecting your usual activities?';
    }
    return 'How is this affecting your usual activities?';
}
function dialogue_context_reply($reply,array $history) {
    $texts=[];foreach($history as $m)if($m['role']==='user')$texts[]=$m['content'];$text=implode(' ',$texts);
    $timingKnown=preg_match('/\b(yesterday|today|since|\d+\s*(?:hours?|days?|weeks?|minutes?))\b/i',$text);
    if($timingKnown && preg_match('/\b(when did|when does|how long|when .*start)\b/i',$reply))return 'I have the timing you mentioned. '.dialogue_followup_fallback($history);
    if(preg_match('/\b(both feet|left foot|right foot)\b/i',$text) && preg_match('/\b(one foot or both|which foot|which feet)\b/i',$reply))return dialogue_followup_fallback($history);
    return $reply;
}
function dialogue_fact_schema() {
    $keys=[];$values=[];
    foreach(assessment_questions() as $q){$keys[]=$q['key'];foreach(array_keys($q['options']) as $v)$values[$v]=true;}
    $fact=['type'=>'object','additionalProperties'=>false,'properties'=>['key'=>['type'=>'string','enum'=>$keys],'value'=>['type'=>'string','enum'=>array_keys($values)],'evidence'=>['type'=>'string']],'required'=>['key','value','evidence']];
    return ['type'=>'object','additionalProperties'=>false,'properties'=>['facts'=>['type'=>'array','items'=>$fact]],'required'=>['facts']];
}
function dialogue_validate_facts(array $facts,array $history,array $known=[]) {
    $catalog=[];foreach(assessment_questions() as $q)$catalog[$q['key']]=$q;
    $userText=[];foreach($history as $m)if($m['role']==='user')$userText[]=$m['content'];
    $answers=[];$blocked=[];
    foreach($facts as $fact){
        if(!is_array($fact) || !is_string($fact['key'] ?? null) || !is_string($fact['value'] ?? null) || !is_string($fact['evidence'] ?? null))continue;
        $key=$fact['key'];$value=$fact['value'];$quote=trim($fact['evidence']);
        if(!isset($catalog[$key]['options'][$value]) || $quote==='')continue;
        $supported=false;foreach($userText as $text)if(strpos($text,$quote)!==false){$supported=true;break;}
        if(!$supported)continue;
        // Contradictory extractions require human review, never last-write reassurance.
        if(isset($answers[$key]) && $answers[$key]!==$value){unset($answers[$key]);$blocked[$key]=true;continue;}
        if(!empty($blocked[$key]))continue;
        $answers[$key]=$value;
    }
    foreach($catalog as $key=>$q)if(!assessment_question_applies($q,array_merge($known,$answers)))unset($answers[$key]);
    return $answers;
}
function dialogue_result(array $state,array $answers) {
    if($state['safety']==='emergency')return assessment_evaluate(['warning'=>'yes']);
    if($state['safety']==='urgent')return assessment_evaluate(['warning'=>'unsure']);
    foreach(assessment_questions() as $q){$v=$answers[$q['key']] ?? null;if(assessment_question_applies($q,$answers) && is_string($v) && isset($q['stop'][$v]))return assessment_evaluate($answers);}
    if(!empty($state['needs_review']))return assessment_result('clinic','Some conversation details or model interpretations could not be verified. Please discuss them with clinic staff.',['Conversation needs human review; no automatic reassurance.']);
    try{return assessment_evaluate($answers);}
    catch(InvalidArgumentException $e){return assessment_result('clinic','The conversation has not established all the information needed for an automated care recommendation. Please discuss the missing or uncertain details with clinic staff.',['Conversation intake is incomplete; missing facts were not assumed negative.']);}
}
function dialogue_action($action) {
    if($action==='dialogue_start'){
        if(($_POST['ai_consent'] ?? '')!=='yes')throw new InvalidArgumentException('Please review and agree to AI processing first.');
        clinic_ai_validate_settings();$id=bin2hex(random_bytes(24));
        $_SESSION['_dialogue']=['id'=>$id,'at'=>time(),'history'=>[],'safety'=>'none','needs_review'=>false,'review'=>null,'answers'=>[],'asked'=>[],'pending'=>'symptom','turn_count'=>0];
        return ['dialogue_id'=>$id,'reply'=>'Hi! What is bothering you today? You can describe it in your own words.'];
    }
    $s=dialogue_load($_POST['dialogue_id'] ?? null);
    if($action==='dialogue_turn'){
        $message=$_POST['message'] ?? null;
        if(!is_string($message) || trim($message)==='' || mb_strlen($message)>500)throw new InvalidArgumentException('Type a message of up to 500 characters.');
        if(count($s['history'])>=40)throw new InvalidArgumentException('This conversation reached its limit. Review the summary or contact the clinic.');
        dialogue_quota();$history=$s['history'];$history[]=['role'=>'user','content'=>trim($message)];
        $explicit=dialogue_explicit_warning($message);
        $s['turn_count']=($s['turn_count'] ?? 0)+1;
        $key=$_POST['answer_key'] ?? null;$value=$_POST['answer_value'] ?? null;$direct=false;
        if(is_string($key) && is_string($value) && $key===($s['pending'] ?? 'symptom')) {
            foreach(assessment_questions() as $q)if($q['key']===$key && isset($q['options'][$value]) && trim($message)===$q['options'][$value]) {$s['answers'][$key]=$value;$direct=true;break;}
        }
        $facts=[];
        if($explicit!=='none')$r=['reply'=>'','safety'=>$explicit,'needs_review'=>false];
        elseif($direct)$r=['reply'=>'Thanks, I have noted that.','safety'=>'none','needs_review'=>false];
        elseif(clinic_ai_settings()['provider']==='groq') {
            $data=clinic_ollama_json(dialogue_messages($history,$s),dialogue_schema(),800);
            $r=dialogue_validate_reply($data,trim($message));
            $facts=is_array($data['facts'] ?? null)?$data['facts']:[];
        }
        else {
            // The 1B model is more reliable with one focused schema than a whole intake form.
            $pending=null;foreach(assessment_questions() as $q)if($q['key']===($s['pending'] ?? 'symptom'))$pending=$q;
            if(!$pending)throw new InvalidArgumentException('The pending assessment question is invalid. Please restart.');
            $parsed=clinic_ai_interpret($pending,trim($message));
            $r=['reply'=>'Thanks for explaining.','safety'=>$parsed['safety'],'needs_review'=>$parsed['needs_review']];
            if($parsed['option']!=='unclassified')$facts[]=['key'=>$pending['key'],'value'=>$parsed['option'],'evidence'=>trim($message)];
        }
        $s['answers']=dialogue_merge_answers($s['answers'] ?? [],$facts,trim($message));
        $explicit=dialogue_explicit_warning($message);
        if($explicit==='emergency' || ($explicit==='urgent' && $r['safety']==='none'))$r['safety']=$explicit;
        $s['needs_review']=!empty($s['needs_review']) || $r['needs_review'];
        $lastAssistant='';foreach($s['history'] as $item)if($item['role']==='assistant')$lastAssistant=$item['content'];
        if(strcasecmp(trim($r['reply']),trim($message))===0 || ($lastAssistant!=='' && strcasecmp(trim($r['reply']),trim($lastAssistant))===0)) $r['reply']=dialogue_followup_fallback($history);
        $r['reply']=dialogue_context_reply($r['reply'],$history);
        // Keep the first question only when a small model generates a questionnaire.
        $questionAt=strpos($r['reply'],'?');if($questionAt!==false)$r['reply']=substr($r['reply'],0,$questionAt+1);
        if($r['safety']==='emergency' || ($r['safety']==='urgent' && $s['safety']!=='emergency'))$s['safety']=$r['safety'];
        if($s['safety']!=='none')$r['reply']=assessment_evaluate(['warning'=>$s['safety']==='emergency'?'yes':'unsure'])['advice'];
        $s['history']=$history;$s['review']=null;
        $progress=dialogue_progress($s);$review=null;$assessment=null;
        $triage=null;
        if($progress['ready']) {
            $assessment=dialogue_result($s,$s['answers']);
            if(in_array($assessment['priority'],['urgent','emergency'],true))$s['safety']=$assessment['priority'];
            $triage=clinic_triage_output($assessment,$s['answers']);
            $r['reply']=$triage['conversational_response'];
        } else {
            $next=$progress['next'];$s['pending']=$next['key'];$s['asked'][$next['key']]=($s['asked'][$next['key']] ?? 0)+1;
            if(clinic_ai_settings()['provider']!=='groq' || $direct || strpos($r['reply'],'?')===false) $r['reply']=($facts || $direct ? 'Thanks, I have noted that. ' : 'Let me check one detail. ').$next['text'];
        }
        $s['history'][]=['role'=>'assistant','content'=>$r['reply']];$_SESSION['_dialogue']=$s;
        return ['reply'=>$r['reply'],'safety'=>$s['safety'],'ready'=>$progress['ready'],'review'=>$review,'assessment'=>$assessment,'triage'=>$triage,'phase'=>$progress['ready']?'COMPLETE':'NEEDS_MORE_INFORMATION','next_question'=>(clinic_ai_settings()['provider']==='groq' && !$direct ? null : $progress['next'])];
    }
    if($action==='dialogue_review'){
        if(!$s['history'])throw new InvalidArgumentException('Tell me what is bothering you before requesting guidance.');
        $review=dialogue_prepare_review($s);$_SESSION['_dialogue']=$s;return $review;
    }
    if($action==='dialogue_confirm'){
        $token=$_POST['review_token'] ?? null;
        if(!is_string($token) || !$s['review'] || !hash_equals($s['review']['token'],$token) || ($_POST['confirmed'] ?? '')!=='yes')throw new InvalidArgumentException('Review and confirm the current summary first.');
        $result=dialogue_result($s,$s['review']['answers']);$result['summary'].="\n\nStudent-confirmed conversation summary:\n".json_encode($s['review']['answers'],JSON_INVALID_UTF8_SUBSTITUTE)."\n\nConversation transcript:";
        foreach($s['history'] as $m)$result['summary'].="\n".ucfirst($m['role']).': '.$m['content'];
        $_SESSION['_assessment']=['result'=>$result,'at'=>time(),'saved_id'=>null,'token'=>bin2hex(random_bytes(24))];
        return ['result'=>$result,'assessment_token'=>$_SESSION['_assessment']['token']];
    }
    throw new InvalidArgumentException('Unknown conversation action.');
}
