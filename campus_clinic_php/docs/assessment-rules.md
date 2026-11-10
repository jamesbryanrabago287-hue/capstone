# Student symptom assessment: draft rules v3

Status: implementation prototype, awaiting clinic validation. Prepared 2026-11-06.
No clinic nurse has approved these rules. Passing software tests is not evidence of clinical safety or diagnostic accuracy.

## Intended behavior

The chatbot collects structured symptoms and suggests a level of care, without diagnosing a disease. The button questionnaire uses no external API. Optional free-text chat uses a local Llama model through Ollama to interpret a proposed answer, which the student confirms; the care rules remain local. Its questions, branch conditions, and immediate escalation signals come from `assessment_questions()`; both PHP validation and the browser use that schema. The result includes its draft rule version and the actual questions/answers for staff review. Existing saved assessments remain unchanged.

| Outcome | Trigger in this prototype | Student action |
| --- | --- | --- |
| Emergency | Reported general or symptom-specific emergency sign | Seek emergency help immediately; do not wait for saving or staff review |
| Prompt assessment | Other concerning branch signs, uncertainty about warning signs, severe/worsening symptoms, hydration problems, or fever with risk/uncertain risk | Contact a professional now; use an urgent service if the clinic is unavailable |
| Clinic consultation | Under 18/age unknown; health risks; persistent, new/recurrent, unclear or unsupported symptoms; moderate impact; no clear improvement; free-text notes | Arrange consultation and escalate if symptoms change |
| Self-care and monitoring | ALL: adult, no declared risk, under 24 hours, mild, improving, normal fluids/urination, no flags, no additional notes, and one supported mild pattern below | Non-drug care, monitoring, and explicit instructions to seek care if symptoms persist/worsen or concern the student |

The only self-care patterns are familiar occasional mild headache, isolated mild nose/throat/cough symptoms without fever/wheeze, and isolated mild loose stools without vomiting, fever or ongoing belly pain. Fever, rash and other/complex symptoms always get a human referral. New/recurrent headaches, nausea/vomiting and abdominal pain are outside self-care.

The under-24-hour/improving-only restriction is an intentionally narrow prototype choice, NOT a diagnostic threshold from NHS guidance. A duration of one day does not itself imply danger; it means this limited prototype will not automatically recommend self-care. The adult-only restriction is a design boundary, not a statement that all children require emergency care. Risk grouping is deliberately broad. A negative warning answer cannot rule out illness.

Free-text notes are never interpreted as safe, benign or diagnostic. Any notes disqualify self-care and are explicitly marked for human review. Additional notes are shared only with consent. The student can always seek care regardless of the result.

## Evidence used to draft wording

These public sources informed warning signs and non-drug supportive care; they do not validate this algorithm or replace local Philippine clinical guidance. UK emergency telephone numbers are not copied into the product.

- Headache warning signs, hydration, regular meals and screen breaks: https://www.nhs.uk/symptoms/headaches/
- Respiratory symptom care and reasons to consult: https://www.nhs.uk/conditions/common-cold/
- Swallowing/breathing danger signs: https://www.nhs.uk/symptoms/sore-throat/
- Fluids, bowel-symptom escalation, and 48-hour exclusion after diarrhea/vomiting: https://www.nhs.uk/symptoms/diarrhoea-and-vomiting/
- Fever and when professional review is needed: https://www.nhs.uk/symptoms/fever-in-adults/
- Non-fading rash and neurological warning signs: https://www.nhs.uk/conditions/meningitis/
- Severe skin/mucosal reactions: https://www.nhs.uk/conditions/stevens-johnson-syndrome/
- Severe allergy warning signs: https://www.nhs.uk/conditions/anaphylaxis/

## Clinic review checklist (pending)

- Review each question and escalation threshold with the campus nurse/physician, including local fever/dengue guidance and referral capacity.
- Confirm the adult-only limits, handling of chronic conditions/pregnancy, and routes for students under 18.
- Approve or replace every self-care pathway, monitoring instruction and infection-control message; validate Filipino/local-language wording if added.
- Supply actual local emergency/clinic contacts and clarify when the clinic is staffed.
- Test realistic cases, atypical presentations, missed warning signs, uncertain answers, multiple symptoms and symptom deterioration with clinical reviewers.
- Approve consent, retention, access control and the fact that inbox submissions are not emergency alerts.
- Document reviewer, date, protocol version and required changes before clinical use.

Reviewer: pending. Approval date: pending. Local protocol reference: pending.

## Software checks

