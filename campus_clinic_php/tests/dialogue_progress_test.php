<?php
require __DIR__.'/../includes/dialogue.php';
function check($ok,$label){if(!$ok){fwrite(STDERR,"FAIL: $label\n");exit(1);}echo "PASS: $label\n";}
$complete=['symptom'=>'headache','headache_flags'=>'none','headache_pattern'=>'familiar','warning'=>'no','age'=>'adult','risk'=>'no','duration'=>'today','severity'=>'mild','trend'=>'improving','hydration'=>'normal'];
$s=['answers'=>[],'asked'=>[],'turn_count'=>0,'safety'=>'none','history'=>[]];
$p=dialogue_progress($s);check(!$p['ready']&&$p['next']['key']==='symptom','begin with main concern');
$s['answers']=$complete;check(dialogue_progress($s)['ready'],'all required details finish automatically');
$missing=$complete;unset($missing['warning']);$s['answers']=$missing;check(!dialogue_progress($s)['ready'],'missing warning answer prevents automatic reassurance');
$s['answers']=['symptom'=>'foot','foot_flags'=>'none','warning'=>'no','duration'=>'days','severity'=>'moderate'];check(dialogue_progress($s)['reason']==='human_review','foot severity intake reaches clinic review without endless questions');
$s['answers']=['symptom'=>'foot','foot_flags'=>'emergency'];check(dialogue_progress($s)['reason']==='warning','positive danger stops intake');
check(dialogue_result(['safety'=>'none','needs_review'=>true],$s['answers'])['priority']==='emergency','known danger cannot be downgraded by model uncertainty');
$s['answers']=[];$s['turn_count']=10;check(dialogue_progress($s)['reason']==='clarification_limit','clarification is bounded');
check(dialogue_result(['safety'=>'none'],[])['priority']==='clinic','bounded unresolved intake receives human referral');
$old=['symptom'=>'foot','foot_flags'=>'none','foot_side'=>'both','foot_injury'=>'activity'];$a=dialogue_merge_answers($old,[['key'=>'symptom','value'=>'headache','evidence'=>'my head hurts']],'actually my head hurts');
check($a['symptom']==='headache'&&!isset($a['foot_flags'],$a['foot_side']),'symptom correction removes irrelevant branch answers');
$a=dialogue_merge_answers([], [['key'=>'risk','value'=>'no','evidence'=>'invented']],'hello');check(!$a,'unsupported extracted facts do not advance intake');
$s=['answers'=>$complete,'history'=>[['role'=>'user','content'=>'Synthetic symptom details']]];$review=dialogue_prepare_review($s);check(isset($review['review_token'],$s['review']['answers']['severity']),'automatic summary records severity and confirmation token');
echo "Completion tests passed; these are software tests, not clinical validation.\n";
