<?php
require __DIR__.'/../includes/conversation.php';
function ok($value,$label){if(!$value){fwrite(STDERR,"FAIL: $label\n");exit(1);}echo "PASS: $label\n";}
$q=conversation_question('symptom');
$valid=['option'=>'headache','evidence'=>'my head hurts','safety'=>'none','safety_evidence'=>'','needs_review'=>false];
ok(clinic_ai_parse_interpretation($valid,$q,'my head hurts')['option']==='headache','supported option accepted');
$bad=$valid;$bad['evidence']='invented quote';ok(clinic_ai_parse_interpretation($bad,$q,'my head hurts')['option']==='unclassified','unsupported inference cannot clear an answer');
$bad=$valid;$bad['safety']='emergency';$bad['safety_evidence']='invented';ok(clinic_ai_parse_interpretation($bad,$q,'my head hurts')['safety']==='urgent','unverified safety output requests human assessment');
$bad=$valid;$bad['option']='diagnosis';try{clinic_ai_parse_interpretation($bad,$q,'my head hurts');ok(false,'invalid option');}catch(RuntimeException $e){ok(true,'out-of-schema options rejected');}
$payload=clinic_ai_interpret_payload($q,'my head hurts');ok($payload['store']===false && $payload['text']['format']['strict']===true,'shared schema definition remains strict');
ok(!isset($payload['tools']) && strpos($payload['input'],'medicine_records')===false,'no tools or inventory context');
$_SESSION=['_free_chat'=>['id'=>'synthetic-session','at'=>time(),'turns'=>[],'needs_review'=>false,'safety'=>'none']];
$a=['warning'=>'no','age'=>'adult','risk'=>'no','symptom'=>'headache','headache_flags'=>'none','headache_pattern'=>'familiar','duration'=>'today','severity'=>'mild','trend'=>'improving','hydration'=>'normal'];
ok(conversation_attach($a,'synthetic-session')['priority']==='self_care','rules still determine outcome');
$_SESSION['_free_chat']['needs_review']=true;ok(conversation_attach($a,'synthetic-session')['priority']==='clinic','unmapped concerns prevent self-care');
$_SESSION['_free_chat']['safety']='urgent';ok(conversation_attach($a,'synthetic-session')['priority']==='urgent','AI can escalate concern');
ok(conversation_attach(['warning'=>'yes'],'synthetic-session')['priority']==='emergency','AI concern cannot downgrade explicit emergency');
$_SESSION['_free_chat']['safety']='emergency';ok(conversation_attach($a,'synthetic-session')['priority']==='emergency','AI emergency remains escalated');
try{conversation_session('wrong-token');ok(false,'wrong chat token');}catch(InvalidArgumentException $e){ok(true,'cross-tab chat token rejected');}
$_SESSION['_free_chat']['at']=time()-1801;try{conversation_session('synthetic-session');ok(false,'expired chat');}catch(InvalidArgumentException $e){ok(true,'expired chat rejected');}
ok(clinic_ai_settings()['model']===(getenv('OLLAMA_MODEL') ?: 'llama3.2:3b'),'local model configuration');
ok(!isset(clinic_ai_settings()['api_key']),'local provider needs no key');
echo "AI adapter tests passed without contacting the model. They do not validate clinical interpretation.\n";