`C:\xampp\php\php.exe tests/assessment_test.php` covers early warning overrides, branch-specific escalation, unknown/missing/contradictory answers, notes, and 10,368 combinations of generic risk/severity fields. Only three default low-risk combinations reach self-care. These tests exercise implementation consistency, not medical correctness.

## AI intake extension (pending validation)
AI output is constrained to answer choices, quoted evidence, an escalation-only safety signal, and an unmapped-concern flag. Model output is untrusted and checked by PHP. Uncertainty does not fill reassuring answers by default. Additional concerns exclude self-care. AI safety signals cannot downgrade a declared emergency. Transcripts may contain unconfirmed suggestions; staff must verify them. Consent, model interpretation accuracy, false-negative safety signals, prompt injection, local languages, cross-tab behavior and clinical usability require evaluation. This extension changes the paper's predefined-response-only limitation.

## Symptom-first presentation (2026-11-07)
The intended users feel unwell and are unsure whether to visit the clinic. Both modes start with their main concern, then ask relevant symptom-specific questions. A short emergency notice remains visible. Positive or uncertain danger answers interrupt the flow, even before demographic and other fields are complete; no negative answers are inferred. Emergency signs outrank urgent signs if both are supplied. Finishing, sharing or saving is never a prerequisite to seeking help. The self-care eligibility checks still require every reassuring answer.

## Unified guided conversation (2026-11-07)
A single Start symptom check button opens symptom choices. The bot presents relevant follow-up choices after each answer. Students may choose Type my answer instead, consent to local AI processing, and type a response for AI interpretation and confirmation. API sessions start lazily on the first typed submission. Selecting choices requires no API key, AI processing, or technical mode selection. The same draft rules control all care outcomes.

## Local model integration (2026-11-07)
The active provider is local Ollama, model llama3.2:1b. Calls use a fixed loopback endpoint with no cloud fallback or API key. The consent copy identifies local processing. Synthetic examples support schema interpretation; evidence validation, student confirmation, conservative rules and escalation remain mandatory. Small models can fail on simple or ambiguous replies. Evaluate false negatives, negation, uncommon/multiple symptoms, local languages and workload on the actual hardware before clinical use.

## Foot-pain intake (2026-11-07)
Foot/ankle pain now has a named symptom group. Follow-ups record warning signs, one/both feet and injury/activity context. Foot pain does not enter the automated self-care pathway; it routes to clinic review unless warning signs require prompt or emergency help. Draft wording references: https://www.nhs.uk/symptoms/foot-pain/pain-in-the-top-of-the-foot/ and https://www.nhs.uk/conditions/sprains-and-strains/ . Clinic validation is pending. Typed-reply preference persists across follow-ups; answer choices remain available.

## Conversation-based intake (2026-11-07, current flow)
The default interaction is a free-text local conversation with recent-message history, rather than converting and confirming one questionnaire answer each turn. Students request a review, inspect extracted facts and missing information, correct the conversation, then confirm a single summary. Rule evaluation remains separate. Missing or conflicting facts do not become negative findings; incomplete intake routes to clinic review. Generated interview language is constrained by prompts and a basic phrase filter, neither of which guarantees safety. Clinician-led evaluation of generation, memory, negation, corrections, extraction and warning detection is required. The guided question flow remains available.

Live conversation testing found echoed replies and unsupported danger tags from the 1B model. Echoes/repeated replies now use a contextual intake follow-up. Unsupported danger tags mark the conversation for clinic review rather than fabricating an urgent clinical finding. Explicit inability to breathe/walk still escalates. These checks are non-exhaustive and do not rule out emergencies. Final summaries include the student's actual words and a visible missing-information list; neither generated questions nor missing answers are patient facts.

## Goal-directed completion (2026-11-07)
The server now accumulates validated current-message facts, asks about missing information, and automatically prepares a review when the required information is available. Symptom group, warning signs, duration and functional severity are central to intake. Unsupported self-care groups go to clinic review once these basics are known; this does not imply the condition is serious. Self-care still requires all existing eligibility checks. Positive warnings stop the process. Ten successful turns or exhausted clarification attempts produce an incomplete-information clinic referral rather than endless chat or an assumed mild result. This is interaction/completion logic, not a new clinical score. Clinical approval remains pending.

## Choices-first student flow (2026-11-07, current default)
Students choose symptom and follow-up answers. Typing is offered only when their symptom/answer is absent from the choices. An unlisted symptom is recorded as Other plus the student's description and needs human review. Unknown information is not inferred negative. The assessment appears automatically after the guided questions, without requiring a final free-text note. Optional typed interpretation returns to choices after confirmation; it does not start an endless conversation.

