/*
 * Portal WhatsApp gateway.
 * Each portal user links their own WhatsApp (Settings → Linked devices → scan QR). This service keeps
 * those sessions connected and gives the portal a tiny JSON API (over a local socket file on the server,
 * or a localhost port in development):
 *
 *   GET  /health
 *   POST /s/:sid/start            start / resume the session
 *   GET  /s/:sid/status           { state: starting | qr | connected | logged_out, qr: dataURL, me }
 *   GET  /s/:sid/chats?q=         latest chats
 *   GET  /s/:sid/messages?jid=    messages of one chat (marks them read)
 *   POST /s/:sid/send             { jid | number, text }
 *   POST /s/:sid/pair             { number } → { code } link with phone number (8-letter code typed in WhatsApp)
 *   POST /s/:sid/logout           unlink the device and forget the session
 *
 * Every request needs header X-Gateway-Key. Login data stays in data/<sid>/auth, chat list in data/<sid>/store.json.
 */
import http from 'node:http';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import pino from 'pino';
import QRCode from 'qrcode';
import makeWASocket, { useMultiFileAuthState, DisconnectReason, fetchLatestBaileysVersion, Browsers, jidNormalizedUser } from '@whiskeysockets/baileys';

const ROOT = path.dirname(fileURLToPath(import.meta.url));
const DATA = process.env.WA_DATA || path.join(ROOT, 'data');
const SOCKET = process.env.WA_SOCKET || '';
const PORT = Number(process.env.WA_PORT || 0);
const KEY = process.env.WA_KEY || (fs.existsSync(path.join(ROOT, '.key')) ? fs.readFileSync(path.join(ROOT, '.key'), 'utf8').trim() : '');
const MAX_SESSIONS = Number(process.env.WA_MAX_SESSIONS || 60);
const IDLE_UNLOAD_MS = Number(process.env.WA_IDLE_HOURS || 12) * 3600 * 1000; // sessions nobody opened for this long are unloaded (they resume on next open)
const MAX_CHATS = 150;
const MAX_MESSAGES = 80;
const logger = pino({ level: 'silent' });
const log = (...a) => console.log(new Date().toISOString(), ...a);

fs.mkdirSync(DATA, { recursive: true });
let waVersion;

/* ------------------------------------------------------------------ store */
const textOf = (msg) => {
    const m = msg?.message;
    if (!m) return '';
    const inner = m.ephemeralMessage?.message || m.viewOnceMessage?.message || m.viewOnceMessageV2?.message || m.documentWithCaptionMessage?.message || m;
    if (inner.conversation) return inner.conversation;
    if (inner.extendedTextMessage?.text) return inner.extendedTextMessage.text;
    if (inner.imageMessage) return `📷 Photo${inner.imageMessage.caption ? ': ' + inner.imageMessage.caption : ''}`;
    if (inner.videoMessage) return `🎥 Video${inner.videoMessage.caption ? ': ' + inner.videoMessage.caption : ''}`;
    if (inner.audioMessage) return inner.audioMessage.ptt ? '🎤 Voice message' : '🎵 Audio';
    if (inner.documentMessage) return `📄 ${inner.documentMessage.fileName || 'Document'}`;
    if (inner.stickerMessage) return '💟 Sticker';
    if (inner.locationMessage) return '📍 Location';
    if (inner.contactMessage) return `👤 ${inner.contactMessage.displayName || 'Contact'}`;
    if (inner.reactionMessage) return null; // reactions are not shown as messages
    if (inner.protocolMessage) return null;
    return '💬 Message';
};
const tsOf = (msg) => Number(msg?.messageTimestamp?.low ?? msg?.messageTimestamp ?? 0) || Math.floor(Date.now() / 1000);
const isChatJid = (jid) => jid && (jid.endsWith('@s.whatsapp.net') || jid.endsWith('@g.us') || jid.endsWith('@lid')) && jid !== 'status@broadcast';

