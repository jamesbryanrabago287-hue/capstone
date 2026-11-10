> Draft-2 snapshot: see [the draft-3 expanded symptom review supplement](expanded-symptom-review.html) for the eight added groups. Clinical approval remains pending.

# Campus Clinic Chatbot: Nurse / Physician Review Worksheet

**Status: draft for clinical review — not an approved clinical protocol.**

Prepared: 7 November 2026. Software rule version: draft-2.
Source snapshot: `includes/assessment.php` and `assessment_intake_questions()`.
This worksheet records proposed decisions; completing it does not automatically change or approve the running system.

## 1. Reviewer and intended use

| Item | Complete during review |
| --- | --- |
| Reviewing nurse / physician and professional role | ______________________________ |
| Campus clinic / department | ______________________________ |
| Review date and protocol / guideline version | ______________________________ |
| Student population and supported ages | ______________________________ |
| Clinic hours and actual contact details | ______________________________ |
| Local urgent-care / emergency referral route | ______________________________ |
| Review scope: prototype testing or clinical deployment | ______________________________ |

**Proposed purpose:** Help a student who feels unwell decide whether to monitor symptoms, visit the campus clinic, or seek more urgent help. It provides preliminary guidance, not a disease diagnosis. Submission of the result to staff is optional and is not an appointment or emergency alert.

Reviewer decision on purpose and scope: [ ] Accept  [ ] Revise  [ ] Reject

Approved purpose / exclusions:

____________________________________________________________________________

____________________________________________________________________________

## 2. Agree on what each result means

Fill in the clinical criteria, time frames and destination. The developers must not invent these from the outcome names.

| Student-facing result | Clinician-approved criteria | Approved action and time frame | Route if clinic is closed |
| --- | --- | --- | --- |
| Self-care and monitoring may be reasonable | __________________ | __________________ | __________________ |
| Clinic consultation recommended | __________________ | __________________ | __________________ |
| Prompt medical assessment needed | __________________ | __________________ | __________________ |
| Seek emergency help now | __________________ | __________________ | __________________ |

Should prompt assessment and emergency remain separate outcomes? __________________

Approved explanation of uncertainty / when to seek further help:

____________________________________________________________________________

## 3. Current prototype rules that need an explicit decision

These summarize the SOFTWARE'S PRESENT BEHAVIOR, not medically approved thresholds.

| ID | Current implementation | Reviewer: accept / revise / remove | Approved replacement and source |
| --- | --- | --- | --- |
| R1 | Self-care is restricted to adults aged 18+; age unknown and minors receive clinic guidance | __________ | __________ |
| R2 | Self-care requires symptoms under 24 hours, mild impact, clear improvement, normal drinking/urination and no listed warning signs | __________ | __________ |
| R3 | Self-care also requires no declared pregnancy/recent birth, blood thinners, immunocompromise, significant chronic condition or special care plan | __________ | __________ |
| R4 | Only familiar occasional mild headache, isolated mild nose/throat/cough symptoms, and mild loose stools can enter self-care | __________ | __________ |
| R5 | Fever, rash, foot/ankle pain and unlisted symptoms do not enter automated self-care | __________ | __________ |
| R6 | Severe/rapidly worsening symptoms and reported hydration problems trigger prompt assessment | __________ | __________ |
| R7 | Getting worse or returning after improvement triggers prompt assessment | __________ | __________ |
| R8 | Fever plus a reported or uncertain health risk triggers prompt assessment | __________ | __________ |
| R9 | A positive emergency warning overrides the remaining questions; uncertainty about listed warning signs triggers prompt assessment | __________ | __________ |
| R10 | Unlisted free text / extra notes require human review and exclude automatic self-care; omitted answers are not assumed negative | __________ | __________ |
| R11 | Students can receive guidance without submitting it; submission only adds optional staff review and a reference | __________ | __________ |

