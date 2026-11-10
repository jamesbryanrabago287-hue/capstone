# Campus Clinic Medicine Inventory with Decision Support System — PHP Starter

This starter system is based on the uploaded capstone paper. It implements the major functions described in the paper:

- Clinic Administrator and Clinic Staff login
- Role-based access
- Medicine records
- Stock-in and stock-out transactions
- Real-time stock display from MySQL
- Low-stock and expiration alerts
- Student records
- Dashboard
- Decision support using reorder-point rules and usage data
- Purchase Request (PR), APP, and PPMP report views
- Printable procurement reports

## Technology
- PHP 8+
- MySQL / MariaDB
- HTML/CSS
- PDO prepared statements
- XAMPP recommended for beginners

## Installation in XAMPP

1. Install XAMPP.
2. Start **Apache** and **MySQL**.
3. Copy the `campus_clinic` folder into:
   `C:\xampp\htdocs\`
4. Open phpMyAdmin:
   `http://localhost/phpmyadmin`
5. Create/import the database:
   - Click **Import**
   - Select `database.sql`
   - Run the import.
6. Check `config.php`.
   Default XAMPP MySQL settings are:
   - host: localhost
   - database: campus_clinic
   - user: root
   - password: blank
7. Open:
   `http://localhost/campus_clinic/`

## Demo login

- Administrator: `admin` / `admin123`
- Clinic Staff: `staff` / `staff123`

## Main pages

- `dashboard.php` — inventory dashboard
- `medicines.php` — medicine records
- `transactions.php` — stock in/out
- `alerts.php` — low stock and expiration alerts
- `students.php` — basic student records
- `analytics.php` — decision support
- `reports.php` — PR / APP / PPMP
- `users.php` — administrator account management



## Next recommended development

For a full capstone-ready version, add:
1. Edit/update medicine records.
2. Soft-delete/deactivate records instead of hard delete.
3. Detailed audit/history logs.
4. Better procurement document templates matching the university format.
5. More robust forecasting using historical monthly data.
6. CSRF protection and stronger session/security controls.
7. Password reset.
8. Pagination/search/filtering.
9. Backup and restore.
10. Formal testing and ISO/IEC 25010 evaluation.


## Student pre-diagnostic chatbot

Open `pre_assessment.php`, or follow **Start a symptom check** from the staff login page. Students do not need a staff account. The floating widget is also available on staff pages. Choose **Start symptom check**. The default flow is a local conversation with recent-message memory, followed by one summary review. Guided answer choices remain available as a fallback. **Type my answer instead** offers optional consent-based AI interpretation. Typed replies are interpreted by local Llama through Ollama on the same server as PHP. No OpenAI key or cloud AI call is used. Selecting answer choices does not need Ollama.

The conversation starts with the student main concern, then asks symptom-specific follow-ups and the safety, duration, severity, improvement, hydration, age and risk questions needed for an outcome. A short emergency notice stays visible. Concerning answers interrupt the conversation without requiring completion or sharing. It can suggest emergency help, prompt assessment, clinic consultation, or limited adult self-care and monitoring. Emergency guidance appears immediately in the browser before a network request. Results are conservative preliminary referral guidance, never a diagnosis, medicine recommendation, or assurance that serious illness is excluded. Free-text notes are for staff review and are not interpreted by the rules.

Students can finish without sharing, or explicitly consent to store their name, student number, answers, and generated guidance. A draft is temporarily held in the PHP session and expires for sharing after 30 minutes; saved records go to `chatbot_sessions`. Results are generated on the server; clients cannot submit a forged result to save. Assessment tokens prevent cross-tab result mixups. Retries of a save return the existing reference. The service does not book visits or dispatch help; do not wait for inbox review in an emergency.

Authorized staff open **Pre-Assessments** to review submissions, mark them reviewed, and link them to a student record after verifying identity. Publicly submitted names and student numbers are unverified and never automatically attach a result to an official history. Students shows internal record IDs and links to verified assessment history. Staff actions are CSRF-protected and logged by record ID, without symptom text in the activity log. The inbox is paginated and authenticated.

For existing installations, the module creates missing `chatbot_sessions` and `chatbot_reviews` tables without changing existing records. New installations use `database.sql`. Apache and MySQL must be running. Do not re-import the database or reset data for this feature.

### Clinical scope

These are prototype rules for symptom intake and preliminary referral. Clinic personnel must review and validate the questions, emergency routes, rules, privacy/retention policy, and results before clinical use. No validated diagnostic scoring system, condition prediction, or clinical accuracy is claimed. Draft v2 adds narrow self-care paths for short-lived, mild, improving adult symptoms with reassuring follow-up answers. Fever, rash, complex symptoms, risk factors and uncertainty remain human-referral paths. See [the rule specification and pending clinic review checklist](docs/assessment-rules.md). Notes and uncommon symptoms require human assessment.

