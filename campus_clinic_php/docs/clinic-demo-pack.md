> Draft-2 snapshot: see [the draft-3 expanded symptom review supplement](expanded-symptom-review.html) for the eight added groups. Clinical approval remains pending.

# Campus Clinic Chatbot: Provisional Demonstration Pack

Prepared: 7 November 2026. Software snapshot: draft-2.

**For a supervised prototype demonstration using fictional cases. Clinical review and approval are pending.**

This pack demonstrates the implemented branches. The recorded outputs are the current SOFTWARE behavior, not a clinician-approved answer key or evidence of medical accuracy. No patient records were created to prepare these cases.

## What to tell the clinic

> We have a working choices-first prototype. Students report symptoms, duration, severity and related details. It gives preliminary guidance on monitoring symptoms, a clinic visit or more urgent help. Sending the result to staff is optional. The current decision rules are provisional, and we are asking you to correct the questions, criteria and student advice before clinical use.

## Suggested walkthrough

1. Introduce the purpose and draft status. Do not claim that the clinic has approved it.
2. Use D01 to show a monitoring/self-care software result, and D02 to show clinic guidance.
3. Use D04 to show that a warning interrupts the questions and does not require submitting the form.
4. Use D06 and D07 to show the current limits: foot pain and unlisted symptoms need human review in this draft.
5. Compare D01 and D08. Ask specifically whether the duration restriction causes unnecessary referrals.
6. Use a clearly named fictional identity, such as DEMO STUDENT / DEMO-CLINIC-01, only if demonstrating optional submission. Do not use real student health information.
7. In Pre-Assessments, show the reference, staff review and identity-verification step. If demonstrating consultation, register a separate fictional student record, explicitly verify that this is a simulated visit, and record only fictional findings. A submission is not an appointment or an emergency alert.
8. Ask the nurse/physician to record expected clinical actions and corrections, then implement and re-test the agreed changes. Keep clinical approval fields blank until the actual review occurs.

The demo materials do not seed or delete database records. If your team manually saves a fictional assessment/consultation during the presentation, track its reference and arrange authorized cleanup or a test-database reset separately. Completed consultation records are intentionally read-only and protected by history-retention rules.

## Files and pages

- Printable walkthrough and cases: `clinic-demo-pack.html` (Print / Save as PDF).
- Exact fictional inputs and generated outputs: `clinic-demo-cases.json`.
- Detailed question-by-question clinical review: `clinic-review-worksheet.html` or `clinic-review-worksheet.md`.
- Student flow: `../pre_assessment.php`.
- Staff inbox: `../assessments.php` (staff login required).

## Provisional behavior to discuss, not claim as medical policy

- Adult-only self-care eligibility.
- The under-24-hour, mild-and-improving self-care restriction.
- Only three self-care patterns; fever, rash, foot pain and unlisted symptoms are referred.
- Broad grouped warning and health-risk questions.
- How to handle uncertain, contradictory or missing answers.
- Approved local clinic hours, referral destinations and escalation contacts.
- Whether optional Llama interpretation is needed; the choices work without the model.

These rules are already implemented as drafts. Preparing this pack does not expand the supported medical scope, change thresholds or establish clinical approval.

## Case sheets

For an independent comparison, ask the reviewer to write the expected clinical action BEFORE revealing the recorded software output. All cases require clinical review; no expected clinical actions have been filled in.

### D01 - Mild familiar headache

Fictional scenario: Fictional adult selects a familiar occasional mild headache, under 24 hours, improving, normal hydration and no listed risks or warning signs.

| Question | Selected answer |
| --- | --- |
| What is bothering you? Choose the concern that best fits how you feel. | Headache |
| Headache check. Emergency signs: sudden extremely painful headache; recent head injury; new weakness, speech or vision loss; seizure; stiff neck or light sensitivity with fever. Other concerning signs: vomiting, new eye/vision problems, or pain brought on by coughing/exercise. | None of the listed signs |
| Before we continue, are you having severe trouble breathing, a tight/heavy chest, sudden weakness or trouble speaking, confusion, collapse, a seizure, sudden severe pain, uncontrolled bleeding, swelling of the lips/tongue/throat, or another emergency? | None of these |
| How long has this been going on? | Less than 24 hours |
| How are the symptoms affecting you? | Mild; I can do usual activities |
| Is this headache like an occasional mild headache you have had before, without any new or unusual features? | Yes, familiar and occasional |
| Compared with earlier, are the symptoms improving? | Yes, improving |
| Can you drink and keep fluids down, and are you urinating about as usual without new dizziness? | Yes, drinking and urinating normally |
| Which age group are you in? The self-care pathway is for adults aged 18 and over only. | 18 or older |
| Are you pregnant/recently gave birth, taking blood thinners, immunocompromised, or managing a significant condition such as asthma, diabetes, or heart/kidney disease? Also choose Yes if a clinician has given you a special care plan. | No |

