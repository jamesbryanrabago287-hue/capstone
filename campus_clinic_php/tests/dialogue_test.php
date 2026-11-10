<?php
require __DIR__.'/../includes/dialogue.php';
function check($value,$label){if(!$value){fwrite(STDERR,"FAIL: $label\n");exit(1);}echo "PASS: $label\n";}
$history=[['role'=>'user','content'=>'my feet hurt'],['role'=>'assistant','content'=>'Is it one foot or both?'],['role'=>'user','content'=>'both feet since yesterday']];
$m=dialogue_messages($history);check(count($m)===6 && $m[3]['content']==='my feet hurt','recent conversation retained with roles');
$facts=[['key'=>'symptom','value'=>'foot','evidence'=>'my feet hurt'],['key'=>'foot_side','value'=>'both','evidence'=>'both feet'],['key'=>'risk','value'=>'no','evidence'=>'invented quote'],['key'=>'duration','value'=>'days','evidence'=>'since yesterday']];
$a=dialogue_validate_facts($facts,$history);check(($a['symptom'] ?? '')==='foot' && ($a['foot_side'] ?? '')==='both','supported symptom facts retained');check(!isset($a['risk']),'invented reassurance discarded');
$bad=[['key'=>'warning','value'=>'no','evidence'=>'Is it one foot or both?']];check(!dialogue_validate_facts($bad,$history),'assistant questions never become patient facts');
$conflict=[['key'=>'foot_side','value'=>'both','evidence'=>'both feet'],['key'=>'foot_side','value'=>'one','evidence'=>'both feet']];check(!isset(dialogue_validate_facts($conflict,$history)['foot_side']),'conflicting facts remain unknown');
check(dialogue_result(['safety'=>'none'],[])['priority']==='clinic','incomplete chat cannot generate self-care');
check(dialogue_result(['safety'=>'emergency'],[])['priority']==='emergency','emergency overrides missing fields');
check(dialogue_result(['safety'=>'urgent'],[])['priority']==='urgent','urgent concern is not cleared');
$r=dialogue_validate_reply(['reply'=>'Is it one foot or both?','safety'=>'none','safety_evidence'=>''],'my feet hurt');check($r['reply']==='Is it one foot or both?','ordinary follow-up retained');
$r=dialogue_validate_reply(['reply'=>'You probably have a sprain.','safety'=>'none','safety_evidence'=>''],'my feet hurt');check(strpos($r['reply'],'cannot determine')!==false,'obvious diagnosis phrasing replaced');
$r=dialogue_validate_reply(['reply'=>'Seek help.','safety'=>'emergency','safety_evidence'=>'invented'],'my feet hurt');check($r['safety']==='none' && $r['needs_review']===true,'unsupported safety signal requires review without an invented urgency finding');
$_SESSION=['_dialogue'=>['id'=>'test','at'=>time(),'history'=>$history,'safety'=>'none','review'=>null]];
check(dialogue_load('test')['history']===$history,'server conversation state retained');
try{dialogue_load('wrong');check(false,'cross-session token');}catch(InvalidArgumentException $e){check(true,'wrong conversation token rejected');}
$_SESSION['_dialogue']['at']=time()-1801;try{dialogue_load('test');check(false,'expiry');}catch(InvalidArgumentException $e){check(true,'expired conversation rejected');}
check(dialogue_explicit_warning('I cannot breathe')==='emergency','explicit breathing warning remains escalated');
check(dialogue_explicit_warning('I already told you yesterday')==='none','ordinary correction is not a medical warning');
check(strpos(dialogue_followup_fallback($history),'walk normally')!==false,'foot follow-up uses earlier context');
check(stripos(dialogue_context_reply('When did the pain start?',$history),'when did')===false,'answered onset question is not repeated');
echo "Conversation tests passed without model calls. They do not establish clinical safety.\n";