Emergency warning wording was informed by NHS guidance, reviewed 2026-11-06:
- https://www.nhs.uk/symptoms/shortness-of-breath/
- https://www.nhs.uk/conditions/anaphylaxis/

These references do not validate the complete prototype. No UK emergency number is embedded. The clinic should supply its local escalation contacts before clinical use. Use HTTPS and an appropriate health-data access/retention policy when hosting. Submission limits apply per session; public internet deployment also needs infrastructure-level abuse protection.

Run `C:\xampp\php\php.exe tests/assessment_test.php` to check the decision rules.
Local configuration: includes/auth.php loads includes/config.php when present and otherwise uses includes/config.example.php with the existing XAMPP database settings. Configure your own database credentials before deployment.

## Local Llama / Ollama

The chatbot now uses `llama3.2:1b` through Ollama at the fixed loopback URL `http://127.0.0.1:11434/api/chat`. No API key, paid cloud account, or Apache environment change is needed. Existing OPENAI_* settings are ignored. There is no cloud fallback.

1. Install Ollama for Windows and open the app.
2. Download the model with `ollama run llama3.2:1b`. Use `/bye` to exit the terminal conversation; leave the Ollama app running in the background.
3. Start Apache and MySQL in XAMPP.
4. Open `pre_assessment.php`, refresh, and choose **Start symptom check**.
5. Agree to the local conversation and send a reply such as "my feet hurt". Continue naturally. The assistant automatically presents a summary when enough details are known. Correct it or confirm it once to receive guidance. **Get guidance with current information** is an optional early exit; incomplete details lead to clinic review.

The model runs on the PHP server computer, not each student's device. Other students accessing this clinic website through the network use that server's Ollama process. Do not expose Ollama itself on a public interface. A future hosted PHP deployment would need its own local model installation.

Optional: set Apache `SetEnv OLLAMA_MODEL "llama3.2:3b"` only after downloading that local model, then restart Apache. The default 1B model is chosen for this laptop's 8 GB RAM. Larger models and concurrent users may exceed the machine's resources. Cloud-tagged models are rejected. HTTP redirects and proxies are disabled for this loopback call.

Each typed request sends only the current question, permitted answers, the typed reply, and synthetic formatting examples to the local model. Inventory, account records, identity fields entered when sharing, and prior students' messages are not included. Choices never invoke AI. Consent is requested before typed replies; saving a transcript for clinic review requires separate consent. Temporary chat state expires after 30 minutes.

The adapter uses JSON schema output, temperature zero, a 4096-token context, and a 384-token output limit. Evidence quotes and allowed option values are validated by PHP. Students confirm interpretations before they become answers. AI concerns can increase urgency, never overrule a declared emergency. Unexpected output prompts clarification or choices. The small model can misinterpret negation, additional symptoms and unusual language; these safeguards do not establish clinical accuracy.

The inference timeout is 60 seconds, with a longer browser timeout for cold starts. If Ollama is closed, the model is missing, memory is low, or interpretation fails, the answer choices remain available. Current per-session limits are 20 AI requests/minute and 30 messages/chat. No result is a diagnosis or medical clearance.

The paper should describe local AI-assisted language interpretation plus rule-based preliminary triage, rather than predefined responses alone. Clinical validation and a model-specific evaluation are still required; see `docs/assessment-rules.md`.

Checks: `C:\xampp\php\php.exe tests/conversation_test.php` and `C:\xampp\php\php.exe tests/assessment_test.php`.
API reference: https://docs.ollama.com/api/chat
Structured outputs: https://docs.ollama.com/capabilities/structured-outputs
## Conversational intake (current default)
Start symptom check now opens local-AI consent and a persistent message composer. The assistant sees the last ten messages with roles, responds conversationally, handles corrections, and asks a relevant follow-up. It is instructed to avoid diagnosis, medicines, treatment instructions or care clearance. Obvious prohibited phrases are filtered, but generated language is not guaranteed clinically safe or accurate. Llama 3.2 1B remains limited.

Review what we discussed extracts schema-constrained candidate facts from recent history. PHP checks allowed choices and exact quotes from student messages; missing, conflicting and unsupported facts remain unknown. The server tracks known facts and chooses missing follow-ups. Students are automatically shown one summary and may correct the conversation before confirming. Only then do deterministic draft rules produce care guidance. Incomplete information leads to clinic consultation, never automatic reassurance. Confirmed results and transcripts use the existing optional Share with clinic flow; nothing is saved to patient records automatically.