**Important review issue:** the under-24-hour and improving-only conditions are deliberately restrictive prototype choices. They are not established clinical cutoffs. Decide whether they would cause unnecessary clinic visits, and supply approved alternatives if needed.

**Separate pathways:** the public default is choices first. Legacy local-AI conversation routes have additional completion limits, including an incomplete-information referral after ten turns. Do not assume changes to one route are automatically applied to all routes.

## 4. Define each supported symptom pathway

| Symptom group | Required follow-up questions | Signs requiring urgent / emergency help | Conditions permitting self-care | Clinic-referral criteria and reassessment timing | Approved source |
| --- | --- | --- | --- | --- | --- |
| Headache | __________ | __________ | __________ | __________ | __________ |
| Cough / sore throat / runny nose | __________ | __________ | __________ | __________ | __________ |
| Fever / feeling feverish | __________ | __________ | __________ | __________ | __________ |
| Stomach discomfort / nausea / diarrhea | __________ | __________ | __________ | __________ | __________ |
| Itching / rash | __________ | __________ | __________ | __________ | __________ |
| Foot / feet / ankle pain | __________ | __________ | __________ | __________ | __________ |
| Other / multiple / unclear symptoms | __________ | __________ | __________ | __________ | __________ |

Additional groups the clinic actually needs: ___________________________________

Local conditions or protocols that must be incorporated, including fever-related guidance:

____________________________________________________________________________

## 5. Validate the actual questions and choices

The appendix below is generated from the current UI schema. For each question, check whether a student can understand it, whether several questions should be split, whether the choices are complete, and whether “not sure” is handled appropriately. Removing or rewriting a question requires the developers to update and re-test its decision rules.

## 6. Independent case review

Use fictional cases only. The clinical reviewer should fill in the expected action BEFORE looking at the software's output. Write enough details for each case to be clinically interpretable; symptom labels alone are not complete test cases.

| Case | Scenario to flesh out | Clinician expected action and reason (fill first) | Software output | Match? / required change |
| --- | --- | --- | --- | --- |
| T1 | Adult with familiar mild headache, short duration, improvement and no reported warning signs; complete risk/history details | __________ | __________ | __________ |
| T2 | Similar headache that is new, recurrent or different; specify associated symptoms | __________ | __________ | __________ |
| T3 | Mild respiratory symptoms; specify duration, fever status, breathing/swallowing and risk conditions | __________ | __________ | __________ |
| T4 | Fever; specify duration, progression, hydration, risk conditions and relevant local warning signs | __________ | __________ | __________ |
| T5 | Loose stools; specify duration, blood/vomiting, abdominal pain, hydration and risk factors | __________ | __________ | __________ |
| T6 | Foot pain after walking; specify injury, ability to walk, skin changes, duration and relevant health risks | __________ | __________ | __________ |
| T7 | Explicit warning sign identified in the approved clinic protocol | __________ | __________ | __________ |
| T8 | Student under 18 / age not provided | __________ | __________ | __________ |
| T9 | Pregnancy, chronic condition, immunocompromise or other risk requiring a special pathway | __________ | __________ | __________ |
| T10 | Unlisted symptom, several symptoms, contradictory answers or “not sure” | __________ | __________ | __________ |
| T11 | Symptoms worsen after an earlier self-care result | __________ | __________ | __________ |
| T12 | Local model unavailable or typed reply misunderstood; student uses choices | __________ | __________ | __________ |

Also test missing danger signs, atypical presentations, negation (“I do not have ...”), time-frame boundaries, disability/accessibility needs and language the students actually use.

Cases needing revision before release: _________________________________________

## 7. Student result and clinic handoff

[ ] Each result gives a clear next action and the reason based on reported answers.
[ ] Self-care wording does not guarantee that a clinic visit is unnecessary.
[ ] Approved monitoring / escalation advice is understandable.
[ ] Students are not asked to finish or submit before seeking immediate help.
[ ] Actual clinic hours, contacts and closed-clinic routes are correct.
[ ] Optional submission is clearly distinguished from booking and staff acknowledgement.
[ ] Students receive a reference and know to show it with their student ID.
[ ] Staff verify identity; chatbot guidance remains separate from staff findings.
[ ] Consultation completion and verified student history are practical for clinic staff.