class Store {
    constructor(file) {
        this.file = file;
        this.chats = {};     // jid -> { id, name, unread, ts, last }
        this.messages = {};  // jid -> [ { id, fromMe, text, ts, sender } ]
        this.names = {};     // jid -> name
        this.dirty = false;
        try { Object.assign(this, JSON.parse(fs.readFileSync(file, 'utf8'))); } catch (e) { /* new */ }
    }
    name(jid) { return this.names[jid] || this.chats[jid]?.name || '+' + jid.split('@')[0]; }
    setName(jid, name) { if (jid && name) { this.names[jid] = name; if (this.chats[jid] && !this.chats[jid].nameFixed) this.chats[jid].name = name; this.dirty = true; } }
    upsertChat(jid, patch = {}) {
        if (!isChatJid(jid)) return;
        const c = this.chats[jid] || (this.chats[jid] = { id: jid, name: this.names[jid] || null, unread: 0, ts: 0, last: '' });
        Object.assign(c, Object.fromEntries(Object.entries(patch).filter(([, v]) => v !== undefined && v !== null)));
        this.dirty = true;
    }
    addMessage(msg, notify) {
        const jid = msg?.key?.remoteJid;
        if (!isChatJid(jid)) return;
        const text = textOf(msg);
        if (text === null) return;
        const list = this.messages[jid] || (this.messages[jid] = []);
        if (list.some((m) => m.id === msg.key.id)) return;
        const item = { id: msg.key.id, fromMe: !!msg.key.fromMe, text, ts: tsOf(msg), sender: jid.endsWith('@g.us') ? (msg.pushName || '') : '' };
        list.push(item);
        list.sort((a, b) => a.ts - b.ts);
        if (list.length > MAX_MESSAGES) list.splice(0, list.length - MAX_MESSAGES);
        const chat = this.chats[jid];
        if (!chat || item.ts >= chat.ts) this.upsertChat(jid, { ts: item.ts, last: (item.fromMe ? 'You: ' : '') + text });
        if (notify && !item.fromMe) this.chats[jid].unread = (this.chats[jid].unread || 0) + 1;
        if (!item.fromMe && msg.pushName && !jid.endsWith('@g.us')) this.setName(jid, msg.pushName);
        this.dirty = true;
    }
    trim() {
        const keep = Object.values(this.chats).sort((a, b) => b.ts - a.ts).slice(0, MAX_CHATS).map((c) => c.id);
        for (const jid of Object.keys(this.chats)) if (!keep.includes(jid)) { delete this.chats[jid]; delete this.messages[jid]; }
    }
    save() {
        if (!this.dirty) return;
        this.trim();
        fs.writeFileSync(this.file + '.tmp', JSON.stringify({ chats: this.chats, messages: this.messages, names: this.names }));
        fs.renameSync(this.file + '.tmp', this.file);
        this.dirty = false;
    }
}

/* ------------------------------------------------------------------ sessions */
const sessions = new Map(); // sid -> session

async function startSession(sid) {
    let s = sessions.get(sid);
    if (s && s.state !== 'logged_out') { s.seen = Date.now(); return s; }
    if (sessions.size >= MAX_SESSIONS) unloadIdle(true);
    const dir = path.join(DATA, sid);
    fs.mkdirSync(dir, { recursive: true });
    s = { sid, dir, state: 'starting', qr: null, pair: null, me: null, sock: null, store: new Store(path.join(dir, 'store.json')), seen: Date.now(), retries: 0, stopped: false };
    sessions.set(sid, s);
    await connect(s);
    return s;
}

async function connect(s) {
    const { state, saveCreds } = await useMultiFileAuthState(path.join(s.dir, 'auth'));
    if (!waVersion) {
        try { waVersion = (await fetchLatestBaileysVersion()).version; } catch (e) { waVersion = undefined; }
    }
    const sock = makeWASocket({
        version: waVersion,
        auth: state,
        logger,
        browser: Browsers.windows('Employee Portal'),
        printQRInTerminal: false,
        syncFullHistory: false,
        markOnlineOnConnect: false,
        generateHighQualityLinkPreview: false,
    });
    s.sock = sock;
    const st = s.store;

    sock.ev.on('creds.update', saveCreds);
    sock.ev.on('connection.update', async ({ connection, lastDisconnect, qr }) => {
        // Error correction "H" keeps the QR scannable with the WhatsApp logo on top of it
        if (qr) { s.state = 'qr'; s.qr = await QRCode.toDataURL(qr, { margin: 1, width: 300, errorCorrectionLevel: 'H' }); }
        if (connection === 'open') {
            s.state = 'connected'; s.qr = null; s.pair = null; s.retries = 0;
            s.me = { id: jidNormalizedUser(sock.user?.id || ''), name: sock.user?.name || sock.user?.verifiedName || '' };
            log(s.sid, 'connected', s.me.id);
        }
        if (connection === 'close') {
            const code = lastDisconnect?.error?.output?.statusCode;
            if (code === DisconnectReason.loggedOut) {
                log(s.sid, 'logged out from phone');
                s.state = 'logged_out'; s.qr = null; s.me = null;
                fs.rmSync(path.join(s.dir, 'auth'), { recursive: true, force: true });
                return;
            }
            if (s.stopped) return;
            s.state = s.me ? 'reconnecting' : 'starting';
            const wait = Math.min(30000, 1000 * 2 ** Math.min(s.retries++, 5));
            setTimeout(() => { if (!s.stopped && sessions.get(s.sid) === s) connect(s).catch((e) => log(s.sid, 'reconnect error', e.message)); }, wait);
        }
    });

    sock.ev.on('messaging-history.set', ({ chats = [], contacts = [], messages = [] }) => {
        contacts.forEach((c) => st.setName(c.id, c.name || c.notify || c.verifiedName));
        chats.forEach((c) => st.upsertChat(c.id, { name: c.name || undefined, unread: c.unreadCount || 0, ts: Number(c.conversationTimestamp?.low ?? c.conversationTimestamp ?? 0) || undefined }));
        messages.forEach((m) => st.addMessage(m, false));
    });
    sock.ev.on('chats.upsert', (chats) => chats.forEach((c) => st.upsertChat(c.id, { name: c.name || undefined, unread: c.unreadCount ?? undefined })));
    sock.ev.on('chats.update', (chats) => chats.forEach((c) => {
        if (!st.chats[c.id]) return;
        st.upsertChat(c.id, { name: c.name || undefined, unread: c.unreadCount ?? undefined });
    }));
    sock.ev.on('contacts.upsert', (cs) => cs.forEach((c) => st.setName(c.id, c.name || c.notify || c.verifiedName)));
    sock.ev.on('contacts.update', (cs) => cs.forEach((c) => st.setName(c.id, c.name || c.notify)));
    sock.ev.on('groups.upsert', (gs) => gs.forEach((g) => { st.setName(g.id, g.subject); st.upsertChat(g.id, { name: g.subject }); }));
    sock.ev.on('messages.upsert', ({ messages, type }) => messages.forEach((m) => st.addMessage(m, type === 'notify')));
}

