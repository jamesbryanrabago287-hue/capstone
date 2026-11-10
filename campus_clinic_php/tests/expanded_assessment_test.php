<?php
require __DIR__.'/../includes/assessment.php';
function expect($ok,$message){if(!$ok){fwrite(STDERR,"FAIL: $message\n");exit(1);}}
$keys=['dizziness','dental','ear','eye','back','period','urinary','muscle'];
foreach($keys as $symptom){
 $a=['symptom'=>$symptom,'warning'=>'no','age'=>'adult','risk'=>'no','duration'=>'today','severity'=>'mild','trend'=>'improving','hydration'=>'normal'];
 foreach(assessment_questions() as $q) if(assessment_question_applies($q,$a) && !isset($a[$q['key']])) $a[$q['key']]=str_ends_with($q['key'],'_flags')?'none':array_key_first($q['options']);
 expect(assessment_evaluate($a)['priority']==='clinic',"$symptom requires human review");
 foreach(['emergency'=>'emergency','urgent'=>'urgent','unsure'=>'urgent'] as $flag=>$priority) expect(assessment_evaluate(['symptom'=>$symptom,$symptom.'_flags'=>$flag])['priority']===$priority,"$symptom early $flag escalation");
 unset($a[$symptom.'_flags']);try{assessment_evaluate($a);expect(false,"$symptom missing safety check accepted");}catch(InvalidArgumentException $e){}
}
$questions=assessment_intake_questions();expect(count($questions)===35,'35 questions in expanded catalog');expect($questions[0]['key']==='symptom','symptom first');
echo "PASS: eight new pathways, early escalation, required safety answers and question catalog. Clinical validity is not established.\n";
