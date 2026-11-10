<?php
// Completion logic only: care decisions remain in the draft assessment rules.
function dialogue_missing(array $answers) {
    $missing=[];
    foreach(assessment_intake_questions() as $q)if(assessment_question_applies($q,$answers) && !isset($answers[$q['key']]))$missing[]=$q;
    return $missing;
}
function dialogue_progress(array $state) {
    $answers=$state['answers'] ?? [];$missing=dialogue_missing($answers);
    if(($state['safety'] ?? 'none')!=='none')return ['ready'=>true,'reason'=>'warning','next'=>null];
    // A positive rule warning must not wait for all remaining questions.
    foreach(assessment_questions() as $q){$v=$answers[$q['key']] ?? null;if(assessment_question_applies($q,$answers) && is_string($v) && isset($q['stop'][$v]))return ['ready'=>true,'reason'=>'warning','next'=>null];}
    if(!$missing)return ['ready'=>true,'reason'=>'complete','next'=>null];
    // Unsupported symptom pathways need human review once basic severity is established.
    $core=['symptom','warning','duration','severity'];$coreKnown=true;foreach($core as $key)if(!isset($answers[$key]))$coreKnown=false;
    $branchKnown=true;foreach($missing as $q)if(str_ends_with($q['key'],'_flags'))$branchKnown=false;
    if($coreKnown && $branchKnown && !in_array($answers['symptom'],['headache','respiratory','stomach'],true))return ['ready'=>true,'reason'=>'human_review','next'=>null];
    if(($state['turn_count'] ?? 0)>=10)return ['ready'=>true,'reason'=>'clarification_limit','next'=>null];
    // Rotate unanswered details after two attempts rather than repeat one question forever.
    foreach($missing as $q)if(($state['asked'][$q['key']] ?? 0)<2)return ['ready'=>false,'reason'=>'missing','next'=>$q];
    return ['ready'=>true,'reason'=>'clarification_limit','next'=>null];
}
function dialogue_prepare_review(array &$state) {
    $answers=$state['answers'] ?? [];$lines=['What you told me:'];
    foreach($state['history'] as $m)if($m['role']==='user')$lines[]='- '.$m['content'];
    $lines[]="\nDetails to check:";$missing=[];
    $labels=['symptom'=>'Main concern','warning'=>'General warning signs','duration'=>'When it started','severity'=>'Effect on activities','age'=>'Age group','risk'=>'Health risks','trend'=>'Improving or worsening','hydration'=>'Drinking and urination'];
    foreach(assessment_intake_questions() as $q){if(!assessment_question_applies($q,$answers))continue;$label=$labels[$q['key']] ?? ucfirst(str_replace('_',' ',$q['key']));if(isset($answers[$q['key']]))$lines[]=$label.': '.$q['options'][$answers[$q['key']]];else $missing[]=$label;}
    if($missing)$lines[]="\nNot established: ".implode(', ',$missing).'. Missing details are not assumed reassuring; incomplete information leads to clinic review.';
    $token=bin2hex(random_bytes(24));$state['review']=['token'=>$token,'answers'=>$answers];
    return ['summary'=>implode("\n\n",$lines),'review_token'=>$token];
}
function dialogue_merge_answers(array $old,array $facts,$message) {
    // New facts require an exact quote in the CURRENT message, never model questions.
    $updates=dialogue_validate_facts($facts,[['role'=>'user','content'=>$message]],$old);
    $merged=array_merge($old,$updates);
    foreach(assessment_questions() as $q)if(!assessment_question_applies($q,$merged))unset($merged[$q['key']]);
    return $merged;
}
