<?php
// Draft triage pathways, version 3. Not a validated diagnostic instrument.
// Sources, restrictions and review checklist: docs/assessment-rules.md.
function assessment_questions() {
    $flags = ['emergency'=>'One or more emergency signs listed above', 'urgent'=>'One or more other concerning signs listed above', 'none'=>'None of the listed signs', 'unsure'=>'Not sure'];
    return [
        ['key'=>'warning','text'=>'Are you having any of these emergency signs?

- Severe trouble breathing
- A tight or heavy chest
- Sudden weakness or trouble speaking
- Confusion, collapse or a seizure
- Sudden severe pain
- Bleeding that will not stop
- Swollen lips, tongue or throat
- Another emergency','options'=>['yes'=>'Yes / I may be having an emergency','unsure'=>'I am unsure','no'=>'None of these'],'stop'=>['yes'=>'emergency','unsure'=>'urgent']],
        ['key'=>'age','text'=>'How old are you? Self-care guidance here is only for people aged 18 or older.','options'=>['adult'=>'18 or older','minor'=>'Under 18','unknown'=>'Prefer not to say / not sure']],
        ['key'=>'risk','text'=>'Do any of these apply to you?

- Pregnant or recently gave birth
- Taking blood-thinning medicine
- A weakened immune system
- A significant health condition, such as asthma, diabetes, or heart or kidney disease
- A special care plan from a clinician','options'=>['yes'=>'Yes','no'=>'No','unknown'=>'Not sure / prefer not to say']],
        ['key'=>'symptom','text'=>'What is bothering you? Choose the closest match.','options'=>['fever'=>'Fever / feeling feverish','respiratory'=>'Cough / sore throat / runny nose','headache'=>'Headache','stomach'=>'Stomach discomfort / nausea / diarrhea','skin'=>'Itching / rash','foot'=>'Foot / feet / ankle pain','dizziness'=>'Dizziness / feeling faint','dental'=>'Tooth / gum pain','ear'=>'Ear pain / hearing concern','eye'=>'Eye pain / redness / vision concern','back'=>'Back pain','period'=>'Menstrual cramps / pelvic pain','urinary'=>'Pain / burning / changes when urinating','muscle'=>'Muscle / joint pain or injury','other'=>'Other / several symptoms that do not fit']],
        ['key'=>'headache_flags','when'=>['symptom'=>'headache'],'text'=>'Do any of these signs apply?

Emergency signs:
sudden extremely painful headache; recent head injury; new weakness, speech or vision loss; seizure; stiff neck or light sensitivity with fever.

Other concerning signs:
vomiting, new eye/vision problems, or pain brought on by coughing/exercise.','options'=>$flags,'stop'=>['emergency'=>'emergency','urgent'=>'urgent','unsure'=>'urgent']],
        ['key'=>'headache_pattern','when'=>['symptom'=>'headache'],'text'=>'Is this a familiar, occasional mild headache, with nothing new or unusual?','options'=>['familiar'=>'Yes, familiar and occasional','new'=>'New or different','recurrent'=>'Happening repeatedly','unknown'=>'Not sure']],
        ['key'=>'respiratory_flags','when'=>['symptom'=>'respiratory'],'text'=>'Do any of these signs apply?

Emergency signs:
gasping, inability to speak because of breathing difficulty, blue/grey lips, throat swelling, or inability to swallow.

Other concerning signs:
shortness of breath, chest pain, coughing blood, or trouble swallowing fluids.','options'=>$flags,'stop'=>['emergency'=>'emergency','urgent'=>'urgent','unsure'=>'urgent']],
        ['key'=>'respiratory_pattern','when'=>['symptom'=>'respiratory'],'text'=>'Do you only have a mild runny or blocked nose, sneezing, sore throat or cough? No fever, wheezing or other symptoms.','options'=>['simple'=>'Yes, only these mild symptoms','other'=>'No, I have other symptoms too','unknown'=>'Not sure']],
        ['key'=>'stomach_flags','when'=>['symptom'=>'stomach'],'text'=>'Do any of these signs apply?

Emergency signs:
blood/coffee-ground material or green vomit, sudden severe belly pain, or possible poisoning.

Other concerning signs:
blood in stool, persistent/localized belly pain, repeated vomiting, or inability to keep fluids down.','options'=>$flags,'stop'=>['emergency'=>'emergency','urgent'=>'urgent','unsure'=>'urgent']],
        ['key'=>'stomach_pattern','when'=>['symptom'=>'stomach'],'text'=>'Which best describes your stomach symptoms?','options'=>['loose'=>'Only mild loose stools, without vomiting, fever, or ongoing belly pain','vomiting'=>'Nausea or vomiting','pain'=>'Belly pain or other symptoms','unknown'=>'Not sure']],
        ['key'=>'fever_flags','when'=>['symptom'=>'fever'],'text'=>'Do any of these signs apply?

Emergency signs:
stiff neck, pain from bright light, confusion, or a rash that does not fade under a glass.

Other concerning signs:
worsening fever despite home care, repeated vomiting, new bleeding, severe weakness, or difficulty drinking.','options'=>$flags,'stop'=>['emergency'=>'emergency','urgent'=>'urgent','unsure'=>'urgent']],
        ['key'=>'skin_flags','when'=>['symptom'=>'skin'],'text'=>'Do any of these signs apply?

Emergency signs:
swelling of lips/tongue/throat or difficulty breathing, a rash that does not fade under a glass, or painful blistering/peeling skin with mouth/eye involvement.

Other concerning signs:
fever, rapid spreading, or painful skin.','options'=>$flags,'stop'=>['emergency'=>'emergency','urgent'=>'urgent','unsure'=>'urgent']],
        ['key'=>'foot_flags','when'=>['symptom'=>'foot'],'text'=>'Do any of these signs apply?

Emergency signs:
a badly injured foot with exposed bone, uncontrolled bleeding, an odd shape after injury, or new numbness or cold/blue skin after injury.

Other concerning signs:
you cannot walk, severe pain, or a hot swollen foot or fever.','options'=>$flags,'stop'=>['emergency'=>'emergency','urgent'=>'urgent','unsure'=>'urgent']],
        ['key'=>'foot_side','when'=>['symptom'=>'foot'],'text'=>'Is the pain in one foot or both feet?','options'=>['one'=>'One foot / ankle','both'=>'Both feet / ankles','unknown'=>'Not sure']],
        ['key'=>'foot_injury','when'=>['symptom'=>'foot'],'text'=>'Did this start after an injury or a long time walking or standing?','options'=>['injury'=>'After an injury / twist / fall','activity'=>'After walking or standing','neither'=>'Neither / no clear trigger','unknown'=>'Not sure']],
        ['key'=>'dizziness_flags','when'=>['symptom'=>'dizziness'],'text'=>'Do any of these signs apply?

Emergency signs:
sudden weakness, trouble speaking, collapse, chest pain or severe breathing difficulty.

Other concerning signs:
new vision changes, repeated near-fainting, or dizziness with vomiting.','options'=>$flags,'stop'=>['emergency'=>'emergency','urgent'=>'urgent','unsure'=>'urgent']],
        ['key'=>'dizziness_pattern','when'=>['symptom'=>'dizziness'],'text'=>'Which best describes the dizziness?','options'=>['spinning'=>'Spinning / off-balance','faint'=>'Lightheaded / feeling faint','other'=>'Another feeling / not sure']],
        ['key'=>'dental_flags','when'=>['symptom'=>'dental'],'text'=>'Do any of these signs apply?

Emergency signs:
swelling around the eye or neck, or mouth/neck swelling affecting breathing, swallowing or speaking.

Other concerning signs:
cheek/jaw swelling, fever or difficulty opening your mouth.','options'=>$flags,'stop'=>['emergency'=>'emergency','urgent'=>'urgent','unsure'=>'urgent']],
        ['key'=>'dental_pattern','when'=>['symptom'=>'dental'],'text'=>'Where is the discomfort?','options'=>['tooth'=>'Tooth','gum'=>'Gum / mouth','other'=>'Several areas / not sure']],
        ['key'=>'ear_flags','when'=>['symptom'=>'ear'],'text'=>'Do any of these signs apply?

Emergency signs:
collapse, confusion or new weakness/trouble speaking.

Other concerning signs:
hearing loss/change, fluid from the ear, swelling around the ear, fever/shivering, something stuck in the ear, or pain lasting more than 2 to 3 days.','options'=>$flags,'stop'=>['emergency'=>'emergency','urgent'=>'urgent','unsure'=>'urgent']],
        ['key'=>'ear_pattern','when'=>['symptom'=>'ear'],'text'=>'Which ear is affected?','options'=>['one'=>'One ear','both'=>'Both ears','unknown'=>'Not sure']],
        ['key'=>'eye_flags','when'=>['symptom'=>'eye'],'text'=>'Do any of these signs apply?

Emergency signs:
sudden eye symptoms, inability to see, an injured/pierced eye or something stuck in it, eye pain keeping you awake, red eye with severe headache/nausea, or severe pain/vision loss after recent eye treatment.

Other concerning signs:
severe pain, light sensitivity, very red eye or other vision changes.','options'=>$flags,'stop'=>['emergency'=>'emergency','urgent'=>'urgent','unsure'=>'urgent']],
        ['key'=>'eye_pattern','when'=>['symptom'=>'eye'],'text'=>'Which best describes the eye concern?','options'=>['pain'=>'Pain / soreness','red'=>'Redness / watering / discharge','vision'=>'Vision concern','other'=>'Other / not sure']],
        ['key'=>'back_flags','when'=>['symptom'=>'back'],'text'=>'Do any of these signs apply?

Emergency signs:
symptoms in both legs such as weakness/numbness, loss of feeling around genitals/anus, new bladder/bowel or sexual-function changes, chest pain, or pain after a serious accident.

Other concerning signs:
sudden severe or quickly worsening pain, fever/shivering or feeling generally unwell.','options'=>$flags,'stop'=>['emergency'=>'emergency','urgent'=>'urgent','unsure'=>'urgent']],
        ['key'=>'back_pattern','when'=>['symptom'=>'back'],'text'=>'Where is the back discomfort?','options'=>['lower'=>'Lower back','upper'=>'Upper back','other'=>'Several areas / not sure']],
        ['key'=>'period_flags','when'=>['symptom'=>'period'],'text'=>'Do any of these signs apply?

Emergency signs:
severe or worsening pelvic pain, fainting, shoulder-tip pain, heavy vaginal bleeding or breathing difficulty.

Other concerning signs:
possible pregnancy, fever/shivering, unusual bleeding/discharge, vomiting, or difficulty/pain when urinating or passing stool.','options'=>$flags,'stop'=>['emergency'=>'emergency','urgent'=>'urgent','unsure'=>'urgent']],
        ['key'=>'period_pattern','when'=>['symptom'=>'period'],'text'=>'Which best describes the discomfort?','options'=>['usual'=>'Similar to usual menstrual cramps','different'=>'New / different cramps','pelvic'=>'Pelvic / lower abdominal pain not clearly related to a period','unknown'=>'Not sure / prefer not to say']],
        ['key'=>'urinary_flags','when'=>['symptom'=>'urinary'],'text'=>'Do any of these signs apply?

Emergency signs:
confusion, marked drowsiness or difficulty speaking.

Other concerning signs:
fever/shivering, blood in urine, lower tummy or back/side pain, pregnancy/possible pregnancy, inability to urinate, diabetes or weakened immunity.','options'=>$flags,'stop'=>['emergency'=>'emergency','urgent'=>'urgent','unsure'=>'urgent']],
        ['key'=>'urinary_pattern','when'=>['symptom'=>'urinary'],'text'=>'Which change have you noticed?','options'=>['burning'=>'Pain / burning','frequency'=>'Going more often / sudden urge','other'=>'Another change / not sure']],
        ['key'=>'muscle_flags','when'=>['symptom'=>'muscle'],'text'=>'Do any of these signs apply?

Emergency signs:
a limb at an odd angle after injury, new numbness/tingling, cold or blue skin after injury, exposed bone or uncontrolled bleeding.

Other concerning signs:
inability to use or bear weight, substantial swelling, severe/worsening pain, fever/shivering or a hot swollen joint.','options'=>$flags,'stop'=>['emergency'=>'emergency','urgent'=>'urgent','unsure'=>'urgent']],
        ['key'=>'muscle_pattern','when'=>['symptom'=>'muscle'],'text'=>'How did it start?','options'=>['injury'=>'Injury / twist / fall','activity'=>'After activity / exercise','neither'=>'No clear trigger','unknown'=>'Not sure']],
        ['key'=>'duration','text'=>'When did this start?','options'=>['today'=>'Less than 24 hours','days'=>'1 to 3 days','longer'=>'More than 3 days','unknown'=>'Not sure']],
        ['key'=>'severity','text'=>'How much is this affecting your usual activities?','options'=>['mild'=>'Mild; I can do usual activities','moderate'=>'Interfering with usual activities','severe'=>'Severe / rapidly worsening','unknown'=>'Not sure'],'stop'=>['severe'=>'urgent']],
        ['key'=>'trend','text'=>'Are you feeling better or worse?','options'=>['improving'=>'Yes, improving','same'=>'About the same','worse'=>'Getting worse / returned after improving','unknown'=>'Not sure']],
        ['key'=>'hydration','text'=>'Can you keep drinks down and pee as usual, without new dizziness?','options'=>['normal'=>'Yes, drinking and urinating normally','problem'=>'No; unable to keep fluids down, much less urine, or new dizziness','unknown'=>'Not sure'],'stop'=>['problem'=>'urgent']],
    ];
}
// Present the concern first, then relevant safety checks; never infer negative answers.
function assessment_intake_questions() {
    $questions=assessment_questions();
    $order=['symptom','headache_flags','respiratory_flags','stomach_flags','fever_flags','skin_flags','dizziness_flags','dental_flags','ear_flags','eye_flags','back_flags','period_flags','urinary_flags','muscle_flags','foot_flags','foot_side','foot_injury','warning','duration','severity','headache_pattern','respiratory_pattern','stomach_pattern','dizziness_pattern','dental_pattern','ear_pattern','eye_pattern','back_pattern','period_pattern','urinary_pattern','muscle_pattern','trend','hydration','age','risk'];
    $rank=array_flip($order);
    usort($questions,function($a,$b) use($rank){return $rank[$a['key']] <=> $rank[$b['key']];});
    foreach($questions as &$q) {
        if($q['key']==='symptom') $q['text']='What is bothering you? Choose the closest match.';
        if($q['key']==='warning') $q['text']=str_replace('First, are you','Before we continue, are you',$q['text']);
    }
    unset($q);
    return $questions;
}
function assessment_question_applies(array $question, array $answers) {
    foreach ($question['when'] ?? [] as $key=>$value) if (($answers[$key] ?? null)!==$value) return false;
    return true;
}
function assessment_next_action($priority) {
    $actions=['self_care'=>'Follow the monitoring guidance below. You may still contact the clinic if worried.','clinic'=>'Visit the campus clinic for an assessment. Tell clinic staff your symptoms when you arrive.','urgent'=>'Seek prompt in-person medical attention. Do not wait to submit this form or for staff to read it.','emergency'=>'Seek emergency medical help now. Completing or sending this form is not required and does not dispatch help.'];
    return $actions[$priority] ?? 'Discuss this preliminary result with clinic staff.';
}
function assessment_result($priority,$reason,array $summary,$pattern='') {
    $titles=['emergency'=>'Seek emergency help now','urgent'=>'Prompt medical assessment needed','clinic'=>'Clinic consultation recommended','self_care'=>'Self-care and monitoring may be reasonable'];
    $guidance=[
        'emergency'=>'Seek emergency medical help now. Ask someone nearby to help contact local emergency services or the nearest emergency department. Do not wait for this chat or a clinic reply. This system does not dispatch help.',
        'urgent'=>'Contact clinic staff or a healthcare professional now for prompt assessment. If they are unavailable, use an urgent medical service. If an emergency sign is present or develops, seek emergency help immediately; do not wait for a saved submission.',
        'clinic'=>'Arrange a clinic consultation to clarify your symptoms. Contact staff sooner if you feel worse or are concerned. If symptoms are severe or rapidly worsening, seek prompt medical assessment; use emergency services for emergency warning signs.',
        'self_care'=>'Based on the limited answers provided, home care and monitoring may be reasonable for now. This is not medical clearance, a diagnosis, or proof that a clinic visit is unnecessary. You can still contact the clinic at any time.',
    ];
    $advice=$reason . "\n\n" . $guidance[$priority];
    if ($pattern!=='') $advice .= "\n\nReported symptom pattern: " . $pattern . '. This does not identify the cause.';
    if ($priority==='self_care') {
        $advice .= "\n\n" . [
            'Familiar mild headache'=>'Drink water, eat regular meals, relax, and take breaks from screens.',
            'Mild nose/throat/cough symptoms'=>'Rest, drink fluids, and use tissues and good hand hygiene to reduce spread.',
            'Mild loose stools'=>'Rest, take frequent small drinks of water, and eat when able. Wash hands with soap and water. Stay away from classes until at least 48 hours after the last episode of diarrhea.',
        ][$pattern];
        $advice .= "\n\nRecheck how you feel later today and tomorrow. Contact the clinic if symptoms are not improving, persist, return, or worry you. Seek prompt medical help for worsening symptoms, fever, trouble drinking, less urine, or new dizziness. Seek emergency help for breathing difficulty, collapse, confusion, sudden severe pain, or other emergency signs.";
    }
    $advice .= "\n\nPrototype guidance; clinic review is still required. Free-text notes are not assessed by these rules.";
    return ['priority'=>$priority,'assessment'=>$titles[$priority],'advice'=>$advice,'summary'=>implode("\n",$summary),'rule_version'=>'draft-3','next_action'=>assessment_next_action($priority)];
}
function assessment_evaluate(array $answers) {
    $summary=['Rule version: draft-3'];
    // A symptom-first conversation can reveal a danger sign before other fields exist.
    // Escalate partial positive/uncertain answers without fabricating reassuring answers.
    $urgent=null;
    foreach(assessment_questions() as $question) {
        if(!assessment_question_applies($question,$answers)) continue;
        $value=$answers[$question['key']] ?? null;
        if(!is_string($value) || !isset($question['options'][$value],$question['stop'][$value])) continue;
        $priority=$question['stop'][$value];
        $partial=['Rule version: draft-3','Conversation interrupted before completing all questions.',$question['text']."\nAnswer: ".$question['options'][$value]];
        if(isset($answers['symptom']) && is_string($answers['symptom'])) {
            foreach(assessment_questions() as $item) if($item['key']==='symptom' && isset($item['options'][$answers['symptom']])) $partial[]='Main concern: '.$item['options'][$answers['symptom']];
        }
        if($priority==='emergency') return assessment_result('emergency','A reported warning sign needs immediate help. Do not complete or submit the questionnaire before seeking care.',$partial);
        $urgent=$partial;
    }
    if($urgent!==null) return assessment_result('urgent','A concerning or uncertain answer needs prompt human assessment; you do not need to finish this chat first.',$urgent);
    $details=$answers['details'] ?? '';
    if (!is_string($details) || mb_strlen($details,'UTF-8')>500) throw new InvalidArgumentException('Additional details must be at most 500 characters.');
    foreach (assessment_questions() as $q) {
        if (!assessment_question_applies($q,$answers)) {
            // Reject contradictory branch answers rather than silently downgrading them.
            if (array_key_exists($q['key'],$answers)) throw new InvalidArgumentException('The symptom choices changed. Please start a new assessment.');
            continue;
        }
        $value=$answers[$q['key']] ?? null;
        if (!is_string($value) || !isset($q['options'][$value])) throw new InvalidArgumentException('Please complete the question: '.$q['text']);
        $summary[]=$q['text']."\nAnswer: ".$q['options'][$value];
        if (isset($q['stop'][$value])) {
            if (trim($details)!=='') $summary[]='Student notes (not automatically interpreted): '.trim($details);
            return assessment_result($q['stop'][$value],'Your answer indicates a warning sign, uncertainty about warning signs, or symptoms needing prompt human assessment.',$summary);
        }
    }
    if (trim($details)!=='') $summary[]='Student notes (not automatically interpreted): '.trim($details);
    $patterns=['headache'=>'Familiar mild headache','respiratory'=>'Mild nose/throat/cough symptoms','stomach'=>'Mild loose stools'];
    $pattern=$patterns[$answers['symptom']] ?? '';
    $simple=($answers['symptom']==='headache' && $answers['headache_pattern']==='familiar')
        || ($answers['symptom']==='respiratory' && $answers['respiratory_pattern']==='simple')
        || ($answers['symptom']==='stomach' && $answers['stomach_pattern']==='loose');
    $eligible=$answers['age']==='adult' && $answers['risk']==='no' && $answers['duration']==='today'
        && $answers['severity']==='mild' && $answers['trend']==='improving' && $answers['hydration']==='normal' && $simple && trim($details)==='';
    // Explanatory patterns must not mislabel a different symptom presentation.
    if (!$simple) $pattern='';
    if ($answers['trend']==='worse') return assessment_result('urgent','Symptoms are getting worse or have returned after improving.',$summary,$pattern);
    if ($answers['risk']!=='no' && $answers['symptom']==='fever') return assessment_result('urgent','Fever together with a health risk or uncertain risk needs prompt professional assessment.',$summary);
    if ($eligible) return assessment_result('self_care','You reported a short-lived, mild, improving symptom pattern, no listed warning signs or health risks, and normal fluid intake.',$summary,$pattern);
    $reasons=[];
    if ($answers['symptom']==='dental') $reasons[]='Tooth or gum concerns need dental assessment; clinic staff can help you find appropriate dental care.';
    if ($answers['age']!=='adult') $reasons[]='The self-care pathway is limited to adults; students under 18 should involve a parent/guardian or clinic professional.';
    if ($answers['risk']!=='no') $reasons[]='A relevant health risk was reported or could not be ruled out.';
    if ($answers['duration']!=='today') $reasons[]='Symptoms have lasted at least a day, or their duration is uncertain.';
    if ($answers['severity']!=='mild') $reasons[]='Symptoms interfere with activities or their severity is uncertain.';
    if ($answers['trend']!=='improving') $reasons[]='Clear improvement was not reported.';
    if ($answers['hydration']!=='normal') $reasons[]='Hydration needs clarification.';
    if (!$simple) $reasons[]='This symptom pattern is outside the limited self-care pathways.';
    if (trim($details)!=='') $reasons[]='Additional notes need human review because the rules do not interpret free text.';
    return assessment_result('clinic',implode(' ',$reasons),$summary,$pattern);
}
function ensure_assessment_tables(PDO $pdo) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS chatbot_sessions (id INT AUTO_INCREMENT PRIMARY KEY, student_no VARCHAR(50), student_name VARCHAR(120), symptoms TEXT NOT NULL, assessment TEXT NOT NULL, advice TEXT NOT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE IF NOT EXISTS chatbot_reviews (session_id INT PRIMARY KEY, student_id INT NULL, reviewed_by INT NULL, reviewed_at DATETIME NULL, KEY idx_chat_student (student_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE IF NOT EXISTS clinic_consultations (
 id INT AUTO_INCREMENT PRIMARY KEY, assessment_id INT NULL UNIQUE, student_id INT NOT NULL,
 student_no_snapshot VARCHAR(50) NOT NULL, student_name_snapshot VARCHAR(120) NOT NULL,
 recorded_by INT NULL, recorded_by_name VARCHAR(120) NOT NULL,
 status ENUM('In progress','Completed') NOT NULL DEFAULT 'In progress', findings TEXT NULL, next_steps TEXT NULL,
 version INT NOT NULL DEFAULT 1, outcome VARCHAR(40) NOT NULL DEFAULT '', started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 completed_at DATETIME NULL, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 KEY idx_consultation_student (student_id), KEY idx_consultation_status (status),
 CONSTRAINT fk_clinic_consult_assessment FOREIGN KEY (assessment_id) REFERENCES chatbot_sessions(id) ON DELETE RESTRICT,
 CONSTRAINT fk_clinic_consult_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE RESTRICT,
 CONSTRAINT fk_clinic_consult_recorder FOREIGN KEY (recorded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $assessmentColumn=$pdo->query("SHOW COLUMNS FROM clinic_consultations LIKE 'assessment_id'")->fetch();
    if($assessmentColumn['Null']==='NO')$pdo->exec('ALTER TABLE clinic_consultations MODIFY assessment_id INT NULL');
    if(!$pdo->query("SHOW COLUMNS FROM clinic_consultations LIKE 'start_token'")->fetch())$pdo->exec('ALTER TABLE clinic_consultations ADD COLUMN start_token VARCHAR(64) NULL UNIQUE');
    if(!$pdo->query("SHOW COLUMNS FROM clinic_consultations LIKE 'version'")->fetch()) $pdo->exec('ALTER TABLE clinic_consultations ADD COLUMN version INT NOT NULL DEFAULT 1');
}