Required changes: ____________________________________________________________

## 8. Optional AI, privacy and testing

[ ] Decide whether typed interpretation is needed at all for the prototype.
[ ] Review model misunderstandings, unsupported symptoms, negation and corrections.
[ ] Confirm that AI does not independently diagnose or override care rules.
[ ] Explain local processing, temporary drafts, optional saved transcripts and consent.
[ ] Define access permissions, retention/deletion rules and who reviews submissions.
[ ] Agree on clinical test cases and acceptable error handling, including missed warning signs.
[ ] Separate clinical validation from software tests and ISO/IEC 25010 usability/quality surveys.

Responsible staff member and review schedule: _________________________________

## 9. Review outcome and implementation tracking

[ ] Changes required before another review
[ ] Approved for a limited supervised prototype evaluation, under the conditions below
[ ] Approved for the expressly defined use below, subject to institutional requirements
[ ] Not approved

Conditions / unresolved issues: ______________________________________________

____________________________________________________________________________

Reviewer name / signature / date: _____________________________________________

Developer: __________________  Implementation version / date: __________________

Clinical re-check of implemented changes: _____________________________________

Do not label the current application clinically approved merely because this worksheet has been created. Record the exact reviewed protocol version, implement the changes, and re-check the resulting behavior.

## Appendix: current student questions and answer choices

### symptom

Applies when: All symptom groups

What is bothering you? Choose the concern that best fits how you feel.

- Fever / feeling feverish (`fever`)
- Cough / sore throat / runny nose (`respiratory`)
- Headache (`headache`)
- Stomach discomfort / nausea / diarrhea (`stomach`)
- Itching / rash (`skin`)
- Foot / feet / ankle pain (`foot`)
- Other / several symptoms that do not fit (`other`)

Reviewer: [ ] Accept  [ ] Revise  [ ] Remove

Replacement wording / options / rule / clinical source:

____________________________________________________________________________

### headache_flags

Applies when: symptom = headache

Headache check. Emergency signs: sudden extremely painful headache; recent head injury; new weakness, speech or vision loss; seizure; stiff neck or light sensitivity with fever. Other concerning signs: vomiting, new eye/vision problems, or pain brought on by coughing/exercise.

- One or more emergency signs listed above (`emergency`)
- One or more other concerning signs listed above (`urgent`)
- None of the listed signs (`none`)
- Not sure (`unsure`)

Current early-stop mapping: emergency -> emergency; urgent -> urgent; unsure -> urgent.

Reviewer: [ ] Accept  [ ] Revise  [ ] Remove

Replacement wording / options / rule / clinical source:

____________________________________________________________________________

### respiratory_flags

Applies when: symptom = respiratory

Breathing/throat check. Emergency signs: gasping, inability to speak because of breathing difficulty, blue/grey lips, throat swelling, or inability to swallow. Other concerning signs: shortness of breath, chest pain, coughing blood, or trouble swallowing fluids.

- One or more emergency signs listed above (`emergency`)
- One or more other concerning signs listed above (`urgent`)
- None of the listed signs (`none`)
- Not sure (`unsure`)

Current early-stop mapping: emergency -> emergency; urgent -> urgent; unsure -> urgent.

Reviewer: [ ] Accept  [ ] Revise  [ ] Remove

Replacement wording / options / rule / clinical source:

____________________________________________________________________________

### stomach_flags

Applies when: symptom = stomach

Stomach check. Emergency signs: blood/coffee-ground material or green vomit, sudden severe belly pain, or possible poisoning. Other concerning signs: blood in stool, persistent/localized belly pain, repeated vomiting, or inability to keep fluids down.