async function stopSession(s, logout) {
    s.stopped = true;
    try { if (logout) await s.sock?.logout(); else s.sock?.end(undefined); } catch (e) { /* already closed */ }
    s.store.save();
    sessions.delete(s.sid);
    if (logout) fs.rmSync(s.dir, { recursive: true, force: true });
}

function unloadIdle(force = false) {
    const now = Date.now();
    const list = [...sessions.values()].sort((a, b) => a.seen - b.seen);
    for (const s of list) {
        if (now - s.seen > IDLE_UNLOAD_MS || (force && sessions.size >= MAX_SESSIONS)) {
            log(s.sid, 'unloaded (idle)');
            stopSession(s, false);
        }
    }
}

/* ------------------------------------------------------------------ http api */
const json = (res, code, data) => { res.writeHead(code, { 'Content-Type': 'application/json' }); res.end(JSON.stringify(data)); };
const readBody = (req) => new Promise((resolve) => {
    let b = '';
    req.on('data', (c) => { b += c; if (b.length > 100000) req.destroy(); });
    req.on('end', () => { try { resolve(b ? JSON.parse(b) : {}); } catch (e) { resolve({}); } });
});
const toJid = (body) => {
    if (body.jid && isChatJid(body.jid)) return body.jid;
    let digits = String(body.number || '').replace(/\D/g, '');
    if (digits.length === 10) digits = '91' + digits; // Indian number without country code
    return digits.length >= 10 ? `${digits}@s.whatsapp.net` : null;
};