Clinician-expected action and reason (fill first): ______________________________

Recorded SOFTWARE output: **Self-care and monitoring may be reasonable** (`self_care`).

Follow the monitoring guidance below. You may still contact the clinic if worried, or send this summary for staff review.

Discussion: Ask whether the self-care eligibility and monitoring advice are appropriate.

Reviewer feedback / correction: ________________________________________________

### D02 - Headache affecting activities

Fictional scenario: Same fictional baseline, but symptoms interfere with usual activities.

| Question | Selected answer |
| --- | --- |
| What is bothering you? Choose the concern that best fits how you feel. | Headache |
| Headache check. Emergency signs: sudden extremely painful headache; recent head injury; new weakness, speech or vision loss; seizure; stiff neck or light sensitivity with fever. Other concerning signs: vomiting, new eye/vision problems, or pain brought on by coughing/exercise. | None of the listed signs |
| Before we continue, are you having severe trouble breathing, a tight/heavy chest, sudden weakness or trouble speaking, confusion, collapse, a seizure, sudden severe pain, uncontrolled bleeding, swelling of the lips/tongue/throat, or another emergency? | None of these |
| How long has this been going on? | Less than 24 hours |
| How are the symptoms affecting you? | Interfering with usual activities |
| Is this headache like an occasional mild headache you have had before, without any new or unusual features? | Yes, familiar and occasional |
| Compared with earlier, are the symptoms improving? | Yes, improving |
| Can you drink and keep fluids down, and are you urinating about as usual without new dizziness? | Yes, drinking and urinating normally |
| Which age group are you in? The self-care pathway is for adults aged 18 and over only. | 18 or older |
| Are you pregnant/recently gave birth, taking blood thinners, immunocompromised, or managing a significant condition such as asthma, diabetes, or heart/kidney disease? Also choose Yes if a clinician has given you a special care plan. | No |

Clinician-expected action and reason (fill first): ______________________________

Recorded SOFTWARE output: **Clinic consultation recommended** (`clinic`).

Visit the campus clinic for an assessment. If you send this summary, keep its reference and show it with your student ID when you visit.

Discussion: Ask how the clinic defines functional severity and the visit time frame.

Reviewer feedback / correction: ________________________________________________

### D03 - Headache warning response

Fictional scenario: Fictional student selects the other concerning signs choice in the headache warning question.

| Question | Selected answer |
| --- | --- |
| What is bothering you? Choose the concern that best fits how you feel. | Headache |
| Headache check. Emergency signs: sudden extremely painful headache; recent head injury; new weakness, speech or vision loss; seizure; stiff neck or light sensitivity with fever. Other concerning signs: vomiting, new eye/vision problems, or pain brought on by coughing/exercise. | One or more other concerning signs listed above |
| Before we continue, are you having severe trouble breathing, a tight/heavy chest, sudden weakness or trouble speaking, confusion, collapse, a seizure, sudden severe pain, uncontrolled bleeding, swelling of the lips/tongue/throat, or another emergency? | None of these |
| How long has this been going on? | Less than 24 hours |
| How are the symptoms affecting you? | Mild; I can do usual activities |
| Is this headache like an occasional mild headache you have had before, without any new or unusual features? | Yes, familiar and occasional |
| Compared with earlier, are the symptoms improving? | Yes, improving |
| Can you drink and keep fluids down, and are you urinating about as usual without new dizziness? | Yes, drinking and urinating normally |
| Which age group are you in? The self-care pathway is for adults aged 18 and over only. | 18 or older |
| Are you pregnant/recently gave birth, taking blood thinners, immunocompromised, or managing a significant condition such as asthma, diabetes, or heart/kidney disease? Also choose Yes if a clinician has given you a special care plan. | No |

Clinician-expected action and reason (fill first): ______________________________

Recorded SOFTWARE output: **Prompt medical assessment needed** (`urgent`).

Seek prompt in-person medical attention. Do not wait to submit this form or for staff to read it.

