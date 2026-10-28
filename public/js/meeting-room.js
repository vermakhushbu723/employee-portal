/*
 * Built-in meeting room (WebRTC mesh, up to 8 people).
 * Video / audio go directly between browsers. The portal only relays the small connection
 * messages, polled about once a second:
 *   - whoever joined later sends an "offer" to everyone already in the room
 *   - the others answer; both sides exchange network "candidates"
 * Every connection always has one audio and one video "slot" in both directions. Camera / mic
 * tracks are put into those slots (replaceTrack), so a mic or camera that becomes ready later —
 * permission given after joining, camera switched on, screen share — reaches everyone without
 * reconnecting. A connection that fails is rebuilt by the side that called.
 * Remote sound plays from its own <audio> element; when the browser blocks sound until a tap,
 * a "Tap to turn on sound" button appears.
 */
(() => {
    const root = document.getElementById('meetRoom');
    if (!root) return;

    const cfg = JSON.parse(root.dataset.room);
    const $ = (sel) => root.querySelector(sel);
    const $$ = (sel) => [...root.querySelectorAll(sel)];
    const csrf = () => document.querySelector('meta[name=csrf-token]')?.content;
    const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

    let local = new MediaStream();   // camera / mic (tracks are added when the browser gives them)
    let screen = null;               // MediaStream from screen share
    let me = null;                   // { id, secret, name, since }
    let after = 0;
    let ice = [];
    let polling = null;
    let started = 0;
    let micOn = true;
    let camOn = true;
    let mediaReady = null;           // promise: first camera / mic request finished
    const peers = new Map();         // id -> { pc, gen, offerer, name, role, tile, audio, remote, pending, remoteSet, since, lostAt }

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
        finally { flushing = false; if (outbox.length && outbox.some((m) => m.type !== 'candidate')) flush(); }
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
        const noVideo = !camOn || !local.getVideoTracks().length;
        $('[data-local-off]')?.classList.toggle('d-none', !noVideo || !!screen);
        $('[data-preview-off]')?.classList.toggle('d-none', !noVideo);
    };

    /* ---------------- camera / mic */
    const trackOf = (kind) => (kind === 'video' && screen ? screen.getVideoTracks()[0] : local.getTracks().find((t) => t.kind === kind && t.readyState === 'live')) || null;
    // Put the current camera / mic / screen into every connection
    const refreshSenders = () => peers.forEach((p) => p.pc.getTransceivers().forEach((t) => {
        const kind = t.receiver.track?.kind;
        if (!kind || t.currentDirection === 'stopped' || t.stopped) return;
        const track = trackOf(kind);
        if (t.sender.track !== track) t.sender.replaceTrack(track).catch(() => {});
        try { t.sender.setStreams?.(local); } catch (e) { /* older browser */ }
    }));
    const showLocal = () => {
        $('[data-preview]').srcObject = local;
        $('[data-local]').srcObject = screen || local;
    };
    const addTracks = (stream) => {
        stream.getTracks().forEach((t) => {
            local.getTracks().filter((o) => o.kind === t.kind).forEach((o) => { o.stop(); local.removeTrack(o); });
            local.addTrack(t);
        });
        showLocal();
        refreshSenders();
    };
    const getMedia = async () => {
        $('[data-lobby-msg]').textContent = 'Allow camera and microphone when the browser asks.';
        try {
            addTracks(await navigator.mediaDevices.getUserMedia({ audio: { echoCancellation: true, noiseSuppression: true, autoGainControl: true }, video: { width: { ideal: 1280 }, height: { ideal: 720 }, facingMode: 'user' } }));
        } catch (e) {
            try { addTracks(await navigator.mediaDevices.getUserMedia({ audio: { echoCancellation: true, noiseSuppression: true } })); camOn = false; } // no camera
            catch (e2) {
                micOn = false; camOn = false;
                $('[data-lobby-msg]').textContent = 'Camera / microphone blocked — you can still join and listen. Allow them in the browser settings (lock icon in the address bar), then tap the mic button.';
            }
        }
        local.getAudioTracks().forEach((t) => { t.enabled = micOn; });
        local.getVideoTracks().forEach((t) => { t.enabled = camOn; });
        syncButtons();
    };
    // Mic / camera button: switches the track on or off; asks the browser again when there is none yet
    const setMic = async (on) => {
        if (on && !local.getAudioTracks().length) {
            try { addTracks(await navigator.mediaDevices.getUserMedia({ audio: { echoCancellation: true, noiseSuppression: true } })); }
            catch (e) { toast('Microphone blocked', 'Allow the microphone for this site in the browser settings.'); }
        }
        micOn = on && !!local.getAudioTracks().length;
        local.getAudioTracks().forEach((t) => { t.enabled = micOn; });
        syncButtons();
    };
    const setCam = async (on) => {
        if (on && !local.getVideoTracks().length) {
            try { addTracks(await navigator.mediaDevices.getUserMedia({ video: { width: { ideal: 1280 }, height: { ideal: 720 }, facingMode: 'user' } })); }
            catch (e) { toast('Camera blocked', 'Allow the camera for this site in the browser settings.'); }
        }
        camOn = on && !!local.getVideoTracks().length;
        local.getVideoTracks().forEach((t) => { t.enabled = camOn; });
        syncButtons();
    };

    /* ---------------- sound (browsers may block it until the page is tapped) */
    const soundBtn = document.createElement('button');
    soundBtn.type = 'button';
    soundBtn.className = 'meet-sound d-none';
    soundBtn.innerHTML = '<i class="bi bi-volume-up-fill"></i> Tap to turn on sound';
    root.appendChild(soundBtn);
    const playAudio = (p) => {
        const a = p.audio;
        if (!a.srcObject) a.srcObject = p.remote;
        const r = a.play();
        if (r?.catch) r.then(() => { if (![...peers.values()].some((x) => x.audio.paused && x.remote.getAudioTracks().length)) soundBtn.classList.add('d-none'); })
            .catch(() => soundBtn.classList.remove('d-none'));
    };
    soundBtn.addEventListener('click', () => { soundBtn.classList.add('d-none'); peers.forEach(playAudio); });

    /* ---------------- tiles */
    const updateCount = () => {
        const n = peers.size + 1;
        $$('[data-count], [data-count2]').forEach((el) => { el.textContent = n; });
        $('[data-grid]').dataset.n = Math.min(n, 9);
        const people = [`<li class="meet-person"><span class="meet-mini">${esc((me?.name || 'You')[0])}</span>${esc(me?.name || 'You')} <span class="opacity-75">(you)</span></li>`]
            .concat([...peers.values()].map((p) => `<li class="meet-person"><span class="meet-mini">${esc(p.name[0])}</span>${esc(p.name)} <span class="opacity-75">${p.role === 'guest' ? '(customer)' : ''}</span></li>`));
        $('[data-people]').innerHTML = people.join('');
    };
    const addTile = (name) => {
        const tile = document.createElement('div');
        tile.className = 'meet-tile';
        tile.innerHTML = `<video autoplay playsinline muted></video><audio autoplay></audio><div class="meet-avatar">${esc(name[0] || '?')}</div><span class="meet-name">${esc(name)}</span><span class="meet-state">Connecting…</span><button type="button" class="meet-fs" data-tile-fs title="Full screen"><i class="bi bi-arrows-fullscreen"></i></button>`;
        $('[data-grid]').appendChild(tile);
        return tile;
    };
    const setName = (p, name) => {
        if (!name || p.name === name) return;
        p.name = name;
        p.tile.querySelector('.meet-name').textContent = name;
        p.tile.querySelector('.meet-avatar').textContent = name[0] || '?';
        updateCount();
    };

    /* ---------------- peer connections */
    const setupPc = (p, id) => {
        try { p.pc?.close(); } catch (e) { /* closed */ }
        const pc = new RTCPeerConnection({ iceServers: ice });
        p.pc = pc;
        p.pending = [];
        p.remoteSet = false;
        p.connectedOnce = false;
        p.lostAt = Date.now();
        p.remote.getTracks().forEach((t) => p.remote.removeTrack(t));

        if (p.offerer) {
            // The caller creates the two slots (audio + video), each sending and receiving
            ['audio', 'video'].forEach((kind) => pc.addTransceiver(trackOf(kind) || kind, { direction: 'sendrecv', streams: [local] }));
        }
        pc.onicecandidate = (e) => { if (e.candidate) send(id, 'candidate', { c: e.candidate, gen: p.gen }); };
        pc.ontrack = (e) => {
            if (pc !== p.pc) return;
            const track = e.track;
            p.remote.getTracks().filter((t) => t.kind === track.kind && t !== track).forEach((t) => p.remote.removeTrack(t));
            if (!p.remote.getTracks().includes(track)) p.remote.addTrack(track);
            const video = p.tile.querySelector('video');
            if (video.srcObject !== p.remote) video.srcObject = p.remote;
            video.play?.().catch(() => {});
            if (track.kind === 'video') {
                const show = () => p.tile.querySelector('.meet-avatar').classList.toggle('d-none', !track.muted);
                track.onmute = show; track.onunmute = show; show();
            } else {
                playAudio(p);
            }
        };
        pc.onconnectionstatechange = () => {
            if (pc !== p.pc) return;
            const st = p.tile.querySelector('.meet-state');
            const s = pc.connectionState;
            st.textContent = s === 'connected' ? '' : s === 'failed' ? 'Reconnecting…' : s === 'disconnected' ? 'Weak network…' : 'Connecting…';
            st.classList.toggle('d-none', s === 'connected');
            if (s === 'connected') { p.connectedOnce = true; p.lostAt = null; playAudio(p); }
            else if (!p.lostAt) p.lostAt = Date.now();
        };
    };
    const createPeer = (id, name, role, offerer, since = 0) => {
        if (peers.has(id)) return peers.get(id);
        const p = { pc: null, gen: offerer ? Date.now() : 0, offerer, name: name || '…', role, since, remote: new MediaStream(), tile: addTile(name || '…') };
        p.audio = p.tile.querySelector('audio');
        peers.set(id, p);
        setupPc(p, id);
        updateCount();
        return p;
    };
    const removePeer = (id) => {
        const p = peers.get(id);
        if (!p) return;
        try { p.pc.close(); } catch (e) { /* closed */ }
        p.tile.remove();
        peers.delete(id);
        updateCount();
    };
    const call = async (id) => {
        const p = peers.get(id);
        if (!p) return;
        const pc = p.pc;
        const offer = await pc.createOffer();
        if (pc !== p.pc) return;
        await pc.setLocalDescription(offer);
        send(id, 'offer', { type: 'offer', sdp: pc.localDescription.sdp, gen: p.gen, name: me?.name });
    };
    // The caller rebuilds a connection that failed or never connected
    const restart = (id) => {
        const p = peers.get(id);
        if (!p || !p.offerer) return;
        p.gen = Date.now();
        setupPc(p, id);
        call(id).catch((e) => console.warn('meeting: re-offer failed', e));
    };
    setInterval(() => {
        if (!me) return;
        peers.forEach((p, id) => {
            const s = p.pc.connectionState;
            const waited = p.lostAt ? Date.now() - p.lostAt : 0;
            if (p.offerer && ((s === 'failed' && waited > 3000) || (s !== 'connected' && waited > (p.connectedOnce ? 12000 : 20000)))) restart(id);
        });
    }, 3000);
    const flushCandidates = async (p) => {
        while (p.pending.length) { try { await p.pc.addIceCandidate(p.pending.shift()); } catch (e) { /* stale */ } }
    };

    const handle = async (s, listed) => {
        if (!me) return;
        let data;
        try { data = JSON.parse(s.payload); } catch (e) { return; }
        if (s.type === 'chat') { addChat(data.name, data.text, false); return; }

        let p = peers.get(s.from);
        if (s.type === 'offer') {
            const info = listed.get(s.from) || {};
            if (!p) p = createPeer(s.from, info.name || data.name, info.role || '', false, info.since || 0);
            setName(p, info.name || data.name);
            if (p.offerer) {
                // Both sides called at once: the one who joined earlier answers, the later one keeps calling
                if ((me.since || 0) > (p.since || 0)) return;
                p.offerer = false;
            }
            if (p.gen !== data.gen) { p.gen = data.gen; setupPc(p, s.from); }   // new / rebuilt connection from the caller
            const pc = p.pc;
            await pc.setRemoteDescription({ type: 'offer', sdp: data.sdp });
            p.remoteSet = true;
            // Answer with our camera + mic in the caller's two slots
            for (const t of pc.getTransceivers()) {
                const kind = t.receiver.track?.kind;
                if (!kind) continue;
                t.direction = 'sendrecv';
                try { await t.sender.replaceTrack(trackOf(kind)); } catch (e) { /* none */ }
                try { t.sender.setStreams?.(local); } catch (e) { /* older browser */ }
            }
            await flushCandidates(p);
            const answer = await pc.createAnswer();
            await pc.setLocalDescription(answer);
            send(s.from, 'answer', { type: 'answer', sdp: pc.localDescription.sdp, gen: p.gen });
        } else if (s.type === 'answer' && p) {
            if (data.gen === p.gen && p.pc.signalingState === 'have-local-offer') {
                await p.pc.setRemoteDescription({ type: 'answer', sdp: data.sdp });
                p.remoteSet = true;
                await flushCandidates(p);
            }
        } else if (s.type === 'candidate' && p) {
            if (data.gen !== p.gen) return;   // from an old connection
            if (p.remoteSet) { try { await p.pc.addIceCandidate(data.c); } catch (e) { /* stale */ } }
            else p.pending.push(data.c);
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
            const listed = new Map(data.peers.map((p) => [p.id, p]));
            for (const s of data.signals) {
                try { await handle(s, listed); } catch (e) { console.warn('meeting: signal', s.type, e); }
            }
            peers.forEach((p, id) => { if (!listed.has(id)) { removePeer(id); toast('Left the meeting', p.name); } });
            listed.forEach((info, id) => {
                const p = peers.get(id);
                if (p) { setName(p, info.name); p.since = info.since; return; }
                // Someone who joined before us that we are not connected to yet (e.g. their offer was lost): we call
                if ((info.since || 0) < (me.since || 0) || ((info.since || 0) === (me.since || 0) && id < me.id)) {
                    createPeer(id, info.name, info.role, true, info.since);
                    call(id).catch((e) => console.warn('meeting: offer failed', e));
                }
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
    const stopScreen = () => {
        screen?.getTracks().forEach((t) => t.stop());
        screen = null;
        refreshSenders();
        showLocal();
        $('[data-screen]')?.classList.remove('on');
        syncButtons();
    };
    $('[data-screen]')?.addEventListener('click', async () => {
        if (screen) { stopScreen(); return; }
        try {
            screen = await navigator.mediaDevices.getDisplayMedia({ video: true });
            screen.getVideoTracks()[0].onended = stopScreen;
            refreshSenders();
            showLocal();
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
            // Wait for camera / mic first (up to 15 s for the permission popup) so others hear us from the start
            await Promise.race([mediaReady, new Promise((r) => setTimeout(r, 15000))]);
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
            // Start listening first, then call everyone already in the room (in parallel)
            polling = setInterval(poll, 1000);
            clock = setInterval(timer, 1000);
            timer();
            json.peers.forEach((p) => {
                createPeer(p.id, p.name, p.role, true, p.since);
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
        soundBtn.classList.add('d-none');
        me = null;
        $('[data-call]').classList.add('d-none');
        $('[data-left]').classList.remove('d-none');
        $('[data-left-title]').textContent = typeof title === 'string' ? title : 'You left the meeting';
        $('[data-rejoin]').classList.toggle('d-none', title === 'The meeting has ended');
        document.body.classList.remove('in-meeting');
    };

    /* ---------------- full screen: the whole meeting, or one video (button or double-tap) */
    const fsElement = () => document.fullscreenElement || document.webkitFullscreenElement;
    const toggleFs = (el) => {
        if (fsElement()) { (document.exitFullscreen || document.webkitExitFullscreen).call(document); return; }
        const req = el.requestFullscreen || el.webkitRequestFullscreen;
        if (req) req.call(el).catch?.(() => {});
        else { const v = el.querySelector('video'); v?.webkitEnterFullscreen?.(); } // iPhone: video only
    };
    $('[data-fullscreen]').addEventListener('click', () => toggleFs(root));
    root.addEventListener('click', (e) => { const b = e.target.closest('[data-tile-fs]'); if (b) toggleFs(b.closest('.meet-tile')); });
    root.addEventListener('dblclick', (e) => { const t = e.target.closest('.meet-tile'); if (t) toggleFs(t); });
    const fsSync = () => {
        const on = !!fsElement();
        const b = $('[data-fullscreen]');
        b.querySelector('i').className = `bi bi-${on ? 'fullscreen-exit' : 'arrows-fullscreen'}`;
        b.querySelector('span').textContent = on ? 'Exit Full' : 'Full Screen';
    };
    document.addEventListener('fullscreenchange', fsSync);
    document.addEventListener('webkitfullscreenchange', fsSync);

    $('[data-join]').addEventListener('click', join);
    $('[data-leave]').addEventListener('click', () => leave());
    $('[data-rejoin]').addEventListener('click', () => { $('[data-left]').classList.add('d-none'); join(); });
    // Review inside the room: "Close Meeting" ends it for everyone; keep open / reschedule just leaves
    document.querySelector('[data-review-form]')?.addEventListener('submit', (e) => {
        leave(e.target.querySelector('input[name=action]:checked')?.value === 'close' ? 'The meeting has ended' : undefined);
    });
    $$('[data-toggle-mic]').forEach((b) => b.addEventListener('click', () => setMic(!micOn)));
    $$('[data-toggle-cam]').forEach((b) => b.addEventListener('click', () => setCam(!camOn)));
    window.addEventListener('pagehide', () => { if (me) navigator.sendBeacon(cfg.leave, new URLSearchParams({ _token: csrf(), peer: me.id, secret: me.secret })); });

    if (!window.RTCPeerConnection || !navigator.mediaDevices) {
        $('[data-lobby-msg]').textContent = 'This browser cannot join video meetings. Please use Chrome, Edge, Firefox or Safari.';
        $('[data-join]').disabled = true;
        return;
    }
    mediaReady = getMedia();
})();