Conversations expire after 30 minutes and are limited to 20 exchanges; rate limits and local-only inference remain in effect. Model mistakes, prompt injection, arbitrary medical advice and false-negative danger detection still need clinical testing. A short visible emergency notice remains; do not wait for chat in an emergency. Tests: tests/dialogue_test.php. No clinical approval is implied.

Automatic completion: the server now tracks quoted facts, asks about missing details, and presents a summary without requiring a manual review click. Suitable mild-symptom paths need every required reassuring answer; unsupported symptom paths can reach clinic referral once basic warning, duration and severity details are known. Positive danger answers interrupt immediately. After ten successful turns or repeated unresolved clarification, the bot stops rather than loops and routes incomplete information to clinic review. Quick-answer choices are available beside the message box and are validated against the current pending question. Test: tests/dialogue_progress_test.php.

Current completion adapter: the 1B model interprets one pending detail per typed turn using the existing focused schema. The server retains established answers and selects the next missing question. This avoids asking the small model to extract an entire clinical form at once. Unclear or unsupported replies remain unknown and have answer-choice alternatives; this remains a guided preliminary assessment, not a general-purpose ChatGPT replacement.

## Current student flow: choices first
Start symptom check opens choices immediately, without an AI consent screen or model request. Relevant follow-up choices continue after each selection. Other / unlisted symptoms open a short description box; that text is retained in the assessment and routed to human review without automatic diagnosis. Other unlisted answers may use the optional local interpreter after consent, then return to choices. There is no mandatory final notes box or manual request for assessment: guidance appears when the guided questions finish. Existing conversation endpoints remain available internally but are not the default student flow.

## Student assessment to staff consultation
After the choices-first assessment, students see a next-action section and the reasons/guidance. They may choose Send assessment to clinic, consent to storage, and receive a CC- reference plus instructions to bring that reference and their student ID. A submission is not an appointment, proof of identity, an emergency alert, or confirmation of staff review.

Authorized staff open Pre-Assessments. The inbox has New, Reviewed, In progress and Completed filters, a reference/student-number search, and links to each consultation. Mark reviewed records that staff saw the intake. When the student attends, Verify student / start consultation lets staff look up an official student record and explicitly confirm identity in person. Names and student numbers submitted publicly are not automatically treated as verified.

The consultation page keeps chatbot answers and preliminary guidance separate from staff findings. Staff can save a draft or complete the visit by recording findings, next steps communicated to the student and an outcome. Completion is read-only; concurrent/stale saves are rejected. Repeated start clicks return the same visit. Consultation records are linked to verified student history, with student identity snapshots and recorder/timestamps retained. Students with consultation history cannot be hard-deleted. This is a basic visit record, not a comprehensive EMR or treatment-management module.

Existing installations create the new clinic_consultations table and its version field through ensure_assessment_tables on the staff inbox/consultation/submission routes. New installations include it in database.sql. No database re-import or reset is needed. Database foreign keys retain visit history. Only authorized staff can read/write consultations; mutations require CSRF. Activity logs contain record identifiers, not symptom or consultation-note text.

Run C:\xampp\php\php.exe tests/consultation_test.php with the local database running. The test creates its tables before a transaction and rolls back all synthetic test records. Clinical rules remain drafts requiring nurse/physician validation; this workflow change does not establish medical accuracy.

## Disposal and physical stock counts
Manage Inventory now supports Disposal (staff/admin) and Physical Count (administrator only). A reason is mandatory. Physical counts show the difference and reject stale balances. Each new movement records before/after balances, staff, reference and notes. Repeated submission of the same form is deduplicated. Existing rows are preserved; historical balance fields remain unknown. Disposal and adjustments are separate types excluded from Stock Out usage and forecasting.
Existing installations upgrade the transactions table additively when Manage Inventory is opened; no database re-import is needed. Back up the database before deploying schema changes. New installations use database.sql. Counts do not edit or delete history; compensating movements should cite the original reference. Existing product/batch organization is unchanged in this phase.

## Current submission workflow: review when the student visits
Students receive immediate preliminary guidance; saving is optional. Before consent and on the receipt, the interface explains that saving does not book a visit or guarantee a reply. There is no continuous monitoring, callback commitment, or emergency alert. Students follow the care guidance without waiting for review. At attendance, authorized clinic staff locate the reference, verify identity, review the answers and record the consultation. Reviewed means answers were reviewed, not that the student was contacted or a visit completed. Clinic staff must confirm this proposed operating workflow during the clinic review.