Discussion: Ask whether the grouped warning question should be split and what escalation route to show.

Reviewer feedback / correction: ________________________________________________

### D04 - Immediate warning response

Fictional scenario: Fictional student selects Yes to the general emergency-warning question.

| Question | Selected answer |
| --- | --- |
| What is bothering you? Choose the concern that best fits how you feel. | Headache |
| Headache check. Emergency signs: sudden extremely painful headache; recent head injury; new weakness, speech or vision loss; seizure; stiff neck or light sensitivity with fever. Other concerning signs: vomiting, new eye/vision problems, or pain brought on by coughing/exercise. | None of the listed signs |
| Before we continue, are you having severe trouble breathing, a tight/heavy chest, sudden weakness or trouble speaking, confusion, collapse, a seizure, sudden severe pain, uncontrolled bleeding, swelling of the lips/tongue/throat, or another emergency? | Yes / I may be having an emergency |
| How long has this been going on? | Less than 24 hours |
| How are the symptoms affecting you? | Mild; I can do usual activities |
| Is this headache like an occasional mild headache you have had before, without any new or unusual features? | Yes, familiar and occasional |
| Compared with earlier, are the symptoms improving? | Yes, improving |
| Can you drink and keep fluids down, and are you urinating about as usual without new dizziness? | Yes, drinking and urinating normally |
| Which age group are you in? The self-care pathway is for adults aged 18 and over only. | 18 or older |
| Are you pregnant/recently gave birth, taking blood thinners, immunocompromised, or managing a significant condition such as asthma, diabetes, or heart/kidney disease? Also choose Yes if a clinician has given you a special care plan. | No |

Clinician-expected action and reason (fill first): ______________________________

Recorded SOFTWARE output: **Seek emergency help now** (`emergency`).

Seek emergency medical help now. Completing or sending this form is not required and does not dispatch help.

Discussion: Demonstrate immediate guidance without requiring submission; ask for approved local contacts.

Reviewer feedback / correction: ________________________________________________

### D05 - Mild respiratory symptoms with a health risk

Fictional scenario: Fictional adult selects mild respiratory symptoms and Yes to the health-risk question.

| Question | Selected answer |
| --- | --- |
| What is bothering you? Choose the concern that best fits how you feel. | Cough / sore throat / runny nose |
| Breathing/throat check. Emergency signs: gasping, inability to speak because of breathing difficulty, blue/grey lips, throat swelling, or inability to swallow. Other concerning signs: shortness of breath, chest pain, coughing blood, or trouble swallowing fluids. | None of the listed signs |
| Before we continue, are you having severe trouble breathing, a tight/heavy chest, sudden weakness or trouble speaking, confusion, collapse, a seizure, sudden severe pain, uncontrolled bleeding, swelling of the lips/tongue/throat, or another emergency? | None of these |
| How long has this been going on? | Less than 24 hours |
| How are the symptoms affecting you? | Mild; I can do usual activities |
| Are your symptoms limited to a mild runny/blocked nose, sneezing, mild sore throat or mild cough, without fever, wheezing, or other symptoms? | Yes, only these mild symptoms |
| Compared with earlier, are the symptoms improving? | Yes, improving |
| Can you drink and keep fluids down, and are you urinating about as usual without new dizziness? | Yes, drinking and urinating normally |
| Which age group are you in? The self-care pathway is for adults aged 18 and over only. | 18 or older |
| Are you pregnant/recently gave birth, taking blood thinners, immunocompromised, or managing a significant condition such as asthma, diabetes, or heart/kidney disease? Also choose Yes if a clinician has given you a special care plan. | Yes |

Clinician-expected action and reason (fill first): ______________________________

Recorded SOFTWARE output: **Clinic consultation recommended** (`clinic`).

Visit the campus clinic for an assessment. If you send this summary, keep its reference and show it with your student ID when you visit.

Discussion: Ask whether different risks need separate pathways instead of a single broad risk group.

Reviewer feedback / correction: ________________________________________________

### D06 - Feet hurt after walking

Fictional scenario: Fictional adult reports both feet hurting after walking, one to three days, no selected foot warning signs.