## Staff handoff and consultation (2026-11-07)
Students receive explicit next actions and may submit their summary to clinic staff with consent. The receipt explains the reference, visit instructions and absence of booking/dispatch guarantees. Staff can review the submission, verify identity in person, begin a linked consultation, record their own findings and next steps, and complete a basic visit record. New, Reviewed, In progress and Completed describe workflow, not medical urgency. Chatbot guidance is never copied into staff findings automatically. Consultation history is visible only to authorized staff; clinical rules are still pending validation.

## Clinical review worksheet
Use [clinic-review-worksheet.md](clinic-review-worksheet.md) for written review and [clinic-review-worksheet.html](clinic-review-worksheet.html) for a printable form. Prepared 7 November 2026 from the current student-question schema. Reviewer decisions must be implemented and re-tested; the worksheet does not approve or change the running rules.

## Provisional clinic demonstration
Use [clinic-demo-pack.html](clinic-demo-pack.html) or [clinic-demo-pack.md](clinic-demo-pack.md) for the supervised walkthrough. [clinic-demo-cases.json](clinic-demo-cases.json) records 12 fictional input sets and outputs evaluated from draft-2 on 7 November 2026. These are software examples, not clinically approved expected results. No patient data, approvals or review outcomes were invented or saved to the application.

## Expanded symptom catalog (draft-3, 7 November 2026)

Eight new choices: dizziness, dental pain, ear problems, eye problems, back pain, menstrual/pelvic pain, urinary discomfort, and muscle/joint pain. Sore throat remains under cough/sore throat/runny nose. Other symptoms remain available as free text for human review. This is not an exhaustive symptom checker.

Each new group has a conditional safety question and a descriptive follow-up. New groups default to clinic review, or prompt/emergency help for selected concerning answers. No new self-care clearance or disease diagnosis is introduced. Dental concerns explicitly advise dental assessment. Clinical review is pending; grouped warning lists and conservative referral choices need local review.

The existing demo pack and worksheet appendices are historical draft-2 snapshots. Use [the expanded question review](expanded-symptom-review.html) to review the current additions. Do not treat the old snapshots as the complete current catalog.

Sources checked for wording (not validation of this algorithm):
- https://www.nhs.uk/symptoms/dizziness/
- https://www.nhs.uk/symptoms/toothache/
- https://www.nhs.uk/symptoms/earache/
- https://www.nhs.uk/symptoms/eye-pain/
- https://www.nhs.uk/conditions/back-pain/
- https://www.nhs.uk/symptoms/pelvic-pain/
- https://www.nhs.uk/conditions/urinary-tract-infections-utis/
- https://www.nhs.uk/conditions/sprains-and-strains/

## Plain-language wording review ? 7 November 2026

Every symptom warning list is retained, but emergency and other concerning signs are now separated visually. General warning and health-risk questions use readable lists. Age, onset, activity impact, trend and hydration wording are shorter. Answer values, branch conditions, urgency mappings and self-care criteria are unchanged. The global warning wording uses plain equivalents (for example, bleeding that will not stop); clinician and student comprehension review is pending.

Use [the current complete wording review sheet](chatbot-question-review.html) for all 35 questions, relevant conditions, answer labels and immediate software actions. Record actual clinical feedback before changing the medical criteria.

## Stop-care flow correction ? 8 November 2026
Selecting an explicit emergency or prompt-assessment stop answer ends the guided check immediately in the browser. No identity or sharing form is shown and no assessment request is required before displaying help. AI safety stops also end intake. Server-generated urgent/emergency results suppress sharing. Other results offer Finish first, with an optional Save summary button; identity fields appear only after choosing to save. This changes presentation, not clinical criteria.

## Requested triage-assistant purpose ? 10 November 2026
The conversation policy is now maintained in includes/triage_prompt.php. It follows the requested symptom consultation ? appropriate care location ? finish workflow, with no diagnosis, identity form or registration. Internal intake still needs evidence-supported facts and an incomplete-information phase. Once ready, the endpoint returns triage.primary_symptoms, triage.triage_category and triage.conversational_response. Urgent internally maps to CLINIC_VISIT while keeping prompt-care instructions; it must not imply waiting for opening hours. Existing conservative care rules are retained pending clinical review. A temperature-only emergency threshold and universal emergency classification of coughing blood were not introduced. NHS reference checks: https://www.nhs.uk/symptoms/fever-in-adults/ and https://www.nhs.uk/symptoms/coughing-up-blood/. The latter distinguishes small streaks needing urgent review from larger amounts or associated breathing/chest symptoms needing emergency help. Neither source validates this prototype.