- One or more emergency signs listed above (`emergency`)
- One or more other concerning signs listed above (`urgent`)
- None of the listed signs (`none`)
- Not sure (`unsure`)

Current early-stop mapping: emergency -> emergency; urgent -> urgent; unsure -> urgent.

Reviewer: [ ] Accept  [ ] Revise  [ ] Remove

Replacement wording / options / rule / clinical source:

____________________________________________________________________________

### fever_flags

Applies when: symptom = fever

Fever check. Emergency signs: stiff neck, pain from bright light, confusion, or a rash that does not fade under a glass. Other concerning signs: worsening fever despite home care, repeated vomiting, new bleeding, severe weakness, or difficulty drinking.

- One or more emergency signs listed above (`emergency`)
- One or more other concerning signs listed above (`urgent`)
- None of the listed signs (`none`)
- Not sure (`unsure`)

Current early-stop mapping: emergency -> emergency; urgent -> urgent; unsure -> urgent.

Reviewer: [ ] Accept  [ ] Revise  [ ] Remove

Replacement wording / options / rule / clinical source:

____________________________________________________________________________

### skin_flags

Applies when: symptom = skin

Rash check. Emergency signs: swelling of lips/tongue/throat or difficulty breathing, a rash that does not fade under a glass, or painful blistering/peeling skin with mouth/eye involvement. Other concerning signs: fever, rapid spreading, or painful skin.

- One or more emergency signs listed above (`emergency`)
- One or more other concerning signs listed above (`urgent`)
- None of the listed signs (`none`)
- Not sure (`unsure`)

Current early-stop mapping: emergency -> emergency; urgent -> urgent; unsure -> urgent.

Reviewer: [ ] Accept  [ ] Revise  [ ] Remove

Replacement wording / options / rule / clinical source:

____________________________________________________________________________

### foot_flags

Applies when: symptom = foot

I am sorry your feet hurt. First, are there any warning signs? Emergency signs: a badly injured foot with exposed bone, uncontrolled bleeding, an odd shape after injury, or new numbness or cold/blue skin after injury. Other concerning signs: you cannot walk, severe pain, or a hot swollen foot or fever.

- One or more emergency signs listed above (`emergency`)
- One or more other concerning signs listed above (`urgent`)
- None of the listed signs (`none`)
- Not sure (`unsure`)

Current early-stop mapping: emergency -> emergency; urgent -> urgent; unsure -> urgent.

Reviewer: [ ] Accept  [ ] Revise  [ ] Remove

Replacement wording / options / rule / clinical source:

____________________________________________________________________________

### foot_side

Applies when: symptom = foot

Is the pain in one foot or both feet?

- One foot / ankle (`one`)
- Both feet / ankles (`both`)
- Not sure (`unknown`)

Reviewer: [ ] Accept  [ ] Revise  [ ] Remove

Replacement wording / options / rule / clinical source:

____________________________________________________________________________

### foot_injury

Applies when: symptom = foot

Did it start after an injury, or after walking/standing for a long time?

- After an injury / twist / fall (`injury`)
- After walking or standing (`activity`)
- Neither / no clear trigger (`neither`)
- Not sure (`unknown`)

Reviewer: [ ] Accept  [ ] Revise  [ ] Remove

Replacement wording / options / rule / clinical source:

____________________________________________________________________________

### warning

Applies when: All symptom groups

Before we continue, are you having severe trouble breathing, a tight/heavy chest, sudden weakness or trouble speaking, confusion, collapse, a seizure, sudden severe pain, uncontrolled bleeding, swelling of the lips/tongue/throat, or another emergency?

- Yes / I may be having an emergency (`yes`)
- I am unsure (`unsure`)
- None of these (`no`)

Current early-stop mapping: yes -> emergency; unsure -> urgent.

Reviewer: [ ] Accept  [ ] Revise  [ ] Remove

Replacement wording / options / rule / clinical source:

