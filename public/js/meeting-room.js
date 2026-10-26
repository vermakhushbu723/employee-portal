/*
 * Built-in meeting room (WebRTC mesh, up to 8 people).
 * Video / audio go directly between browsers. The portal only relays the small connection
 * messages, polled about once a second:
 *   - whoever joins later sends an "offer" to everyone already in the room
 *   - the others answer; both sides exchange network "candidates"
 * Chat lines use the same relay. Screen share swaps the camera track without reconnecting.
 */
(() => {
    const root = document.getElementById('meetRoom');
    if (!root) return;

    const cfg = JSON.parse(root.dataset.room);
    const $ = (sel) => root.querySelector(sel);
    const $$ = (sel) => [...root.querySelectorAll(sel)];
    const csrf = () => document.querySelector('meta[name=csrf-token]')?.content;
    const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

    let local = null;          // MediaStream from camera / mic
    let screen = null;         // MediaStream from screen share
    let me = null;             // { id, secret, name }
    let after = 0;
    let ice = [];
    let polling = null;
    let started = 0;
    let micOn = true;
    let camOn = true;
    const peers = new Map();   // id -> { pc, name, role, tile, pending: [], remoteSet }

    /* ---------------- helpers */
    const post = async (url, data, keepalive = false) => {
        const body = new URLSearchParams({ _token: csrf(), ...data });
        return fetch(url, { method: 'POST', body, credentials: 'same-origin', keepalive, headers: { Accept: 'application/json' } });
    };
    // Outgoing messages are batched (one request about every 250 ms instead of one per network candidate)
    let outbox = [];
    let flushing = false;
    const flush = async () => {
        if (!me || flushing || !outbox.length) return;
        flushing = true;
        const batch = outbox.splice(0, 40);
        try {
            const res = await post(cfg.signal, { peer: me.id, secret: me.secret, batch: JSON.stringify(batch) });
            if (!res.ok && res.status !== 403) outbox = batch.concat(outbox); // retry next round
        } catch (e) { outbox = batch.concat(outbox); }
        finally { flushing = false; }
    };
    setInterval(flush, 250);
    const send = (to, type, payload) => {
        if (!me) return;
        outbox.push({ to: to || null, type, payload: JSON.stringify(payload) });
        if (type !== 'candidate') flush(); // offers / answers / chat go out at once
    };
    const toast = (title, body) => window.liveToast?.({ type: 'general', title, body });

    const syncButtons = () => {
        $$('[data-toggle-mic]').forEach((b) => { b.classList.toggle('off', !micOn); b.querySelector('i').className = `bi bi-mic${micOn ? '-fill' : '-mute-fill'}`; });
        $$('[data-toggle-cam]').forEach((b) => { b.classList.toggle('off', !camOn); b.querySelector('i').className = `bi bi-camera-video${camOn ? '-fill' : '-off-fill'}`; });
        $('[data-local-muted]')?.classList.toggle('d-none', micOn);
        const noVideo = !camOn || !local?.getVideoTracks().length;
        $('[data-local-off]')?.classList.toggle('d-none', !noVideo || !!screen);
        $('[data-preview-off]')?.classList.toggle('d-none', !noVideo);
    };

    /* ---------------- camera / mic */
    const getMedia = async () => {
        try {
            local = await navigator.mediaDevices.getUserMedia({ audio: { echoCancellation: true, noiseSuppression: true }, video: { width: { ideal: 1280 }, height: { ideal: 720 }, facingMode: 'user' } });
        } catch (e) {
            try { local = await navigator.mediaDevices.getUserMedia({ audio: true }); camOn = false; } // no camera
            catch (e2) { local = new MediaStream(); micOn = false; camOn = false; $('[data-lobby-msg]').textContent = 'Camera / microphone blocked — you can still join and watch. Allow them in the browser settings to speak.'; }
        }
        $('[data-preview]').srcObject = local;
        $('[data-local]').srcObject = local;
        syncButtons();
    };
    const setMic = (on) => { micOn = on && !!local?.getAudioTracks().length; local?.getAudioTracks().forEach((t) => { t.enabled = micOn; }); syncButtons(); };
    const setCam = (on) => { camOn = on && !!local?.getVideoTracks().length; local?.getVideoTracks().forEach((t) => { t.enabled = camOn; }); syncButtons(); };

    /* ---------------- tiles */
    const updateCount = () => {
        const n = peers.size + 1;
        $$('[data-count], [data-count2]').forEach((el) => { el.textContent = n; });
        $('[data-grid]').dataset.n = Math.min(n, 9);
        const people = [`<li class="meet-person"><span class="meet-mini">${esc((me?.name || 'You')[0])}</span>${esc(me?.name || 'You')} <span class="opacity-75">(you)</span></li>`]
            .concat([...peers.values()].map((p) => `<li class="meet-person"><span class="meet-mini">${esc(p.name[0])}</span>${esc(p.name)} <span class="opacity-75">${p.role === 'guest' ? '(customer)' : ''}</span></li>`));
        $('[data-people]').innerHTML = people.join('');
    };
    const addTile = (id, name) => {
        const tile = document.createElement('div');
        tile.className = 'meet-tile';
        tile.innerHTML = `<video autoplay playsinline></video><div class="meet-avatar">${esc(name[0] || '?')}</div><span class="meet-name">${esc(name)}</span><span class="meet-state">Connecting…</span>`;
        $('[data-grid]').appendChild(tile);
        return tile;
    };

    /* ---------------- peer connections */
    const createPeer = (id, name, role) => {
        if (peers.has(id)) return peers.get(id);
        const pc = new RTCPeerConnection({ iceServers: ice });
        const peer = { pc, name, role, tile: addTile(id, name), pending: [], remoteSet: false };
        peers.set(id, peer);

        // Send our camera + mic (or receive only when we have none)
        const tracks = local?.getTracks() || [];
        tracks.forEach((t) => pc.addTrack(t, local));
        if (!local?.getAudioTracks().length) pc.addTransceiver('audio', { direction: 'recvonly' });
        if (!local?.getVideoTracks().length) pc.addTransceiver('video', { direction: 'recvonly' });
        if (screen) replaceVideo(screen.getVideoTracks()[0]);

        pc.onicecandidate = (e) => { if (e.candidate) send(id, 'candidate', e.candidate); };
        pc.ontrack = (e) => {
            const video = peer.tile.querySelector('video');
            if (video.srcObject !== e.streams[0]) video.srcObject = e.streams[0] || new MediaStream([e.track]);
            if (e.track.kind === 'video') {
                const show = () => peer.tile.querySelector('.meet-avatar').classList.toggle('d-none', !e.track.muted);
                e.track.onmute = show; e.track.onunmute = show; show();
            }
        };
        pc.onconnectionstatechange = () => {
            const st = peer.tile.querySelector('.meet-state');
            const s = pc.connectionState;
            st.textContent = s === 'connected' ? '' : s === 'failed' ? 'Connection failed' : 'Connecting…';
            st.classList.toggle('d-none', s === 'connected');
            if (s === 'failed') pc.restartIce?.();
        };
        updateCount();
        return peer;
    };
    const removePeer = (id) => {
        const p = peers.get(id);
        if (!p) return;
        p.pc.close();
        p.tile.remove();
        peers.delete(id);
        updateCount();
    };
    const call = async (id) => {
        const p = peers.get(id);
        const offer = await p.pc.createOffer();
        await p.pc.setLocalDescription(offer);
        send(id, 'offer', p.pc.localDescription);
    };
    const flushCandidates = async (p) => {
        while (p.pending.length) { try { await p.pc.addIceCandidate(p.pending.shift()); } catch (e) { /* stale */ } }
    };

    const handle = async (s) => {
        if (!me) return;
        let data;
        try { data = JSON.parse(s.payload); } catch (e) { return; }
        if (s.type === 'chat') { addChat(data.name, data.text, false); return; }

        const known = peers.get(s.from);
        if (s.type === 'offer') {
            const p = known || createPeer(s.from, data.name || '…', data.role || '');
            await p.pc.setRemoteDescription(data.sdp ? { type: 'offer', sdp: data.sdp } : data);
            p.remoteSet = true;
            await flushCandidates(p);
            const answer = await p.pc.createAnswer();
            await p.pc.setLocalDescription(answer);
            send(s.from, 'answer', p.pc.localDescription);
        } else if (s.type === 'answer' && known) {
            if (known.pc.signalingState === 'have-local-offer') {
                await known.pc.setRemoteDescription(data);
                known.remoteSet = true;
                await flushCandidates(known);
            }
        } else if (s.type === 'candidate' && known) {
            if (known.remoteSet) { try { await known.pc.addIceCandidate(data); } catch (e) { /* stale */ } }
            else known.pending.push(data);
        }
    };

    /* ---------------- relay polling */
    let busy = false;
    const poll = async () => {
        if (!me || busy) return;
        busy = true;
        try {
            const res = await fetch(`${cfg.poll}?${new URLSearchParams({ peer: me.id, secret: me.secret, after })}`, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
            if (res.status === 403) { leave('You were disconnected from the meeting'); return; }
            if (!res.ok) { $('[data-net]').textContent = 'Reconnecting…'; return; }
            $('[data-net]').textContent = '';
            const data = await res.json();
            after = data.after;
            // Names of people in the room (the offer itself does not carry them)
            const listed = new Map(data.peers.map((p) => [p.id, p]));
            for (const s of data.signals) {
                if (s.type === 'offer' && listed.has(s.from) && !peers.has(s.from)) createPeer(s.from, listed.get(s.from).name, listed.get(s.from).role);
                await handle(s);
            }
            peers.forEach((p, id) => { if (!listed.has(id)) { removePeer(id); toast('Left the meeting', p.name); } });
            listed.forEach((p, id) => {
                const known = peers.get(id);
                if (known && known.name === '…') { known.name = p.name; known.tile.querySelector('.meet-name').textContent = p.name; updateCount(); }
            });
            if (data.ended) leave('The meeting has ended');
        } catch (e) { $('[data-net]').textContent = 'Reconnecting…'; console.warn('meeting: poll', e); }
        finally { busy = false; }
    };

    /* ---------------- chat */
    let sideOpen = false;
    const addChat = (name, text, mine) => {
        const box = $('[data-chat]');
        const row = document.createElement('div');
        row.className = `meet-msg${mine ? ' mine' : ''}`;
        row.innerHTML = `<b>${esc(mine ? 'You' : name)}</b><span>${esc(text)}</span><small>${new Date().toLocaleTimeString('en-IN', { hour: '2-digit', minute: '2-digit' })}</small>`;
        box.appendChild(row);
        box.scrollTop = box.scrollHeight;
        if (!mine && !(sideOpen && !$('[data-pane="chat"]').classList.contains('d-none'))) $('[data-chat-dot]').classList.remove('d-none');
    };
    $('[data-chat-form]').addEventListener('submit', (e) => {
        e.preventDefault();
        const input = e.target.querySelector('input');
        const text = input.value.trim();
        if (!text || !me) return;
        send(null, 'chat', { name: me.name, text });
        addChat(me.name, text, true);
        input.value = '';
    });
    const openSide = (tab) => {
        sideOpen = true;
        $('[data-side]').classList.remove('d-none');
        $$('[data-side-tab]').forEach((b) => b.classList.toggle('active', b.dataset.sideTab === tab));
        $$('[data-pane]').forEach((p) => p.classList.toggle('d-none', p.dataset.pane !== tab));
        if (tab === 'chat') $('[data-chat-dot]').classList.add('d-none');
    };
    $$('[data-open-side]').forEach((b) => b.addEventListener('click', () => {
        const tab = b.dataset.openSide;
        if (sideOpen && !$(`[data-pane="${tab}"]`).classList.contains('d-none')) { $('[data-side]').classList.add('d-none'); sideOpen = false; }
        else openSide(tab);
    }));
    $$('[data-side-tab]').forEach((b) => b.addEventListener('click', () => openSide(b.dataset.sideTab)));
    $('[data-side-close]').addEventListener('click', () => { $('[data-side]').classList.add('d-none'); sideOpen = false; });

    /* ---------------- screen share */
    const replaceVideo = (track) => peers.forEach((p) => {
        const sender = p.pc.getSenders().find((s) => s.track?.kind === 'video') || p.pc.getTransceivers().find((t) => t.receiver.track?.kind === 'video')?.sender;
        sender?.replaceTrack(track).catch(() => {});
    });
    const stopScreen = () => {
        screen?.getTracks().forEach((t) => t.stop());
        screen = null;
        replaceVideo(local?.getVideoTracks()[0] || null);
        $('[data-local]').srcObject = local;
        $('[data-screen]')?.classList.remove('on');
        syncButtons();
    };
    $('[data-screen]')?.addEventListener('click', async () => {
        if (screen) { stopScreen(); return; }
        try {
            screen = await navigator.mediaDevices.getDisplayMedia({ video: true });
            const track = screen.getVideoTracks()[0];
            track.onended = stopScreen;
            replaceVideo(track);
            $('[data-local]').srcObject = screen;
            $('[data-screen]').classList.add('on');
            syncButtons();
        } catch (e) { /* cancelled */ }
    });

    /* ---------------- join / leave */
    const timer = () => {
        const s = Math.floor((Date.now() - started) / 1000);
        const pad = (n) => String(n).padStart(2, '0');
        $('[data-timer]').textContent = s >= 3600 ? `${Math.floor(s / 3600)}:${pad(Math.floor(s % 3600 / 60))}:${pad(s % 60)}` : `${pad(Math.floor(s / 60))}:${pad(s % 60)}`;
    };
    let clock = null;

    const join = async () => {
        const btn = $('[data-join]');
        const data = {};
        if (cfg.guest) {
            const name = $('[data-guest-name]')?.value.trim();
            if (!name) { $('[data-guest-name]').focus(); return; }
            data.name = name;
        }
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Joining…';
        try {
            const res = await post(cfg.join, data);
            const json = await res.json().catch(() => ({}));
            if (!res.ok) throw new Error(json.message || 'Could not join the meeting.');
            me = json.peer;
            after = json.after;
            ice = json.ice;
            started = Date.now();
            $('[data-lobby]').classList.add('d-none');
            $('[data-left]').classList.add('d-none');
            $('[data-call]').classList.remove('d-none');
            document.body.classList.add('in-meeting');
            // Start listening first, then offer to everyone already in the room (in parallel)
            polling = setInterval(poll, 1000);
            clock = setInterval(timer, 1000);
            timer();
            json.peers.forEach((p) => {
                createPeer(p.id, p.name, p.role);
                call(p.id).catch((e) => console.warn('meeting: offer failed', e));
            });
            updateCount();
        } catch (e) {
            $('[data-lobby-msg]').textContent = e.message;
        } finally {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-camera-video me-1"></i>Join Meeting';
        }
    };
    const leave = (title) => {
        if (!me) return;
        clearInterval(polling); clearInterval(clock);
        outbox = [];
        post(cfg.leave, { peer: me.id, secret: me.secret }, true).catch(() => {});
        [...peers.keys()].forEach(removePeer);
        if (screen) stopScreen();
        me = null;
        $('[data-call]').classList.add('d-none');
        $('[data-left]').classList.remove('d-none');
        $('[data-left-title]').textContent = typeof title === 'string' ? title : 'You left the meeting';
        $('[data-rejoin]').classList.toggle('d-none', title === 'The meeting has ended');
        document.body.classList.remove('in-meeting');
    };

    $('[data-join]').addEventListener('click', join);
    $('[data-leave]').addEventListener('click', () => leave());
    $('[data-rejoin]').addEventListener('click', () => { $('[data-left]').classList.add('d-none'); join(); });
    $('[data-end-form]')?.addEventListener('submit', () => leave('The meeting has ended'));
    $$('[data-toggle-mic]').forEach((b) => b.addEventListener('click', () => setMic(!micOn)));
    $$('[data-toggle-cam]').forEach((b) => b.addEventListener('click', () => setCam(!camOn)));
    window.addEventListener('pagehide', () => { if (me) navigator.sendBeacon(cfg.leave, new URLSearchParams({ _token: csrf(), peer: me.id, secret: me.secret })); });

    if (!window.RTCPeerConnection || !navigator.mediaDevices) {
        $('[data-lobby-msg]').textContent = 'This browser cannot join video meetings. Please use Chrome, Edge, Firefox or Safari.';
        $('[data-join]').disabled = true;
        return;
    }
    getMedia();
})();
