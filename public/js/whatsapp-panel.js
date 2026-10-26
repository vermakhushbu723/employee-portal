/* WhatsApp mini panel: QR button → floating panel (drag, resize, minimize, close) that opens
 * WhatsApp Web in its own small window and docks / sizes / focuses it. Panel layout is remembered. */
(() => {
    const fab = document.getElementById('waFab');
    const panel = document.getElementById('waPanel');
    if (!fab || !panel) return;

    const KEY = 'portal:waPanel';
    const SIZES = [[380, 560], [460, 680], [560, 800]];
    const load = () => { try { return JSON.parse(localStorage.getItem(KEY)) || {}; } catch (e) { return {}; } };
    const save = (patch) => { try { localStorage.setItem(KEY, JSON.stringify({ ...load(), ...patch })); } catch (e) { /* private mode */ } };
    const st = load();
    let win = null;
    let size = Number.isInteger(st.size) ? st.size : 1;
    let dock = st.dock || 'right';

    const stateEl = panel.querySelector('[data-wa-state]');
    const setState = () => {
        const open = win && !win.closed;
        stateEl.textContent = open ? 'Window open' : 'Not open';
        stateEl.classList.toggle('on', !!open);
    };
    setInterval(setState, 2000);

    // ---- WhatsApp window
    const geometry = () => {
        const [w, h] = SIZES[size];
        const width = Math.min(w, screen.availWidth - 20);
        const height = Math.min(h, screen.availHeight - 40);
        const left = dock === 'left' ? (screen.availLeft || 0) : (screen.availLeft || 0) + screen.availWidth - width;
        const top = (screen.availTop || 0) + Math.max(0, screen.availHeight - height - 10);
        return { width, height, left, top };
    };
    const place = () => {
        if (!win || win.closed) return;
        const g = geometry();
        try { win.resizeTo(g.width, g.height); win.moveTo(g.left, g.top); } catch (e) { /* browser may block */ }
        win.focus();
    };
    const openWa = () => {
        if (win && !win.closed) { place(); return; }
        const g = geometry();
        win = window.open('https://web.whatsapp.com/', 'portalWhatsApp', `popup=yes,width=${g.width},height=${g.height},left=${g.left},top=${g.top}`);
        if (!win) {
            window.liveToast?.({ type: 'alert', title: 'Pop-up blocked', body: 'Allow pop-ups for this site, then tap Open WhatsApp Web again.' });
            window.open('https://web.whatsapp.com/', '_blank', 'noopener');
        }
        setState();
    };

    // ---- Panel show / hide / minimize
    const show = (visible) => { panel.classList.toggle('d-none', !visible); save({ open: visible }); };
    const minimize = (min) => { panel.classList.toggle('minimized', min); save({ min }); };

    fab.addEventListener('click', (e) => {
        e.preventDefault();
        const hidden = panel.classList.contains('d-none');
        show(hidden || panel.classList.contains('minimized'));
        minimize(false);
    });
    panel.querySelector('[data-wa-close]').addEventListener('click', () => show(false));
    panel.querySelector('[data-wa-min]').addEventListener('click', () => minimize(!panel.classList.contains('minimized')));
    panel.querySelector('[data-wa-open]').addEventListener('click', openWa);
    panel.querySelector('[data-wa-front]').addEventListener('click', () => (win && !win.closed ? place() : openWa()));
    panel.querySelector('[data-wa-quit]').addEventListener('click', () => { if (win && !win.closed) win.close(); setState(); });
    panel.querySelectorAll('[data-wa-dock]').forEach((b) => b.addEventListener('click', () => { dock = b.dataset.waDock; save({ dock }); place(); }));
    panel.querySelectorAll('[data-wa-size]').forEach((b) => b.addEventListener('click', () => {
        size = Math.max(0, Math.min(SIZES.length - 1, size + Number(b.dataset.waSize)));
        save({ size });
        place();
    }));

    // ---- Drag the panel by its header (mouse + touch)
    const head = panel.querySelector('[data-wa-drag]');
    let drag = null;
    const clamp = (x, y) => [Math.max(0, Math.min(window.innerWidth - 120, x)), Math.max(0, Math.min(window.innerHeight - 44, y))];
    const startDrag = (x, y) => { const r = panel.getBoundingClientRect(); drag = { dx: x - r.left, dy: y - r.top }; panel.classList.add('dragging'); };
    const moveDrag = (x, y) => {
        if (!drag) return;
        const [l, t] = clamp(x - drag.dx, y - drag.dy);
        Object.assign(panel.style, { left: `${l}px`, top: `${t}px`, right: 'auto', bottom: 'auto' });
    };
    const endDrag = () => {
        if (!drag) return;
        drag = null;
        panel.classList.remove('dragging');
        save({ left: panel.style.left, top: panel.style.top });
    };
    head.addEventListener('mousedown', (e) => { if (!e.target.closest('button')) { startDrag(e.clientX, e.clientY); e.preventDefault(); } });
    window.addEventListener('mousemove', (e) => moveDrag(e.clientX, e.clientY));
    window.addEventListener('mouseup', endDrag);
    head.addEventListener('touchstart', (e) => { if (!e.target.closest('button')) startDrag(e.touches[0].clientX, e.touches[0].clientY); }, { passive: true });
    window.addEventListener('touchmove', (e) => { if (drag) moveDrag(e.touches[0].clientX, e.touches[0].clientY); }, { passive: true });
    window.addEventListener('touchend', endDrag);

    // Remember the panel size after the user resizes it (CSS resize handle)
    if (window.ResizeObserver) {
        new ResizeObserver(() => {
            if (!panel.classList.contains('d-none') && !panel.classList.contains('minimized')) save({ w: panel.style.width, h: panel.style.height });
        }).observe(panel);
    }

    // Restore
    if (st.left && st.top) Object.assign(panel.style, { left: st.left, top: st.top, right: 'auto', bottom: 'auto' });
    if (st.w) panel.style.width = st.w;
    if (st.h) panel.style.height = st.h;
    if (st.open) { show(true); minimize(!!st.min); }
    setState();
})();