const server = http.createServer(async (req, res) => {
    try {
        if (KEY && req.headers['x-gateway-key'] !== KEY) return json(res, 401, { error: 'unauthorized' });
        const url = new URL(req.url, 'http://local');
        if (url.pathname === '/health') return json(res, 200, { ok: true, sessions: sessions.size, uptime: Math.round(process.uptime()) });

        const m = url.pathname.match(/^\/s\/([a-z0-9_-]{1,40})\/(start|status|chats|messages|send|logout|pair)$/i);
        if (!m) return json(res, 404, { error: 'not found' });
        const [, sid, action] = m;

        if (action === 'start' || action === 'status') {
            const s = action === 'start' ? await startSession(sid) : sessions.get(sid);
            if (!s) return json(res, 200, { state: fs.existsSync(path.join(DATA, sid, 'auth', 'creds.json')) ? 'saved' : 'none', qr: null, me: null });
            s.seen = Date.now();
            return json(res, 200, { state: s.state, qr: s.qr, pair: s.pair, me: s.me });
        }
        if (action === 'pair' && req.method === 'POST') {
            // Link with phone number: WhatsApp shows "Enter code" on the phone; no QR scan needed
            const s = await startSession(sid);
            s.seen = Date.now();
            if (s.state === 'connected') return json(res, 409, { error: 'WhatsApp is already linked' });
            let digits = String((await readBody(req)).number || '').replace(/\D/g, '');
            if (digits.length === 10) digits = '91' + digits;
            if (digits.length < 11 || digits.length > 15) return json(res, 422, { error: 'Enter your WhatsApp number with country code' });
            // The socket must be waiting for a login (QR shown) before a code can be asked for
            for (let i = 0; i < 40 && s.state !== 'qr'; i++) await new Promise((r) => setTimeout(r, 250));
            if (s.state !== 'qr' || !s.sock) return json(res, 503, { error: 'WhatsApp is starting — try again in a moment' });
            const raw = await s.sock.requestPairingCode(digits);
            s.pair = { code: String(raw).replace(/(.{4})(?=.)/, '$1-'), number: digits, at: Date.now() };
            log(sid, 'pairing code requested for', digits.slice(0, 4) + '…');
            return json(res, 200, { code: s.pair.code });
        }

        const s = sessions.get(sid);
        if (!s) return json(res, 409, { error: 'session not started' });
        s.seen = Date.now();

        if (action === 'chats') {
            const q = (url.searchParams.get('q') || '').toLowerCase();
            const chats = Object.values(s.store.chats)
                .map((c) => ({ ...c, name: c.name || s.store.name(c.id), group: c.id.endsWith('@g.us') }))
                .filter((c) => !q || c.name.toLowerCase().includes(q) || c.id.includes(q))
                .sort((a, b) => b.ts - a.ts).slice(0, 100);
            return json(res, 200, { chats, state: s.state });
        }
        if (action === 'messages') {
            const jid = url.searchParams.get('jid');
            if (!isChatJid(jid)) return json(res, 422, { error: 'bad chat' });
            const list = s.store.messages[jid] || [];
            const chat = s.store.chats[jid];
            if (chat?.unread) {
                chat.unread = 0; s.store.dirty = true;
                const unreadKeys = list.filter((x) => !x.fromMe).slice(-10).map((x) => ({ remoteJid: jid, id: x.id, fromMe: false }));
                s.sock?.readMessages(unreadKeys).catch(() => {});
            }
            return json(res, 200, { chat: { id: jid, name: chat?.name || s.store.name(jid) }, messages: list });
        }
        if (action === 'send' && req.method === 'POST') {
            if (s.state !== 'connected') return json(res, 409, { error: 'WhatsApp is not connected' });
            const body = await readBody(req);
            const jid = toJid(body);
            const text = String(body.text || '').trim().slice(0, 4000);
            if (!jid || !text) return json(res, 422, { error: 'number and text are required' });
            const sent = await s.sock.sendMessage(jid, { text });
            s.store.addMessage(sent, false);
            return json(res, 200, { ok: true, jid });
        }
        if (action === 'logout' && req.method === 'POST') {
            await stopSession(s, true);
            return json(res, 200, { ok: true });
        }
        return json(res, 405, { error: 'method not allowed' });
    } catch (e) {
        log('api error', e.message);
        return json(res, 500, { error: e.message });
    }
});

/* ------------------------------------------------------------------ boot */
// Only one gateway may run: two would fight over the same WhatsApp logins
const otherRunning = () => new Promise((resolve) => {
    if (!SOCKET || !fs.existsSync(SOCKET)) return resolve(false);
    const req = http.get({ socketPath: SOCKET, path: '/health', headers: { 'x-gateway-key': KEY }, timeout: 2000 }, (res) => { res.resume(); resolve(res.statusCode === 200); });
    req.on('error', () => resolve(false));
    req.on('timeout', () => { req.destroy(); resolve(false); });
});
if (await otherRunning()) {
    log('another gateway is already running — exiting');
    process.exit(0);
}

// Resume every session that has saved login data, so new messages keep arriving
for (const sid of fs.readdirSync(DATA)) {
    if (fs.existsSync(path.join(DATA, sid, 'auth', 'creds.json'))) startSession(sid).catch((e) => log(sid, 'resume error', e.message));
}
setInterval(() => sessions.forEach((s) => { try { s.store.save(); } catch (e) { log(s.sid, 'save error', e.message); } }), 20000);
setInterval(() => unloadIdle(false), 10 * 60 * 1000);

if (SOCKET) {
    try { fs.unlinkSync(SOCKET); } catch (e) { /* none */ }
    server.listen(SOCKET, () => { fs.chmodSync(SOCKET, 0o660); log('gateway listening on', SOCKET); });
} else {
    server.listen(PORT || 3911, '127.0.0.1', () => log('gateway listening on port', PORT || 3911));
}
const shutdown = () => { sessions.forEach((s) => { try { s.store.save(); } catch (e) { /* ignore */ } }); process.exit(0); };
process.on('SIGTERM', shutdown);
process.on('SIGINT', shutdown);
process.on('uncaughtException', (e) => log('uncaught', e.stack || e.message));
process.on('unhandledRejection', (e) => log('unhandled', e?.stack || e));
