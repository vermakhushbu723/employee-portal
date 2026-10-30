/* WhatsApp inside the portal: side box (minimize / maximize / close). Shows the real QR code to link
 * the user's own WhatsApp, then their chats, conversations and a message box — no new tab or window.
 * Data comes from the portal (/wa/*), which talks to the WhatsApp gateway on the server. */
(() => {
    const fab = document.getElementById('waFab');
    const panel = document.getElementById('waPanel');
    if (!fab || !panel) return;

    // WhatsApp Web mode (default): the button opens the real web.whatsapp.com in its docked window (js/wa-web.js)
    if (fab.dataset.waMode !== 'gateway') {
        fab.title = 'WhatsApp Web';
        fab.addEventListener('click', (e) => { e.preventDefault(); window.portalWA?.open(); });
        return;
    }

    const urls = JSON.parse(panel.dataset.urls);
    const KEY = 'portal:waSide';
    const load = () => { try { return JSON.parse(localStorage.getItem(KEY)) || {}; } catch (e) { return {}; } };
    const save = (patch) => { try { localStorage.setItem(KEY, JSON.stringify({ ...load(), ...patch })); } catch (e) { /* private mode */ } };
    const $ = (s) => panel.querySelector(s);
    const csrf = () => document.querySelector('meta[name=csrf-token]')?.content;
    const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

    let view = 'loading';
    let state = null;       // gateway state
    let openJid = null;
    let chatName = '';
    let timer = null;
    let started = false;
    let lastMsgKey = '';

    const api = async (method, url, data) => {
        const opts = { method, credentials: 'same-origin', headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf() } };
        if (method === 'GET' && data) url += `?${new URLSearchParams(data)}`;
        if (method !== 'GET') opts.body = JSON.stringify(data || {});
        const res = await fetch(url, opts);
        const json = await res.json().catch(() => ({}));
        if (!res.ok) { const err = new Error(json.error || json.message || 'WhatsApp request failed'); err.status = res.status; throw err; }
        return json;
    };

    /* ---------------- views */
    const show = (name) => {
        view = name;
        panel.querySelectorAll('[data-view]').forEach((v) => v.classList.toggle('d-none', v.dataset.view !== name));
        const inChat = name === 'chat' || name === 'new';
        $('[data-wa-back]').classList.toggle('d-none', !inChat);
        $('[data-wa-logo]').classList.toggle('d-none', inChat);
        $('[data-wa-menu]').classList.toggle('d-none', !['chats', 'chat', 'new'].includes(name));
        $('[data-wa-title]').textContent = name === 'chat' ? chatName : name === 'new' ? 'New chat' : 'WhatsApp';
        if (name !== 'chat') $('[data-wa-sub]').textContent = state === 'connected' ? (load().me || 'Connected') : '';
    };
    const fail = (msg) => { $('[data-wa-error]').textContent = msg; show('error'); };
    const visible = () => !panel.classList.contains('d-none') && !panel.classList.contains('minimized');

    /* ---------------- linking */
    const applyStatus = (s) => {
        state = s.state;
        if (s.state === 'connected') {
            save({ linked: true, me: s.me?.name || (s.me?.id ? '+' + s.me.id.split('@')[0] : '') });
            if (['loading', 'qr', 'pair', 'error'].includes(view)) { show('chats'); loadChats(); }
            return;
        }
        if (s.state === 'qr' && s.qr) {
            $('[data-wa-qr]').src = s.qr;
            if (view !== 'qr' && view !== 'pair') show('qr');   // stays on the phone-number screen while a code is used
            return;
        }
        if (s.state === 'logged_out') { save({ linked: false }); started = false; boot(); return; }
        $('[data-wa-loading-text]').textContent = s.state === 'reconnecting' ? 'Reconnecting to WhatsApp…' : 'Connecting to WhatsApp…';
        if (view !== 'chats' && view !== 'chat') show('loading');
    };

    const boot = async () => {
        show('loading');
        try {
            applyStatus(await api('POST', urls.start));
            started = true;
        } catch (e) { fail(e.message); }
    };

    /* ---------------- chats */
    const fmtTime = (ts) => {
        if (!ts) return '';
        const d = new Date(ts * 1000);
        const today = new Date();
        return d.toDateString() === today.toDateString()
            ? d.toLocaleTimeString('en-IN', { hour: '2-digit', minute: '2-digit' })
            : d.toLocaleDateString('en-IN', { day: '2-digit', month: 'short' });
    };
    const avatar = (name, group) => `<span class="wa-av ${group ? 'grp' : ''}">${group ? '<i class="bi bi-people-fill"></i>' : esc((name || '?').replace(/^\+/, '')[0] || '?')}</span>`;

    const loadChats = async () => {
        try {
            const data = await api('GET', urls.chats, { q: $('[data-wa-search]').value.trim() });
            if (data.state && data.state !== 'connected') { applyStatus({ state: data.state }); }
            const total = data.chats.reduce((n, c) => n + (c.unread || 0), 0);
            setBadge(total);
            if (view !== 'chats') return;
            const list = $('[data-wa-chats]');
            list.innerHTML = data.chats.length
                ? data.chats.map((c) => `
                    <button type="button" class="wa-chat" data-jid="${esc(c.id)}" data-name="${esc(c.name)}">
                        ${avatar(c.name, c.group)}
                        <span class="wa-chat-main">
                            <span class="wa-chat-top"><b>${esc(c.name)}</b><small class="${c.unread ? 'text-success fw-bold' : ''}">${fmtTime(c.ts)}</small></span>
                            <span class="wa-chat-bottom"><span>${esc(c.last || '')}</span>${c.unread ? `<em>${c.unread}</em>` : ''}</span>
                        </span>
                    </button>`).join('')
                : `<div class="small text-muted text-center p-4">${$('[data-wa-search]').value ? 'No chat found.' : 'Your chats appear here after the phone finishes syncing. New messages show up automatically.'}</div>`;
        } catch (e) {
            if (e.status === 503) fail(e.message);
        }
    };

    const openChat = (jid, name) => {
        openJid = jid;
        chatName = name;
        lastMsgKey = '';
        $('[data-wa-messages]').innerHTML = '<div class="text-center py-4"><div class="spinner-border spinner-border-sm text-success"></div></div>';
        show('chat');
        $('[data-wa-sub]').textContent = jid.endsWith('@g.us') ? 'Group' : '+' + jid.split('@')[0];
        loadMessages();
        setTimeout(() => $('[data-wa-compose] input').focus(), 50);
    };

    const loadMessages = async () => {
        if (!openJid) return;
        try {
            const data = await api('GET', urls.messages, { jid: openJid });
            const key = data.messages.length + ':' + (data.messages.at(-1)?.id || '');
            if (key === lastMsgKey || view !== 'chat') return;
            lastMsgKey = key;
            const box = $('[data-wa-messages]');
            const atBottom = box.scrollHeight - box.scrollTop - box.clientHeight < 60;
            let day = '';
            box.innerHTML = data.messages.length ? data.messages.map((m) => {
                const d = new Date(m.ts * 1000).toDateString();
                const sep = d !== day ? `<div class="wa-day">${new Date(m.ts * 1000).toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' })}</div>` : '';
                day = d;
                return `${sep}<div class="wa-msg ${m.fromMe ? 'me' : ''}">${m.sender ? `<b>${esc(m.sender)}</b>` : ''}<span>${esc(m.text).replace(/\n/g, '<br>')}</span><small>${new Date(m.ts * 1000).toLocaleTimeString('en-IN', { hour: '2-digit', minute: '2-digit' })}</small></div>`;
            }).join('') : '<div class="small text-muted text-center p-4">No messages yet — say hello!</div>';
            if (atBottom || lastMsgKey.startsWith(data.messages.length + ':')) box.scrollTop = box.scrollHeight;
        } catch (e) { /* next round */ }
    };

    /* ---------------- send */
    $('[data-wa-compose]').addEventListener('submit', async (e) => {
        e.preventDefault();
        const input = e.target.querySelector('input');
        const text = input.value.trim();
        if (!text || !openJid) return;
        input.value = '';
        const box = $('[data-wa-messages]');
        box.insertAdjacentHTML('beforeend', `<div class="wa-msg me pending"><span>${esc(text)}</span><small><i class="bi bi-clock"></i></small></div>`);
        box.scrollTop = box.scrollHeight;
        try { await api('POST', urls.send, { jid: openJid, text }); lastMsgKey = ''; loadMessages(); }
        catch (err) { box.lastElementChild.classList.add('failed'); box.lastElementChild.querySelector('small').textContent = 'Not sent'; window.liveToast?.({ type: 'alert', title: 'WhatsApp', body: err.message }); }
    });
    $('[data-wa-new-form]').addEventListener('submit', async (e) => {
        e.preventDefault();
        const f = e.target;
        try {
            const res = await api('POST', urls.send, { number: f.number.value, text: f.text.value });
            f.reset();
            openChat(res.jid, '+' + res.jid.split('@')[0]);
        } catch (err) { window.liveToast?.({ type: 'alert', title: 'WhatsApp', body: err.message }); }
    });

    /* ---------------- panel controls */
    const minimize = (on) => {
        panel.classList.toggle('minimized', on);
        document.body.classList.toggle('wa-docked', !on && !panel.classList.contains('d-none'));
        save({ min: on });
    };
    const maximize = (on) => {
        panel.classList.toggle('maximized', on);
        $('[data-wa-max] i').className = `bi bi-arrows-angle-${on ? 'contract' : 'expand'}`;
        save({ max: on });
    };
    const open = (on) => {
        panel.classList.toggle('d-none', !on);
        document.body.classList.toggle('wa-docked', on && !panel.classList.contains('minimized'));
        save({ open: on });
        if (on && !started) boot();
        if (on) tick();
    };

    fab.addEventListener('click', (e) => {
        e.preventDefault();
        if (panel.classList.contains('d-none') || panel.classList.contains('minimized')) { open(true); minimize(false); }
        else open(false);
    });
    $('[data-wa-min]').addEventListener('click', () => minimize(!panel.classList.contains('minimized')));
    $('[data-wa-max]').addEventListener('click', () => maximize(!panel.classList.contains('maximized')));
    $('[data-wa-close]').addEventListener('click', () => open(false));
    $('[data-wa-back]').addEventListener('click', () => { openJid = null; show('chats'); loadChats(); });
    $('[data-wa-new]').addEventListener('click', () => show('new'));
    $('[data-wa-retry]').addEventListener('click', boot);
    // Link with phone number: the gateway asks WhatsApp for an 8-letter code, typed on the phone
    $('[data-wa-pair-open]').addEventListener('click', () => show('pair'));
    $('[data-wa-qr-open]').addEventListener('click', () => show('qr'));
    $('[data-wa-pair-form]').addEventListener('submit', async (e) => {
        e.preventDefault();
        const btn = e.target.querySelector('button.btn-success');
        const box = $('[data-wa-pair-code]');
        let number = e.target.number.value.replace(/\D/g, '');
        if (number.length === 10) number = `91${number}`;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Getting code…';
        try {
            const res = await api('POST', urls.pair, { number });
            box.innerHTML = `<small>Your code</small><b>${esc(res.code)}</b><small>Type it in WhatsApp within 2 minutes</small>`;
            box.classList.remove('d-none');
        } catch (err) {
            box.innerHTML = `<small class="text-danger">${esc(err.message)}</small>`;
            box.classList.remove('d-none');
        } finally {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-key me-1"></i>Get new code';
        }
    });
    $('[data-wa-logout]').addEventListener('click', async () => {
        if (!confirm('Log out WhatsApp from the portal? You can link it again with the QR code.')) return;
        try { await api('POST', urls.logout); } catch (e) { /* already gone */ }
        save({ linked: false, me: '' }); started = false; openJid = null; boot();
    });
    $('[data-wa-chats]').addEventListener('click', (e) => {
        const b = e.target.closest('[data-jid]');
        if (b) openChat(b.dataset.jid, b.dataset.name);
    });
    let searchTimer;
    $('[data-wa-search]').addEventListener('input', () => { clearTimeout(searchTimer); searchTimer = setTimeout(loadChats, 300); });
    panel.querySelector('.wa-side-head').addEventListener('dblclick', (e) => { if (!e.target.closest('button')) minimize(!panel.classList.contains('minimized')); });

    /* ---------------- unread badge on the WhatsApp button */
    const setBadge = (n) => {
        let b = fab.querySelector('.wa-count');
        if (!n) { b?.remove(); return; }
        if (!b) { b = document.createElement('span'); b.className = 'wa-count'; fab.appendChild(b); }
        b.textContent = n > 99 ? '99+' : n;
    };

    /* ---------------- polling: fast while you look, slow in the background */
    const tick = async () => {
        clearTimeout(timer);
        if (document.hidden) { timer = setTimeout(tick, 15000); return; }
        let next = 60000;
        if (visible() && started) {
            if (view === 'qr' || view === 'pair' || view === 'loading') {
                try { applyStatus(await api('GET', urls.status)); } catch (e) { /* retry */ }
                next = 2500;
            } else if (view === 'chat') { await loadMessages(); next = 3000; }
            else if (view === 'chats') { await loadChats(); next = 6000; }
            else next = 6000;
        } else if (load().linked) {
            if (!started) { try { await api('POST', urls.start); started = true; } catch (e) { /* later */ } }
            await loadChats(); // keeps the unread badge fresh
        }
        timer = setTimeout(tick, next);
    };

    // Restore last layout
    const st = load();
    if (st.max) maximize(true);
    if (st.open) { open(true); minimize(!!st.min); }
    else if (st.linked) tick();
})();
