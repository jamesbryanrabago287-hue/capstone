<?php
require __DIR__.'/../includes/assessment.php';
function check($condition,$label) { if (!$condition) { fwrite(STDERR,"FAIL: $label\n"); exit(1); } echo "PASS: $label\n"; }
function fixture($symptom='headache') {
    $a=['warning'=>'no','age'=>'adult','risk'=>'no','symptom'=>$symptom,'duration'=>'today','severity'=>'mild','trend'=>'improving','hydration'=>'normal'];
    foreach (['headache','respiratory','stomach','fever','skin','foot'] as $s) if ($symptom===$s) $a[$s.'_flags']='none';
    $patterns=['headache'=>'familiar','respiratory'=>'simple','stomach'=>'loose'];
    if (isset($patterns[$symptom])) $a[$symptom.'_pattern']=$patterns[$symptom];
    if ($symptom==='foot') { $a['foot_side']='both'; $a['foot_injury']='activity'; }
    return $a;
}
function priority(array $answers) { return assessment_evaluate($answers)['priority']; }
function invalid(array $answers) { try { assessment_evaluate($answers); return false; } catch(InvalidArgumentException $e) { return true; } }
check(priority(['warning'=>'yes'])==='emergency','global emergency interrupts assessment');
check(priority(['warning'=>'unsure'])==='urgent','uncertain warning signs escalate');
foreach (['headache','respiratory','stomach'] as $s) {
    check(priority(fixture($s))==='self_care',"limited low-risk $s self-care path");
    $r=assessment_evaluate(fixture($s));
    check(strpos($r['advice'],'not medical clearance')!==false && strpos($r['advice'],'Recheck')!==false,"$s has limits and monitoring instructions");
}
foreach (['fever','skin','foot','other'] as $s) check(priority(fixture($s))==='clinic',"$s requires clinic clarification");
foreach (['headache','respiratory','stomach','fever','skin','foot'] as $s) foreach (['emergency'=>'emergency','urgent'=>'urgent','unsure'=>'urgent'] as $value=>$expected) {
    $a=fixture($s);$a[$s.'_flags']=$value;check(priority($a)===$expected,"$s warning $value escalates");
}
$base=fixture();
foreach (['age'=>['minor','unknown'],'risk'=>['yes','unknown'],'duration'=>['days','longer','unknown'],'severity'=>['moderate','unknown'],'trend'=>['same','unknown'],'hydration'=>['unknown']] as $field=>$values) foreach ($values as $value) {
    check(priority(array_merge($base,[$field=>$value]))==='clinic',"$field=$value cannot receive self-care");
}
foreach (['severity'=>'severe','trend'=>'worse','hydration'=>'problem'] as $field=>$value) check(priority(array_merge($base,[$field=>$value]))==='urgent',"$field=$value escalates");
check(priority(array_merge(fixture('fever'),['risk'=>'yes']))==='urgent','fever with risk escalates');
check(priority(array_merge($base,['headache_pattern'=>'recurrent']))==='clinic','recurrent headaches need clinic review');
check(priority(array_merge($base,['headache_pattern'=>'new']))==='clinic','new headaches need clinic review');
check(priority(array_merge(fixture('stomach'),['stomach_pattern'=>'pain']))==='clinic','belly pain excluded from self-care');
check(priority(array_merge(fixture('respiratory'),['respiratory_pattern'=>'other']))==='clinic','additional respiratory symptoms excluded from self-care');
$notes=array_merge($base,['details'=>'New symptom mentioned in notes']);
check(priority($notes)==='clinic','uninterpreted notes cannot receive automated reassurance');
check(strpos(assessment_evaluate($notes)['summary'],'not automatically interpreted')!==false,'notes identified for staff');
check(invalid([]) && invalid(['warning'=>[]]),'invalid initial answers rejected');
$missing=$base;unset($missing['headache_flags']);check(invalid($missing),'missing branch warning answer rejected');
check(invalid(array_merge($base,['hydration'=>[]])),'array input rejected');
check(invalid(array_merge($base,['severity'=>'invalid'])),'unknown option rejected');
check(invalid(array_merge($base,['respiratory_flags'=>'emergency'])),'contradictory branch answers rejected');
check(invalid(array_merge($base,['details'=>str_repeat('x',501)])),'oversized notes rejected');
$paths=0;$selfCare=0;
foreach (['headache','respiratory','stomach','fever','skin','foot','other'] as $s)
foreach (['adult','minor','unknown'] as $age)
foreach (['no','yes','unknown'] as $risk)
foreach (['today','days','longer','unknown'] as $duration)
foreach (['mild','moderate','severe','unknown'] as $severity)
foreach (['improving','same','worse','unknown'] as $trend)
foreach (['normal','problem','unknown'] as $hydration) {
    $a=array_merge(fixture($s),compact('age','risk','duration','severity','trend','hydration'));
    $p=priority($a);$eligible=in_array($s,['headache','respiratory','stomach'],true) && $age==='adult' && $risk==='no' && $duration==='today' && $severity==='mild' && $trend==='improving' && $hydration==='normal';
    if (($p==='self_care')!==$eligible) check(false,'self-care guard invariant');
    if ($p==='self_care') $selfCare++;$paths++;
}
check($paths===12096 && $selfCare===3,'12,096 combinations preserve narrow self-care eligibility');
check(assessment_intake_questions()[0]['key']==='symptom','conversation starts with main concern');
check(count(assessment_intake_questions())===count(assessment_questions()),'presentation preserves every required question');
check(priority(['symptom'=>'headache','headache_flags'=>'emergency'])==='emergency','early branch emergency needs no remaining form fields');
check(priority(['symptom'=>'stomach','stomach_flags'=>'unsure'])==='urgent','early uncertainty interrupts the questionnaire');
check(priority(['warning'=>'unsure','symptom'=>'headache','headache_flags'=>'emergency'])==='emergency','emergency outranks other uncertain warning');
$incomplete=fixture();unset($incomplete['warning']);check(invalid($incomplete),'missing safety answer never becomes implicit reassurance');
echo "All draft-rule tests passed. These tests do not establish clinical validity.\n";