## Medicine issuance during consultation
An active verified consultation has a separate medicine issuance form. Staff select the actual usable batch and quantity; saving creates one linked Stock Out and deducts inventory atomically. Expired/insufficient stock and completed visits are rejected. Retries reuse the original transaction. The visit keeps medicine/batch/unit/expiry and issuer snapshots; stock history links back to the visit. Save visit notes before using the separate issuance form. Do not duplicate an issue through Manage Inventory. The chatbot never selects or prescribes medicine. Existing unlinked stock movements are preserved and not assigned to students automatically.

## Current navigation
The previous Dashboard, Medicines, Manage Inventory, Students, Pre-Assessments, Alerts, Decision Support and Reports navigation is restored. Consultations start from Pre-Assessments. The walk-in entry page is disabled. Compatibility fields and existing saved records are retained.

## Staff usability improvements
Consultation identity lookup accepts student names or numbers and requires explicit verification. Findings, medicine issuance and completion are numbered. Leaving with unsaved findings triggers a warning; submitting a separate form warns about discarded changes. Movement receipts identify medicine, batch, quantity and resulting balance. Use docs/staff-usability-check.html for a supervised fictional-data trial; actual staff evaluation is pending.

## Current chatbot entry: hybrid intake
Start symptom check opens a main-concern text box with a symptom-list alternative. No message is sent until local AI consent is given. A typed concern is interpreted by the focused local adapter and confirmed by the student; relevant guided choices follow. The symptom-list route works without Ollama. Safety stops continue to suppress identity/sharing forms; ordinary results offer optional saving. This supersedes earlier choices-first/default-conversation descriptions.

## Local model trial ? 8 November 2026
The default local model is now llama3.2:3b for evaluation, superseding earlier 1B-default instructions. OLLAMA_MODEL still overrides the default. Keep 1B installed for rollback. All interpretation confirmation, local-only processing, guided fallback and clinical rules remain unchanged. Software checks do not establish clinical accuracy.

## Hosted Groq option
Set Apache environment variables CLINIC_AI_PROVIDER=groq, GROQ_API_KEY (private), and optionally GROQ_MODEL=openai/gpt-oss-120b. Restart Apache and refresh/restart the chatbot to see the provider-specific consent notice. Without an explicit provider setting the system stays on Ollama; there is no automatic cloud fallback. Guided interpretation sends only the current question/choices/reply and synthetic examples; identity and inventory records are not included. Advanced legacy dialogue endpoints may send recent chat messages. Use fictional cases until clinic/privacy review. API keys are server-only and never included in page configuration or error responses. TLS verification remains enabled. Official references: https://console.groq.com/docs/structured-outputs and https://console.groq.com/docs/your-data

## Current student purpose ? advice-only symptom check (10 November 2026)
The student chat ends after preliminary care guidance; no identity or submission form is shown. Existing staff submissions and consultation records remain intact. Groq conversation mode uses recent messages and validated confirmed facts. Exact evidence validation, draft care rules and safety stops remain; guided choices are a fallback. Consent discloses conversation processing. Clinical review is pending.

## Applied triage-assistant behavior
includes/triage_prompt.php contains the symptom-conversation policy. Completed contextual intake gives advice directly, without the previous mandatory summary confirmation. The response includes the three-field final triage object; incomplete turns remain explicitly NEEDS_MORE_INFORMATION. Care criteria and urgency safeguards remain provisional and unchanged.

## Combined batch reordering
Administrator: Medicines ? Link batches for reordering. Explicitly select matching batches and confirm medicine/strength/form/unit, then set one shared reorder point. Nothing is merged by name automatically. Decision Support, PR and APP use summed usable stock and Stock Out usage across linked batches; expired stock is excluded, but historical issues remain demand. Each batch and its transaction history is unchanged. PPMP generation skips groups if any member already has a row for that year; existing PPMP rows are preserved and require manual review. Unlinked batches remain separate pending confirmation.

## Medicines grouped display
The list shows one row per explicitly linked medicine, with total non-expired stock and expandable batch details. Add batch copies the selected medicine identity on the server, creates/uses its reorder group, and records initial stock under the new batch ID. Existing unlinked duplicates need administrator confirmation through Link existing duplicate batches. Search matches any batch but displays the whole group. Sorting/pagination operate on medicine groups. Batch expiry, quantities, edits, dispensing and disposal remain separate.

## Simplified expiry-based receiving
The prior flat Medicines list is restored. Receive stock now asks for the medicine, quantity and received expiry date. Linked medicine choices are deduplicated while receiving. The server reuses the appropriate expiry record or creates a linked record with that expiry, preserving every older balance/expiry and Stock Out history. No fabricated lot code is assigned. Issuing/disposal/counting remain batch-specific. New receipt retries are deduplicated by request token. Past expiry dates are rejected for receiving.
