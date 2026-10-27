/* My Leads: details side panel, inline purpose / remark, call talk time, WhatsApp + e-mail popups
 * with templates, voice notes, column toggles and extra fields ("Add Field"). */
(() => {
    const list = document.getElementById('leadsList');
    if (!list) return;

    const base = list.dataset.panelUrl.replace(/\/0\/panel$/, '');   // /employee/leads
    const url = (id, path) => `${base}/${id}/${path}`;
    const csrf = () => document.querySelector('meta[name=csrf-token]')?.content;
    const toast = (title, body, alert = false) => window.liveToast?.({ type: alert ? 'alert' : 'general', title, body });
    const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const json = async (method, u, body, isForm = false) => {
        const res = await fetch(u, {
            method, credentials: 'same-origin',
            headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf(), ...(isForm ? {} : { 'Content-Type': 'application/json' }) },
            body: body ? (isForm ? body : JSON.stringify(body)) : undefined,
        });
        const data = await res.json().catch(() => ({}));
        if (!res.ok && !data.link) throw new Error(data.message || 'Something went wrong');
        return data;
    };
    const leadOf = (el) => el.closest('[data-lead]');
    const modal = (id) => bootstrap.Modal.getOrCreateInstance(document.getElementById(id));

    /* ---------------- details side panel */
    const panel = document.getElementById('leadPanel');
    const openPanel = async (id) => {
        if (!panel || window.innerWidth < 992) { location.href = `${base}/${id}`; return; }
        panel.classList.remove('d-none');
        document.querySelector('.leads-layout')?.classList.add('with-panel');
        panel.innerHTML = '<div class="text-center py-5"><div class="spinner-border text-primary"></div></div>';
        const res = await fetch(url(id, 'panel'), { credentials: 'same-origin' });
        panel.innerHTML = res.ok ? await res.text() : '<div class="p-3 small text-danger">Could not load the lead.</div>';
        document.querySelectorAll('.lead-row-t.active').forEach((r) => r.classList.remove('active'));
        document.querySelector(`.lead-row-t[data-lead="${id}"]`)?.classList.add('active');
    };
    document.addEventListener('click', (e) => {
        const b = e.target.closest('[data-open-panel]');
        if (b) { e.preventDefault(); openPanel(leadOf(b).dataset.lead); }
        if (e.target.closest('[data-close-panel]')) {
            panel.classList.add('d-none');
            document.querySelector('.leads-layout')?.classList.remove('with-panel');
        }
    });

    /* ---------------- inline purpose / remark */
    document.addEventListener('change', async (e) => {
        const input = e.target.closest('.lead-inline');
        if (!input) return;
        try {
            await json('PATCH', url(leadOf(input).dataset.lead, 'inline'), { [input.dataset.inline]: input.value });
            input.classList.add('saved');
            setTimeout(() => input.classList.remove('saved'), 1200);
        } catch (err) { toast('Not saved', err.message, true); }
    });
    document.addEventListener('keydown', (e) => { if (e.key === 'Enter' && e.target.matches('.lead-inline')) { e.preventDefault(); e.target.blur(); } });

    /* ---------------- calls: tap Call → phone dials; talk time runs until "Save call" */
    let call = null;
    let callTimer = null;
    const fmt = (s) => `${String(Math.floor(s / 60)).padStart(2, '0')}:${String(s % 60).padStart(2, '0')}`;
    const showCall = () => {
        const box = document.getElementById('callEndModal');
        box.querySelector('[data-call-name]').textContent = call.name;
        clearInterval(callTimer);
        const tick = () => { box.querySelector('[data-call-time]').textContent = fmt(Math.floor((Date.now() - call.started) / 1000)); };
        tick(); callTimer = setInterval(tick, 1000);
        modal('callEndModal').show();
    };
    document.addEventListener('click', async (e) => {
        const a = e.target.closest('[data-call]');
        if (!a) return;
        const row = leadOf(a);
        try {
            const res = await json('POST', a.dataset.call);
            call = { id: res.id, lead: row.dataset.lead, name: row.dataset.name, started: Date.now() };
            try { sessionStorage.setItem('portal:call', JSON.stringify(call)); } catch (err) { /* private mode */ }
            setTimeout(showCall, 400);   // the phone app opens; the timer is waiting when you come back
        } catch (err) { toast('Call', err.message, true); }
    });
    document.querySelector('[data-call-end-form]')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        if (!call) return;
        const seconds = Math.floor((Date.now() - call.started) / 1000);
        try {
            const res = await json('POST', url(call.lead, `call/${call.id}`), { seconds, note: e.target.note.value });
            document.querySelectorAll(`[data-lead="${call.lead}"] [data-talk]`).forEach((el) => { el.textContent = res.talk; });
            toast('Call saved', `Talk time ${fmt(seconds)}`);
        } catch (err) { toast('Call not saved', err.message, true); }
        clearInterval(callTimer);
        call = null;
        try { sessionStorage.removeItem('portal:call'); } catch (err) { /* ignore */ }
        e.target.reset();
        modal('callEndModal').hide();
    });
    // Came back after a page reload during the call
    try {
        const saved = JSON.parse(sessionStorage.getItem('portal:call') || 'null');
        if (saved && Date.now() - saved.started < 2 * 3600 * 1000) { call = saved; window.addEventListener('load', showCall); }
    } catch (err) { /* ignore */ }

    /* ---------------- WhatsApp popup */
    let target = null;
    document.addEventListener('click', async (e) => {
        const b = e.target.closest('[data-wa-open-lead]');
        if (!b) return;
        target = leadOf(b);
        const form = document.querySelector('[data-wa-form]');
        form.querySelector('[data-to-name]').textContent = target.dataset.name;
        form.querySelector('[data-to-mobile]').textContent = target.dataset.mobile;
        form.text.value = '';
        const box = form.querySelector('[data-wa-templates]');
        box.innerHTML = '<div class="text-center py-3"><div class="spinner-border spinner-border-sm text-success"></div></div>';
        modal('waModal').show();
        try {
            const t = await json('GET', url(target.dataset.lead, 'templates'));
            box.innerHTML = Object.entries(t.whatsapp).map(([title, text], i) => `
                <label class="tpl-item"><input type="radio" name="tpl" value="${i}" class="form-check-input mt-1">
                <span><b>${esc(title)}</b><small>${esc(text)}</small></span></label>`).join('');
            const texts = Object.values(t.whatsapp);
            box.querySelectorAll('input[name=tpl]').forEach((r) => r.addEventListener('change', () => { form.text.value = texts[r.value]; }));
            const first = box.querySelector('input[name=tpl]');
            if (first) { first.checked = true; form.text.value = texts[0]; }
        } catch (err) { box.innerHTML = '<div class="small text-muted">Type your message below.</div>'; }
    });
    document.querySelector('[data-wa-form]')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const btn = e.target.querySelector('button.btn-success');
        btn.disabled = true;
        try {
            const res = await json('POST', url(target.dataset.lead, 'whatsapp'), { text: e.target.text.value });
            if (res.link) window.open(res.link, '_blank', 'noopener');
            toast('WhatsApp', res.message, !res.ok);
            modal('waModal').hide();
        } catch (err) { toast('WhatsApp', err.message, true); }
        btn.disabled = false;
    });

    /* ---------------- E-mail popup */
    let emailTpl = [];
    document.addEventListener('click', async (e) => {
        const b = e.target.closest('[data-email-open]');
        if (!b) return;
        target = leadOf(b);
        const form = document.querySelector('[data-email-form]');
        form.reset();
        form.querySelector('[data-attach-name]').textContent = 'Attach File (Resume, Brochure, JD etc.)';
        form.to.value = target.dataset.email || '';
        const sel = form.querySelector('[data-email-templates]');
        sel.innerHTML = '<option value="">— Choose a template —</option>';
        modal('emailModal').show();
        try {
            const t = await json('GET', url(target.dataset.lead, 'templates'));
            emailTpl = Object.entries(t.email);
            sel.innerHTML += emailTpl.map(([title], i) => `<option value="${i}">${esc(title)}</option>`).join('');
            if (emailTpl.length) { sel.value = '0'; sel.dispatchEvent(new Event('change')); }
            if (!form.to.value && t.email_to) form.to.value = t.email_to;
        } catch (err) { /* type own */ }
    });
    document.querySelector('[data-email-templates]')?.addEventListener('change', (e) => {
        const t = emailTpl[e.target.value];
        if (!t) return;
        const form = e.target.form;
        form.subject.value = t[1].subject;
        form.body.value = t[1].body;
    });
    document.querySelector('[data-email-form] input[type=file]')?.addEventListener('change', (e) => {
        e.target.closest('form').querySelector('[data-attach-name]').textContent = e.target.files[0]?.name || 'Attach File';
    });
    document.querySelector('[data-email-form]')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const btn = e.target.querySelector('button.btn-primary');
        btn.disabled = true;
        try {
            const res = await json('POST', url(target.dataset.lead, 'email'), new FormData(e.target), true);
            toast('Email', res.message, !res.ok);
            if (res.ok) modal('emailModal').hide();
        } catch (err) { toast('Email', err.message, true); }
        btn.disabled = false;
    });

    /* ---------------- voice notes */
    let rec = null;
    let chunks = [];
    let recStart = 0;
    let recTimer = null;
    let blob = null;
    const vbox = document.getElementById('voiceModal');
    document.addEventListener('click', (e) => {
        const b = e.target.closest('[data-record]');
        if (!b) return;
        target = leadOf(b);
        blob = null;
        vbox.querySelector('[data-voice-name]').textContent = target.dataset.name;
        vbox.querySelector('[data-rec-time]').textContent = '00:00';
        vbox.querySelector('[data-rec-msg]').textContent = 'Tap to start recording';
        vbox.querySelector('[data-rec-save]').classList.add('d-none');
        modal('voiceModal').show();
    });
    vbox?.querySelector('[data-rec-toggle]').addEventListener('click', async (e) => {
        const btn = e.currentTarget;
        if (rec && rec.state === 'recording') { rec.stop(); return; }
        try {
            const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
            rec = new MediaRecorder(stream);
            chunks = [];
            rec.ondataavailable = (ev) => chunks.push(ev.data);
            rec.onstop = () => {
                stream.getTracks().forEach((t) => t.stop());
                blob = new Blob(chunks, { type: rec.mimeType || 'audio/webm' });
                clearInterval(recTimer);
                btn.classList.remove('on');
                vbox.querySelector('[data-rec-msg]').textContent = 'Recorded — tap Save';
                vbox.querySelector('[data-rec-save]').classList.remove('d-none');
            };
            rec.start();
            recStart = Date.now();
            btn.classList.add('on');
            vbox.querySelector('[data-rec-msg]').textContent = 'Recording… tap to stop';
            recTimer = setInterval(() => { vbox.querySelector('[data-rec-time]').textContent = fmt(Math.floor((Date.now() - recStart) / 1000)); }, 500);
        } catch (err) { toast('Microphone', 'Allow the microphone to record a voice note.', true); }
    });
    vbox?.querySelector('[data-rec-save]').addEventListener('click', async () => {
        if (!blob) return;
        const fd = new FormData();
        const ext = blob.type.includes('mp4') ? 'm4a' : (blob.type.includes('ogg') ? 'ogg' : 'webm'); // iPhone records mp4
        fd.append('audio', blob, `voice-note.${ext}`);
        fd.append('seconds', Math.floor((Date.now() - recStart) / 1000));
        try { const res = await json('POST', url(target.dataset.lead, 'voice-note'), fd, true); toast('Voice note', res.message); modal('voiceModal').hide(); }
        catch (err) { toast('Voice note', err.message, true); }
    });

    /* ---------------- columns + extra fields (remembered) */
    const KEY = 'portal:leadCols';
    const load = () => { try { return JSON.parse(localStorage.getItem(KEY)) || {}; } catch (e) { return {}; } };
    const save = (v) => { try { localStorage.setItem(KEY, JSON.stringify(v)); } catch (e) { /* ignore */ } };
    const apply = () => {
        const st = load();
        document.querySelectorAll('[data-col-toggle]').forEach((cb) => {
            const on = st.hide ? !st.hide.includes(cb.dataset.colToggle) : true;
            cb.checked = on;
            document.querySelectorAll(`[data-col="${cb.dataset.colToggle}"]`).forEach((c) => c.classList.toggle('d-none', !on));
        });
        document.querySelectorAll('[data-extra-field]').forEach((cb) => {
            const on = (st.extra || []).includes(cb.dataset.extraField);
            cb.checked = on;
            document.querySelectorAll(`[data-extra-col="${CSS.escape(cb.dataset.extraField)}"]`).forEach((c) => c.classList.toggle('d-none', !on));
        });
    };
    document.addEventListener('change', (e) => {
        if (e.target.matches('[data-col-toggle]')) {
            const st = load();
            const hide = new Set(st.hide || []);
            e.target.checked ? hide.delete(e.target.dataset.colToggle) : hide.add(e.target.dataset.colToggle);
            save({ ...st, hide: [...hide] }); apply();
        }
        if (e.target.matches('[data-extra-field]')) {
            const st = load();
            const extra = new Set(st.extra || []);
            e.target.checked ? extra.add(e.target.dataset.extraField) : extra.delete(e.target.dataset.extraField);
            save({ ...st, extra: [...extra] }); apply();
        }
    });
    document.querySelector('[data-field-search]')?.addEventListener('input', (e) => {
        const q = e.target.value.toLowerCase();
        document.querySelectorAll('[data-field-item]').forEach((i) => i.classList.toggle('d-none', !i.textContent.toLowerCase().includes(q)));
    });
    document.addEventListener('live:updated', (e) => { if (e.detail.key === 'leads') apply(); });
    apply();
})();
