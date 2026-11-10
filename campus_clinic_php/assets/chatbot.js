(() => {
    const panel = document.getElementById('clinic-chat');
    if (!panel) return;
    const toggle = document.getElementById('clinic-chat-toggle');
    const log = document.getElementById('clinic-chat-messages');
    const controls = document.getElementById('clinic-chat-controls');
    const restart = document.getElementById('clinic-chat-restart');
    const questions = JSON.parse(document.getElementById('clinic-chat-questions').textContent);
    const aiConfig = JSON.parse(document.getElementById('clinic-ai-config')?.textContent || '{}');
    const aiDisclosure = aiConfig.disclosure || 'Typed replies use the configured AI provider. Use choices if you do not wish to send text.';
    let stoppedSafety = 'none';
    let answers = {}, step = 0, busy = false, chatId = '', aiConsent = false, preferTyping = false, dialogueId = '', conversationSafety = 'none';
    function open(value) {
        panel.hidden = !value; toggle.setAttribute('aria-expanded', String(value));
        if (value) (controls.querySelector('button,input') || log).focus(); else toggle.focus();
    }
    toggle.addEventListener('click', () => open(panel.hidden));
    document.getElementById('clinic-chat-close').addEventListener('click', () => open(false));
    panel.addEventListener('keydown', e => { if (e.key === 'Escape') open(false); });
    function add(text, type = 'bot') {
        const p = document.createElement('p'); p.className = `clinic-chat-${type}`;
        p.textContent = text; log.appendChild(p); log.scrollTop = log.scrollHeight;
    }
    function button(label, callback) {
        const b = document.createElement('button'); b.type = 'button'; b.textContent = label;
        b.addEventListener('click', callback); controls.appendChild(b); return b;
    }
    function focusControls() { const first = controls.querySelector('button,input,textarea'); if (first && !panel.hidden) first.focus(); }
    function begin() {
        if (busy) return;
        stoppedSafety = 'none'; answers = {}; step = 0; chatId = ''; aiConsent = false; preferTyping = false; dialogueId = ''; conversationSafety = 'none'; log.replaceChildren(); controls.replaceChildren();
        add('Feeling unwell and not sure whether to visit the clinic? Tell me what is bothering you. I will ask about your symptoms and help you consider a clinic visit or, for selected mild adult symptoms, self-care and monitoring. This prototype cannot diagnose illness and still needs clinic validation.');
        add('Describe your symptoms. I will ask relevant follow-up questions and give guidance on whether to visit the clinic, monitor at home or seek immediate help. You do not need to enter your name or submit a form.');
        button('Start symptom check', startConversation);
        focusControls();
    }
    function startConversation() {
        controls.replaceChildren();
        add('What is bothering you? For example: I have a fever, or my feet hurt.');
        conversationForm();
    }
    function guidedFallback() {
        if (busy) return;
        if (conversationSafety !== 'none') {
            answers = {warning:conversationSafety === 'emergency' ? 'yes' : 'unsure'};
            emergencyText(conversationSafety); return;
        }
        answers = {}; step = 0; chatId = ''; preferTyping = false;
        add('We can use the guided questions instead. Please answer them afresh so I do not carry over an unconfirmed interpretation.');
        ask();
    }
    async function ensureDialogue() {
        if (!dialogueId) {
            const data = await post({action:'dialogue_start', ai_consent:'yes'});
            dialogueId = data.dialogue_id;
        }
    }
    function conversationForm(nextQuestion = null) {
        controls.replaceChildren();
        const form = document.createElement('form');
        const label = document.createElement('label'); label.textContent = 'Message the clinic assistant';
        const input = document.createElement('textarea'); input.rows = 2; input.maxLength = 500; input.required = true;
        input.placeholder = 'Describe how you feel, ask a question, or correct something...'; label.appendChild(input);
        const send = document.createElement('button'); send.type = 'submit'; send.textContent = 'Send'; form.append(label,send); controls.appendChild(form);
        button('Get guidance with current information', reviewConversation);
        button('Use guided answer choices', guidedFallback);
        let selectedKey = '', selectedValue = '';
        input.addEventListener('input', () => { selectedKey = ''; selectedValue = ''; });
        if (nextQuestion) Object.entries(nextQuestion.options).forEach(([value,label]) => button(label, () => {
            if (busy) return; input.value = label; selectedKey = nextQuestion.key; selectedValue = value; form.requestSubmit();
        }));
        form.addEventListener('submit', async event => {
            event.preventDefault(); const message = input.value.trim(); if (!message || busy) return;
            if (!aiConsent) {
                controls.replaceChildren(); add(aiDisclosure);
                button('Agree and continue', () => { aiConsent=true; controls.replaceChildren(form); form.requestSubmit(); });
                button('Use guided answer choices', guidedFallback); focusControls(); return;
            }
            add(message,'user');
            if (/\bI (?:cannot|can.t|am unable to) breathe\b/i.test(message)) { emergencyText('emergency'); return; }
            input.value = ''; setBusy(true);
            const thinking = document.createElement('p'); thinking.className = 'clinic-chat-bot'; thinking.textContent = 'Thinking...'; log.appendChild(thinking); log.scrollTop = log.scrollHeight;
            try {
                await ensureDialogue();
                const data = await post({action:'dialogue_turn', dialogue_id:dialogueId, message, answer_key:selectedKey, answer_value:selectedValue});
                selectedKey = ''; selectedValue = '';
                thinking.remove(); add(data.reply);
                if (data.safety !== 'none') { conversationSafety = data.safety; emergencyText(data.safety); return; }
                if (data.ready && data.assessment) finishAdvice(data.assessment);
                else if (data.ready && data.review) showConversationReview(data.review);
                else conversationForm(data.next_question || null);
            } catch (error) {
                thinking.remove(); input.value = message;
                add(error.name === 'AbortError' ? 'The AI took too long. Your message is still in the reply box; retry or use the guided questions.' : error.message);
            } finally { thinking.remove(); setBusy(false); focusControls(); }
        }); focusControls();
    }
    function showConversationReview(data) {
        add(data.summary); add('Please check the recorded details before I give preliminary guidance. Correct anything wrong; missing details will lead to clinic review.');
        controls.replaceChildren();
        button('Confirm details and show guidance', async () => {
            if (busy) return; setBusy(true);
            try {
                const result = await post({action:'dialogue_confirm',dialogue_id:dialogueId,review_token:data.review_token,confirmed:'yes'});
                add(result.result.assessment + '\n\nWhat to do next:\n' + (result.result.next_action || 'Discuss this preliminary result with clinic staff.') + '\n\nWhy / guidance:\n' + result.result.advice); finishAdvice(result.result, result.assessment_token);
            } catch (error) { add(error.message); }
            finally { setBusy(false); focusControls(); }
        });
        button('Correct or add something', () => { add('Tell me what to correct or add.'); conversationForm(); });
    }
    async function reviewConversation() {
        if (busy) return;
        if (!dialogueId) { add('Tell me what is bothering you first.'); return; }
        setBusy(true);
        try { showConversationReview(await post({action:'dialogue_review',dialogue_id:dialogueId})); }
        catch (error) { add(error.message); }
        finally { setBusy(false); focusControls(); }
    }
    restart.addEventListener('click', begin);
    function emergencyText(priority) {
        if (stoppedSafety === 'emergency') priority = 'emergency';
        stoppedSafety = priority; controls.replaceChildren();
        add(priority === 'emergency'
            ? 'Seek emergency medical help now. Ask someone nearby to help contact local emergency services or the nearest emergency department. Do not wait for this chat or a clinic reply. This system does not dispatch help.'
            : 'Contact clinic staff or a healthcare professional now for prompt assessment. If unavailable, use an urgent medical service. If you may be having an emergency, seek emergency help now.');
        add('This symptom check has stopped. You do not need to enter your name, student number, or send a form before seeking help.');
    }
    function accept(q, value, label, initial = false) {
        if (busy) return;
        answers[q.key] = value; add('Confirmed: ' + label, 'user'); controls.replaceChildren();
        const escalation = (q.stop || {})[value];
        if (escalation) { emergencyText(escalation); return; }
        if (initial) step = 0; else step++;
        ask();
    }
    function choices(q, initial = false) {
        controls.replaceChildren();
        Object.entries(q.options).forEach(([value,label]) => button(label, () => {
            if (q.key === 'symptom' && value === 'other') describeOther(q,initial);
            else accept(q,value,label,initial);
        }));
        if (q.key !== 'symptom') button('My answer is not listed', () => textReply(q,initial));
        focusControls();
    }
    function describeOther(q,initial = false) {
        controls.replaceChildren();
        add('What symptom is missing from the choices? I will include your description for human review; I will not guess a diagnosis.');
        const form = document.createElement('form');
        const label = document.createElement('label'); label.textContent = 'Describe the unlisted symptom';
        const input = document.createElement('textarea'); input.rows = 2; input.maxLength = 450; input.required = true; label.appendChild(input);
        const submit = document.createElement('button'); submit.type = 'submit'; submit.textContent = 'Continue';
        form.append(label,submit); controls.appendChild(form);
        button('Back to symptom choices', () => choices(q,initial));
        form.addEventListener('submit', event => {
            event.preventDefault(); const description = input.value.trim(); if (!description || busy) return;
            answers.details = 'Unlisted symptom: ' + description;
            accept(q,'other',description,initial);
        }); focusControls();
    }
    function ask() {
        controls.replaceChildren();
        while (step < questions.length && (Object.hasOwn(answers,questions[step].key) || !Object.entries(questions[step].when || {}).every(([key,value]) => answers[key] === value))) step++;
        if (step === questions.length) return details();
        const q = questions[step]; add(q.text);
        choices(q);
    }
    function textReply(q, initial = false) {
        controls.replaceChildren();
        if (!aiConsent && !initial) {
            add(aiDisclosure);
            button('Agree and type reply', () => { aiConsent = true; preferTyping = false; textReply(q,initial); });
            button('Back to answer choices', () => choices(q,initial));
            focusControls(); return;
        }
        const form = document.createElement('form');
        const label = document.createElement('label'); label.textContent = initial ? 'Describe your main symptom' : 'Your reply';
        const input = document.createElement('textarea'); input.rows = 2; input.maxLength = 500; input.required = true;
        input.placeholder = initial ? 'For example: my head hurts' : 'Answer in your own words...'; label.appendChild(input);
        const send = document.createElement('button'); send.type = 'submit'; send.textContent = 'Send'; form.append(label,send); controls.appendChild(form);
        button(initial ? 'Choose symptoms from a list' : 'Show answer choices', () => { if(initial)add(q.text); choices(q,initial); });
        form.addEventListener('submit', async event => {
            event.preventDefault(); const message = input.value.trim(); if (!message || busy) return;
            if (!aiConsent) {
                controls.replaceChildren();
                add(aiDisclosure);
                button('Agree and interpret my message', () => { aiConsent = true; controls.replaceChildren(form); form.requestSubmit(); });
                button('Use symptom choices instead', () => { add(q.text); choices(q,initial); });
                focusControls(); return;
            }
            add(message,'user'); const thinking = document.createElement('p'); thinking.className = 'clinic-chat-bot'; thinking.textContent = 'Interpreting your reply...'; log.appendChild(thinking); log.scrollTop = log.scrollHeight; setBusy(true);
            try {
                if (!chatId) {
                    const session = await post({action:'chat_start', ai_consent:'yes'});
                    chatId = session.chat_id;
                }
                const data = await post({action:'interpret', chat_id:chatId, question:q.key, message});
                thinking.remove(); const interpretation = data.interpretation;
                if (interpretation.safety !== 'none') {
                    answers = {warning:interpretation.safety === 'emergency' ? 'yes' : 'unsure'};
                    emergencyText(interpretation.safety); return;
                }
                if (interpretation.option === 'unclassified') {
                    add('I could not confidently match that answer. Could you clarify, or choose the answer that fits? ' + q.text);
                    textReply(q,initial);
                } else {
                    controls.replaceChildren();
                    const value = interpretation.option, optionLabel = q.options[value];
                    add('I understood your answer as: ' + optionLabel + '. Is that correct?');
                    button('Yes, that is correct', () => accept(q,value,optionLabel,initial));
                    button('Let me explain again', () => textReply(q,initial));
                    button('Show answer choices', () => choices(q,initial));
                }
            } catch (error) {
                add(error.name === 'AbortError' ? 'The AI request timed out. You can use the answer choices instead. Do not delay urgent care.' : error.message);
            } finally { thinking.remove(); setBusy(false); focusControls(); }
        }); focusControls();
    }
    function details() { assess(); }
    async function post(values) {
        const controller = new AbortController(); const timer = setTimeout(() => controller.abort(), 75000);
        try {
            const response = await fetch('chatbot.php', {method:'POST', credentials:'same-origin', signal:controller.signal,
                body:new URLSearchParams({...values, ai_provider:aiConfig.provider || 'ollama', csrf:panel.dataset.csrf})});
            if (response.redirected) throw new Error('Your session changed. Refresh the page and start again.');
            let data;
            try { data = await response.json(); } catch (_) { throw new Error('Clinic service unavailable. Retry or contact the clinic directly. Do not delay urgent care.'); }
            if (!response.ok) throw new Error(data.error || 'Unable to complete the request.');
            return data;
        } finally { clearTimeout(timer); }
    }
    function setBusy(value) { busy = value; restart.disabled = value; controls.querySelectorAll('button,input,textarea').forEach(el => el.disabled = value); }
    async function assess() {
        if (stoppedSafety !== 'none') return;
        controls.replaceChildren(); add('Preparing your summary...'); setBusy(true);
        try {
            let data = await post({action:'assess', answers:JSON.stringify(answers), chat_id:chatId});
            add(data.result.assessment + '\n\nWhat to do next:\n' + (data.result.next_action || 'Discuss this preliminary result with clinic staff.') + '\n\nWhy / guidance:\n' + data.result.advice + '\n\nYour answers:\n' + data.result.summary);
            add('This is preliminary guidance, not a confirmed diagnosis. You do not need to submit this form to seek care.'); finishAdvice(data.result, data.assessment_token);
        } catch (error) {
            add(error.name === 'AbortError' ? 'The request timed out. Do not delay urgent care.' : error.message); button('Retry assessment', assess);
        } finally { setBusy(false); focusControls(); }
    }
    function finishAdvice(result) {
        controls.replaceChildren();
        if (stoppedSafety !== 'none' || ['emergency','urgent'].includes(result.priority)) {
            emergencyText(stoppedSafety !== 'none' ? stoppedSafety : result.priority); return;
        }
        add('Your symptom check is complete. Follow the guidance above. You can still contact the clinic if worried. No name or submission is required.');
    }
    begin(); if (document.body.dataset.openAssessment === 'yes') open(true);
})();