| Question | Selected answer |
| --- | --- |
| What is bothering you? Choose the concern that best fits how you feel. | Foot / feet / ankle pain |
| I am sorry your feet hurt. First, are there any warning signs? Emergency signs: a badly injured foot with exposed bone, uncontrolled bleeding, an odd shape after injury, or new numbness or cold/blue skin after injury. Other concerning signs: you cannot walk, severe pain, or a hot swollen foot or fever. | None of the listed signs |
| Is the pain in one foot or both feet? | Both feet / ankles |
| Did it start after an injury, or after walking/standing for a long time? | After walking or standing |
| Before we continue, are you having severe trouble breathing, a tight/heavy chest, sudden weakness or trouble speaking, confusion, collapse, a seizure, sudden severe pain, uncontrolled bleeding, swelling of the lips/tongue/throat, or another emergency? | None of these |
| How long has this been going on? | 1 to 3 days |
| How are the symptoms affecting you? | Mild; I can do usual activities |
| Compared with earlier, are the symptoms improving? | Yes, improving |
| Can you drink and keep fluids down, and are you urinating about as usual without new dizziness? | Yes, drinking and urinating normally |
| Which age group are you in? The self-care pathway is for adults aged 18 and over only. | 18 or older |
| Are you pregnant/recently gave birth, taking blood thinners, immunocompromised, or managing a significant condition such as asthma, diabetes, or heart/kidney disease? Also choose Yes if a clinician has given you a special care plan. | No |

Clinician-expected action and reason (fill first): ______________________________

Recorded SOFTWARE output: **Clinic consultation recommended** (`clinic`).

Visit the campus clinic for an assessment. If you send this summary, keep its reference and show it with your student ID when you visit.

Discussion: Explain that foot pain is outside the draft self-care paths; ask whether the blanket referral is too restrictive.

Reviewer feedback / correction: ________________________________________________

### D07 - Symptom not in the choices

Fictional scenario: Fictional student chooses Other and types a synthetic ear-discomfort description.

| Question | Selected answer |
| --- | --- |
| What is bothering you? Choose the concern that best fits how you feel. | Other / several symptoms that do not fit |
| Before we continue, are you having severe trouble breathing, a tight/heavy chest, sudden weakness or trouble speaking, confusion, collapse, a seizure, sudden severe pain, uncontrolled bleeding, swelling of the lips/tongue/throat, or another emergency? | None of these |
| How long has this been going on? | Less than 24 hours |
| How are the symptoms affecting you? | Mild; I can do usual activities |
| Compared with earlier, are the symptoms improving? | Yes, improving |
| Can you drink and keep fluids down, and are you urinating about as usual without new dizziness? | Yes, drinking and urinating normally |
| Which age group are you in? The self-care pathway is for adults aged 18 and over only. | 18 or older |
| Are you pregnant/recently gave birth, taking blood thinners, immunocompromised, or managing a significant condition such as asthma, diabetes, or heart/kidney disease? Also choose Yes if a clinician has given you a special care plan. | No |

Typed description: DEMO ONLY: ear discomfort; fictional example, not a real patient.

Clinician-expected action and reason (fill first): ______________________________

Recorded SOFTWARE output: **Clinic consultation recommended** (`clinic`).

Visit the campus clinic for an assessment. If you send this summary, keep its reference and show it with your student ID when you visit.

Discussion: Demonstrate preserved free text and human review without a made-up diagnosis.

Reviewer feedback / correction: ________________________________________________

### D08 - Duration boundary

Fictional scenario: Same reassuring fictional headache answers as D01, except duration is one to three days.

| Question | Selected answer |
| --- | --- |
| What is bothering you? Choose the concern that best fits how you feel. | Headache |
| Headache check. Emergency signs: sudden extremely painful headache; recent head injury; new weakness, speech or vision loss; seizure; stiff neck or light sensitivity with fever. Other concerning signs: vomiting, new eye/vision problems, or pain brought on by coughing/exercise. | None of the listed signs |
| Before we continue, are you having severe trouble breathing, a tight/heavy chest, sudden weakness or trouble speaking, confusion, collapse, a seizure, sudden severe pain, uncontrolled bleeding, swelling of the lips/tongue/throat, or another emergency? | None of these |
| How long has this been going on? | 1 to 3 days |
| How are the symptoms affecting you? | Mild; I can do usual activities |
| Is this headache like an occasional mild headache you have had before, without any new or unusual features? | Yes, familiar and occasional |
| Compared with earlier, are the symptoms improving? | Yes, improving |
| Can you drink and keep fluids down, and are you urinating about as usual without new dizziness? | Yes, drinking and urinating normally |
| Which age group are you in? The self-care pathway is for adults aged 18 and over only. | 18 or older |
| Are you pregnant/recently gave birth, taking blood thinners, immunocompromised, or managing a significant condition such as asthma, diabetes, or heart/kidney disease? Also choose Yes if a clinician has given you a special care plan. | No |