____________________________________________________________________________

### duration

Applies when: All symptom groups

How long has this been going on?

- Less than 24 hours (`today`)
- 1 to 3 days (`days`)
- More than 3 days (`longer`)
- Not sure (`unknown`)

Reviewer: [ ] Accept  [ ] Revise  [ ] Remove

Replacement wording / options / rule / clinical source:

____________________________________________________________________________

### severity

Applies when: All symptom groups

How are the symptoms affecting you?

- Mild; I can do usual activities (`mild`)
- Interfering with usual activities (`moderate`)
- Severe / rapidly worsening (`severe`)
- Not sure (`unknown`)

Current early-stop mapping: severe -> urgent.

Reviewer: [ ] Accept  [ ] Revise  [ ] Remove

Replacement wording / options / rule / clinical source:

____________________________________________________________________________

### headache_pattern

Applies when: symptom = headache

Is this headache like an occasional mild headache you have had before, without any new or unusual features?

- Yes, familiar and occasional (`familiar`)
- New or different (`new`)
- Happening repeatedly (`recurrent`)
- Not sure (`unknown`)

Reviewer: [ ] Accept  [ ] Revise  [ ] Remove

Replacement wording / options / rule / clinical source:

____________________________________________________________________________

### respiratory_pattern

Applies when: symptom = respiratory

Are your symptoms limited to a mild runny/blocked nose, sneezing, mild sore throat or mild cough, without fever, wheezing, or other symptoms?

- Yes, only these mild symptoms (`simple`)
- No, I have other symptoms too (`other`)
- Not sure (`unknown`)

Reviewer: [ ] Accept  [ ] Revise  [ ] Remove

Replacement wording / options / rule / clinical source:

____________________________________________________________________________

### stomach_pattern

Applies when: symptom = stomach

Which best describes your stomach symptoms?

- Only mild loose stools, without vomiting, fever, or ongoing belly pain (`loose`)
- Nausea or vomiting (`vomiting`)
- Belly pain or other symptoms (`pain`)
- Not sure (`unknown`)

Reviewer: [ ] Accept  [ ] Revise  [ ] Remove

Replacement wording / options / rule / clinical source:

____________________________________________________________________________

### trend

Applies when: All symptom groups

Compared with earlier, are the symptoms improving?

- Yes, improving (`improving`)
- About the same (`same`)
- Getting worse / returned after improving (`worse`)
- Not sure (`unknown`)

Reviewer: [ ] Accept  [ ] Revise  [ ] Remove

Replacement wording / options / rule / clinical source:

____________________________________________________________________________

### hydration

Applies when: All symptom groups

Can you drink and keep fluids down, and are you urinating about as usual without new dizziness?

- Yes, drinking and urinating normally (`normal`)
- No; unable to keep fluids down, much less urine, or new dizziness (`problem`)
- Not sure (`unknown`)

Current early-stop mapping: problem -> urgent.

Reviewer: [ ] Accept  [ ] Revise  [ ] Remove

Replacement wording / options / rule / clinical source:

____________________________________________________________________________

### age

Applies when: All symptom groups

Which age group are you in? The self-care pathway is for adults aged 18 and over only.

- 18 or older (`adult`)
- Under 18 (`minor`)
- Prefer not to say / not sure (`unknown`)

Reviewer: [ ] Accept  [ ] Revise  [ ] Remove

Replacement wording / options / rule / clinical source:

____________________________________________________________________________

### risk

Applies when: All symptom groups

Are you pregnant/recently gave birth, taking blood thinners, immunocompromised, or managing a significant condition such as asthma, diabetes, or heart/kidney disease? Also choose Yes if a clinician has given you a special care plan.

- Yes (`yes`)
- No (`no`)
- Not sure / prefer not to say (`unknown`)

Reviewer: [ ] Accept  [ ] Revise  [ ] Remove

Replacement wording / options / rule / clinical source:

____________________________________________________________________________