Clinician-expected action and reason (fill first): ______________________________

Recorded SOFTWARE output: **Clinic consultation recommended** (`clinic`).

Visit the campus clinic for an assessment. If you send this summary, keep its reference and show it with your student ID when you visit.

Discussion: Flag the under-24-hour rule as an unapproved prototype restriction; ask the reviewer for an appropriate criterion.

Reviewer feedback / correction: ________________________________________________

### D09 - Fever with uncertain health risk

Fictional scenario: Fictional student selects fever, no selected fever warning signs, and Not sure for health risks.

| Question | Selected answer |
| --- | --- |
| What is bothering you? Choose the concern that best fits how you feel. | Fever / feeling feverish |
| Fever check. Emergency signs: stiff neck, pain from bright light, confusion, or a rash that does not fade under a glass. Other concerning signs: worsening fever despite home care, repeated vomiting, new bleeding, severe weakness, or difficulty drinking. | None of the listed signs |
| Before we continue, are you having severe trouble breathing, a tight/heavy chest, sudden weakness or trouble speaking, confusion, collapse, a seizure, sudden severe pain, uncontrolled bleeding, swelling of the lips/tongue/throat, or another emergency? | None of these |
| How long has this been going on? | Less than 24 hours |
| How are the symptoms affecting you? | Mild; I can do usual activities |
| Compared with earlier, are the symptoms improving? | Yes, improving |
| Can you drink and keep fluids down, and are you urinating about as usual without new dizziness? | Yes, drinking and urinating normally |
| Which age group are you in? The self-care pathway is for adults aged 18 and over only. | 18 or older |
| Are you pregnant/recently gave birth, taking blood thinners, immunocompromised, or managing a significant condition such as asthma, diabetes, or heart/kidney disease? Also choose Yes if a clinician has given you a special care plan. | Not sure / prefer not to say |

Clinician-expected action and reason (fill first): ______________________________

Recorded SOFTWARE output: **Prompt medical assessment needed** (`urgent`).

Seek prompt in-person medical attention. Do not wait to submit this form or for staff to read it.

Discussion: Ask whether this uncertainty response causes excessive escalation and how it should be handled.

Reviewer feedback / correction: ________________________________________________

### D10 - Hydration concern

Fictional scenario: Fictional student selects mild loose stools, but reports difficulty with fluid intake or urination in the hydration question.

| Question | Selected answer |
| --- | --- |
| What is bothering you? Choose the concern that best fits how you feel. | Stomach discomfort / nausea / diarrhea |
| Stomach check. Emergency signs: blood/coffee-ground material or green vomit, sudden severe belly pain, or possible poisoning. Other concerning signs: blood in stool, persistent/localized belly pain, repeated vomiting, or inability to keep fluids down. | None of the listed signs |
| Before we continue, are you having severe trouble breathing, a tight/heavy chest, sudden weakness or trouble speaking, confusion, collapse, a seizure, sudden severe pain, uncontrolled bleeding, swelling of the lips/tongue/throat, or another emergency? | None of these |
| How long has this been going on? | Less than 24 hours |
| How are the symptoms affecting you? | Mild; I can do usual activities |
| Which best describes your stomach symptoms? | Only mild loose stools, without vomiting, fever, or ongoing belly pain |
| Compared with earlier, are the symptoms improving? | Yes, improving |
| Can you drink and keep fluids down, and are you urinating about as usual without new dizziness? | No; unable to keep fluids down, much less urine, or new dizziness |
| Which age group are you in? The self-care pathway is for adults aged 18 and over only. | 18 or older |
| Are you pregnant/recently gave birth, taking blood thinners, immunocompromised, or managing a significant condition such as asthma, diabetes, or heart/kidney disease? Also choose Yes if a clinician has given you a special care plan. | No |

Clinician-expected action and reason (fill first): ______________________________

Recorded SOFTWARE output: **Prompt medical assessment needed** (`urgent`).

Seek prompt in-person medical attention. Do not wait to submit this form or for staff to read it.

Discussion: Ask whether the compound hydration question should be split and how urgency should be determined.

Reviewer feedback / correction: ________________________________________________

### D11 - Student under 18

Fictional scenario: Fictional student selects otherwise mild respiratory answers, but age is under 18.

| Question | Selected answer |
| --- | --- |
| What is bothering you? Choose the concern that best fits how you feel. | Cough / sore throat / runny nose |
| Breathing/throat check. Emergency signs: gasping, inability to speak because of breathing difficulty, blue/grey lips, throat swelling, or inability to swallow. Other concerning signs: shortness of breath, chest pain, coughing blood, or trouble swallowing fluids. | None of the listed signs |
| Before we continue, are you having severe trouble breathing, a tight/heavy chest, sudden weakness or trouble speaking, confusion, collapse, a seizure, sudden severe pain, uncontrolled bleeding, swelling of the lips/tongue/throat, or another emergency? | None of these |
| How long has this been going on? | Less than 24 hours |
| How are the symptoms affecting you? | Mild; I can do usual activities |
| Are your symptoms limited to a mild runny/blocked nose, sneezing, mild sore throat or mild cough, without fever, wheezing, or other symptoms? | Yes, only these mild symptoms |
| Compared with earlier, are the symptoms improving? | Yes, improving |
| Can you drink and keep fluids down, and are you urinating about as usual without new dizziness? | Yes, drinking and urinating normally |
| Which age group are you in? The self-care pathway is for adults aged 18 and over only. | Under 18 |
| Are you pregnant/recently gave birth, taking blood thinners, immunocompromised, or managing a significant condition such as asthma, diabetes, or heart/kidney disease? Also choose Yes if a clinician has given you a special care plan. | No |

Clinician-expected action and reason (fill first): ______________________________

Recorded SOFTWARE output: **Clinic consultation recommended** (`clinic`).

Visit the campus clinic for an assessment. If you send this summary, keep its reference and show it with your student ID when you visit.

Discussion: Explain the adult-only design boundary; ask for an approved route for younger students.

Reviewer feedback / correction: ________________________________________________

### D12 - Not sure about warning signs

Fictional scenario: Fictional student selects I am unsure on the general warning question.

| Question | Selected answer |
| --- | --- |
| What is bothering you? Choose the concern that best fits how you feel. | Headache |
| Headache check. Emergency signs: sudden extremely painful headache; recent head injury; new weakness, speech or vision loss; seizure; stiff neck or light sensitivity with fever. Other concerning signs: vomiting, new eye/vision problems, or pain brought on by coughing/exercise. | None of the listed signs |
| Before we continue, are you having severe trouble breathing, a tight/heavy chest, sudden weakness or trouble speaking, confusion, collapse, a seizure, sudden severe pain, uncontrolled bleeding, swelling of the lips/tongue/throat, or another emergency? | I am unsure |
| How long has this been going on? | Less than 24 hours |
| How are the symptoms affecting you? | Mild; I can do usual activities |
| Is this headache like an occasional mild headache you have had before, without any new or unusual features? | Yes, familiar and occasional |
| Compared with earlier, are the symptoms improving? | Yes, improving |
| Can you drink and keep fluids down, and are you urinating about as usual without new dizziness? | Yes, drinking and urinating normally |
| Which age group are you in? The self-care pathway is for adults aged 18 and over only. | 18 or older |
| Are you pregnant/recently gave birth, taking blood thinners, immunocompromised, or managing a significant condition such as asthma, diabetes, or heart/kidney disease? Also choose Yes if a clinician has given you a special care plan. | No |

Clinician-expected action and reason (fill first): ______________________________

Recorded SOFTWARE output: **Prompt medical assessment needed** (`urgent`).

Seek prompt in-person medical attention. Do not wait to submit this form or for staff to read it.

Discussion: Ask how the clinic wants uncertainty handled; do not treat an unanswered or uncertain item as No.

Reviewer feedback / correction: ________________________________________________

## Feedback and next iteration

Review date: __________________  Reviewing nurse / physician: __________________

Required question / choice changes: ___________________________________________

Required decision-rule changes and approved source: _____________________________

Required student next-action / referral wording: _______________________________

Required staff workflow changes: ______________________________________________

Which fictional cases need a different result, and why? _________________________

Agreed scope for the next supervised prototype test: ___________________________

Assigned developer / target version / retest date: ______________________________

Clinical approval and signature fields remain blank until the real reviewer completes the separate review worksheet. Record actual feedback; do not invent test participants, clinical approval, acceptance ratings or validation results.