/* Employee Portal — small vanilla JS helpers (no build step). */
(() => {
    'use strict';

    const pad = (n) => String(Math.floor(n)).padStart(2, '0');
    const fmt = {
        hms: (s) => { s = Math.max(0, Math.floor(s)); return `${pad(s / 3600)}:${pad((s % 3600) / 60)}:${pad(s % 60)}`; },
        hm: (s) => { s = Math.max(0, Math.floor(s)); return `${pad(s / 3600)}h ${pad((s % 3600) / 60)}m`; },
        pct: (a, b) => (b > 0 ? Math.min(100, Math.round((a / b) * 100)) : 0),
    };

    /**
     * Live work timer.
     * <div data-live-timer='{...server timer json...}' data-status-url="...">
     *   <span data-bind="worked:hms"></span>           -> formatted value
     *   <span data-bind="worked:pct" data-of="required"></span>
     *   <span data-bind-width="worked:required"></span> -> progress width
     *   <circle data-ring>                              -> progress ring
     * The server stays the source of truth: state is re-synced every minute.
     */
    class LiveTimer {
        constructor(root) {
            this.root = root;
            this.state = JSON.parse(root.dataset.liveTimer);
            this.statusUrl = root.dataset.statusUrl;
            this.render();
            setInterval(() => this.step(), 1000);
            if (this.statusUrl) setInterval(() => this.sync(), 60000);
        }

        step() {
            const s = this.state;
            if (!s.checked_in || s.checked_out) return;
            if (s.on_break) {
                s.break_used++;
                s.break_remaining = Math.max(0, s.break_allowed - s.break_used);
            } else {
                s.worked++;
                s.remaining = Math.max(0, s.required - s.worked);
            }
            s.shift_ends_in = Math.max(0, s.shift_ends_in - 1);
            this.render();
        }

        async sync() {
            try {
                const res = await fetch(this.statusUrl, { headers: { Accept: 'application/json' } });
                if (res.ok) { this.state = await res.json(); this.render(); }
            } catch (e) { /* offline: keep ticking locally */ }
        }

        render() {
            const s = this.state;
            this.root.querySelectorAll('[data-bind]').forEach((el) => {
                const [key, format = 'hms'] = el.dataset.bind.split(':');
                el.textContent = format === 'pct' ? `${fmt.pct(s[key], s[el.dataset.of || 'required'])}%` : fmt[format](s[key]);
            });
            this.root.querySelectorAll('[data-bind-width]').forEach((el) => {
                const [key, of] = el.dataset.bindWidth.split(':');
                el.style.width = `${fmt.pct(s[key], s[of])}%`;
            });
            this.root.querySelectorAll('[data-ring]').forEach((el) => {
                const c = 2 * Math.PI * el.r.baseVal.value;
                el.style.strokeDasharray = c;
                el.style.strokeDashoffset = c * (1 - fmt.pct(s.worked, s.required) / 100);
            });
        }
    }

    document.querySelectorAll('[data-live-timer]').forEach((el) => new LiveTimer(el));

    // Forms with data-confirm="message" ask before submitting.
    document.addEventListener('submit', (e) => {
        const msg = e.target.dataset.confirm;
        if (msg && !window.confirm(msg)) e.preventDefault();
    });

    // Show / hide password.
    document.querySelectorAll('[data-toggle-password]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const input = document.getElementById(btn.dataset.togglePassword);
            input.type = input.type === 'password' ? 'text' : 'password';
            btn.querySelector('i').classList.toggle('bi-eye');
            btn.querySelector('i').classList.toggle('bi-eye-slash');
        });
    });

    /**
     * Open a modal and fill it from the clicked element:
     *   data-modal="#id"           modal to open
     *   data-action="url"          sets the modal <form> action
     *   data-fill='{"name":"x"}'   fills [data-field="name"] text and [name="name"] inputs
     */
    document.addEventListener('click', (e) => {
        const trigger = e.target.closest('[data-modal]');
        if (!trigger || !window.bootstrap) return;
        e.preventDefault();

        const modal = document.querySelector(trigger.dataset.modal);
        const form = modal.querySelector('form');
        if (form && trigger.dataset.action) form.action = trigger.dataset.action;

        const data = trigger.dataset.fill ? JSON.parse(trigger.dataset.fill) : {};
        Object.entries(data).forEach(([key, value]) => {
            modal.querySelectorAll(`[data-field="${key}"]`).forEach((el) => { el.textContent = value ?? '—'; });
            modal.querySelectorAll(`[name="${key}"]`).forEach((el) => { el.value = value ?? ''; el.dispatchEvent(new Event('change')); });
        });

        bootstrap.Modal.getOrCreateInstance(modal).show();
    });

    // Lead status modal: follow-up date only when status = follow_up.
    document.querySelectorAll('[data-follow-up-toggle]').forEach((select) => {
        const target = document.querySelector(select.dataset.followUpToggle);
        const sync = () => target.classList.toggle('d-none', select.value !== 'follow_up');
        select.addEventListener('change', sync);
        sync();
    });

    // Employee form: warn when an admin-portal role (HR / Super Admin) is chosen.
    document.querySelectorAll('[data-role-warning]').forEach((select) => {
        const warning = document.querySelector(select.dataset.roleWarning);
        const sync = () => warning.classList.toggle('d-none', !['hr', 'super_admin'].includes(select.value));
        select.addEventListener('change', sync);
        sync();
    });

    // Re-open a modal after a failed validation (server sets data-reopen-modal on <body>).
    const reopen = document.body.dataset.reopenModal;
    if (reopen && window.bootstrap) {
        const el = document.querySelector(reopen);
        if (el) bootstrap.Modal.getOrCreateInstance(el).show();
    }

    /* ------------------------------------------------------------------ Real-time */
    /*
     * Every 10 s (and whenever the tab becomes visible) ask /live/pulse for
     * new notifications and per-area versions. A changed version means that
     * area's data changed: re-fetch the current page and swap only the
     * [data-live-region="<area>"] blocks — no full refresh, no lost scroll.
     */
    const pulseUrl = document.body.dataset.pulseUrl;
    if (pulseUrl) {
        let after = Number(document.body.dataset.pulseAfter || 0);
        let versions = null;
        let timer = null;

        const escapeHtml = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

        const toast = (n) => {
            const box = document.getElementById('liveToasts');
            if (!box || !window.bootstrap) return;
            const icon = { important: 'megaphone-fill', alert: 'exclamation-triangle-fill' }[n.type] || 'bell-fill';
            const el = document.createElement('div');
            el.className = 'toast live-toast';
            el.setAttribute('role', 'status');
            el.innerHTML = `<div class="toast-body d-flex gap-2 align-items-start">
                <span class="icon-box sm tone-${n.type === 'alert' ? 'warning' : 'primary'}"><i class="bi bi-${icon}"></i></span>
                <div class="flex-grow-1 min-w-0"><div class="fw-bold">${escapeHtml(n.title)}</div><div class="small text-muted">${escapeHtml(n.body)}</div></div>
                <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button></div>`;
            box.appendChild(el);
            el.addEventListener('hidden.bs.toast', () => el.remove());
            bootstrap.Toast.getOrCreateInstance(el, { delay: 8000 }).show();
        };

        const setBell = (count) => {
            const badge = document.getElementById('bellBadge');
            if (!badge) return;
            badge.textContent = count > 9 ? '9+' : count;
            badge.classList.toggle('d-none', !count);
        };

        // Swap fresh copies of the live regions in; returns false if it had to wait.
        const refreshRegion = async (key, force = false) => {
            const regions = [...document.querySelectorAll(`[data-live-region="${key}"]`)];
            if (!regions.length) return true;
            const busy = document.querySelector('.modal.show, .dropdown-menu.show')
                || regions.some((r) => r.contains(document.activeElement) && /INPUT|TEXTAREA|SELECT/.test(document.activeElement.tagName));
            if (busy && !force) return false;

            const res = await fetch(location.href, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' });
            if (!res.ok) return true;
            const doc = new DOMParser().parseFromString(await res.text(), 'text/html');
            const fresh = [...doc.querySelectorAll(`[data-live-region="${key}"]`)];
            regions.forEach((region, i) => {
                if (!fresh[i]) return;
                region.replaceWith(fresh[i]);
                fresh[i].classList.add('live-updated');
                setTimeout(() => fresh[i].classList.remove('live-updated'), 1600);
            });
            return true;
        };

        const pulse = async () => {
            if (document.hidden) return;
            try {
                const res = await fetch(`${pulseUrl}?after=${after}`, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
                if (res.status === 401 || res.status === 419) { clearInterval(timer); return; }
                if (!res.ok) return;
                const data = await res.json();

                setBell(data.alerts);
                data.new.forEach((n) => { toast(n); after = Math.max(after, n.id); });
                after = Math.max(after, data.latest_id || 0);

                const next = { ...data.versions };
                if (versions) {
                    for (const [key, version] of Object.entries(data.versions)) {
                        if (versions[key] !== version && !(await refreshRegion(key))) {
                            next[key] = versions[key]; // busy: try again next tick
                        }
                    }
                }
                versions = next;
            } catch (e) { /* offline: try again next tick */ }
        };

        setTimeout(pulse, 1500);
        timer = setInterval(pulse, 10000);
        document.addEventListener('visibilitychange', () => { if (!document.hidden) pulse(); });

        window.liveRefresh = refreshRegion;
        window.liveToast = toast;
    }

    const csrf = () => document.querySelector('meta[name=csrf-token]')?.content;

    /* ------------------------------------------------------------------ Inline lead status */
    // Changing the dropdown saves at once; "Follow-up" first asks for a date. The list then refreshes,
    // so the lead moves to the tab of its new status.
    const saveStatus = async (form) => {
        const row = form.closest('.lead-row');
        const body = new FormData(form);
        body.append('_method', 'PATCH');
        body.append('_token', csrf());
        row?.classList.add('saving');
        try {
            const res = await fetch(form.action, { method: 'POST', body, headers: { Accept: 'application/json' }, credentials: 'same-origin' });
            const data = await res.json().catch(() => ({}));
            if (!res.ok) throw new Error(data.message || 'Could not update status.');
            window.liveToast?.({ type: 'general', title: 'Status updated', body: data.message });
            await window.liveRefresh?.('leads', true);
        } catch (e) {
            window.liveToast?.({ type: 'alert', title: 'Not saved', body: e.message });
            row?.classList.remove('saving');
        }
    };

    document.addEventListener('change', (e) => {
        const select = e.target.closest('[data-inline-status] select[name=status]');
        if (!select) return;
        const form = select.form;
        const pick = form.querySelector('.follow-up-pick');
        if (select.value === 'follow_up') {
            pick.classList.remove('d-none');
            const date = pick.querySelector('input');
            date.required = true;
            date.focus();
            return;
        }
        pick.classList.add('d-none');
        saveStatus(form);
    });

    document.addEventListener('submit', (e) => {
        const form = e.target.closest('[data-inline-status]');
        if (!form) return;
        e.preventDefault();
        saveStatus(form);
    }, true);

    /* ------------------------------------------------------------------ Active time (navbar) */
    // Counts only while the employee is active: any mouse/keyboard/touch/scroll in the last 5 minutes.
    // The server keeps the real total; the browser sends a heartbeat every 20 s.
    const activeEl = document.getElementById('activeTimer');
    if (activeEl) {
        const cfg = JSON.parse(activeEl.dataset.activeTimer);
        const display = activeEl.querySelector('[data-active-display]');
        const stateEl = activeEl.querySelector('[data-active-state]');
        const IDLE_AFTER = (cfg.idle_after || 60) * 1000;
        const clockOffset = (cfg.server_now || Date.now() / 1000) - Date.now() / 1000; // server vs device clock
        const sb = (name) => document.querySelector(`[data-sb="${name}"]`);
        let seconds = cfg.seconds;
        let tracking = cfg.tracking;
        let lastActivity = Date.now();

        ['mousemove', 'mousedown', 'keydown', 'touchstart', 'scroll', 'wheel'].forEach((ev) =>
            window.addEventListener(ev, () => { lastActivity = Date.now(); }, { passive: true }));

        const isActive = () => tracking && !document.hidden && Date.now() - lastActivity < IDLE_AFTER;
        const render = () => {
            display.textContent = fmt.hms(seconds);
            const active = isActive();
            activeEl.classList.toggle('idle', !active);
            if (stateEl) stateEl.textContent = !tracking ? 'Active · paused' : (active ? 'Active' : 'Active · idle');

            // Navbar inactive time + status bar: working / idle / break counters
            const now = Date.now() / 1000 + clockOffset;
            const end = cfg.ended || now;
            const breakUsed = cfg.break_closed + (cfg.break_started ? Math.max(0, end - cfg.break_started) : 0);
            const idle = Math.max(0, end - cfg.check_in - breakUsed - seconds);
            document.querySelectorAll('[data-idle-nav]').forEach((el) => { el.textContent = fmt.hms(idle); });
            document.querySelectorAll('[data-total-nav]').forEach((el) => { el.textContent = fmt.hms(Math.max(0, end - cfg.check_in)); });
            if (sb('active')) {
                sb('active').textContent = fmt.hms(seconds);
                sb('idle').textContent = fmt.hms(idle);
                sb('break').textContent = fmt.hms(breakUsed);
                sb('break_left').textContent = `${Math.floor(breakUsed / 60)} / ${Math.round(cfg.break_allowed / 60)} min`;
                const bar = sb('break_bar');
                if (bar) bar.style.width = `${Math.min(100, (breakUsed / Math.max(1, cfg.break_allowed)) * 100)}%`;
                sb('online')?.classList.toggle('idle', !active);
                if (sb('online_text') && !cfg.ended) sb('online_text').textContent = cfg.break_started ? 'On Break' : (active ? 'Online' : 'Idle');
            }
        };

        const beat = async () => {
            try {
                const body = new URLSearchParams({ _token: csrf(), active: isActive() ? 1 : 0 });
                const res = await fetch(cfg.url, { method: 'POST', body, headers: { Accept: 'application/json' }, credentials: 'same-origin' });
                if (!res.ok) return;
                const data = await res.json();
                seconds = data.active_seconds;
                tracking = data.tracking;
                render();
            } catch (e) { /* offline */ }
        };

        setInterval(() => { if (isActive()) seconds++; render(); }, 1000);
        setInterval(beat, 20000);
        beat();
        document.addEventListener('visibilitychange', () => { if (!document.hidden) { lastActivity = Date.now(); beat(); } });
        render();
    }

    /* ------------------------------------------------------------------ Live clock in punch-in popup */
    setInterval(() => {
        document.querySelectorAll('[data-clock]').forEach((el) => {
            el.textContent = new Date().toLocaleTimeString('en-IN', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true });
        });
    }, 1000);

    // "Select all" link for checkbox groups: <a data-check-all="users[]">
    document.addEventListener('click', (e) => {
        const link = e.target.closest('[data-check-all]');
        if (!link) return;
        e.preventDefault();
        const boxes = [...document.querySelectorAll(`input[type=checkbox][name="${link.dataset.checkAll}"]`)];
        const check = boxes.some((b) => !b.checked);
        boxes.forEach((b) => { b.checked = check; });
        link.textContent = check ? 'Clear all' : 'Select all';
    });

    /* ------------------------------------------------------------------ App (PWA) */

    // (The service worker is registered early in partials/pwa-head.)

    // Loading bar while the next page loads (feels like an app screen change).
    const progress = document.getElementById('navProgress');
    const startProgress = () => progress && progress.classList.add('active');
    document.addEventListener('click', (e) => {
        const a = e.target.closest('a[href]');
        if (!a || a.target === '_blank' || e.ctrlKey || e.metaKey || a.hasAttribute('data-modal') || a.hasAttribute('data-bs-toggle')) return;
        const url = new URL(a.href, location.href);
        if (url.origin !== location.origin || url.hash && url.pathname === location.pathname) return;
        startProgress();
    });
    document.addEventListener('submit', (e) => { if (!e.defaultPrevented) startProgress(); });
    window.addEventListener('pageshow', () => progress && progress.classList.remove('active'));

    /* ------------------------------------------------------------------ Install app */
    /*
     * Chrome's install prompt is captured early in partials/pwa-head (window.__installPrompt).
     * - Banner: shown on every new visit until the app is installed ("×" hides it for this visit only).
     * - /install page: big Install button, or step-by-step help where Chrome can't prompt (iPhone, etc.).
     */
    const standalone = window.matchMedia('(display-mode: standalone)').matches || navigator.standalone === true;
    const isIos = /iphone|ipad|ipod/i.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
    const banner = document.getElementById('installBanner');
    const installPage = document.getElementById('installPage');

    const runPrompt = async () => {
        const prompt = window.__installPrompt;
        if (!prompt) return false;
        prompt.prompt();
        const choice = await prompt.userChoice;
        window.__installPrompt = null;
        return choice.outcome === 'accepted';
    };

    // Already installed on this phone? (Chrome Android, via manifest related_applications)
    const checkInstalled = async () => {
        try {
            if (navigator.getInstalledRelatedApps) return (await navigator.getInstalledRelatedApps()).length > 0;
        } catch (e) { /* not supported */ }
        return false;
    };

    if (banner && !standalone && !installPage) {
        const dismissedThisVisit = () => { try { return sessionStorage.getItem('installBannerHidden') === '1'; } catch (e) { return false; } };
        const show = () => { if (!dismissedThisVisit()) banner.classList.remove('d-none'); };
        const hide = () => banner.classList.add('d-none');

        if (window.__installPrompt) show();
        document.addEventListener('installable', show);

        if (isIos) {
            banner.querySelector('[data-install-text]').innerHTML = 'Tap <i class="bi bi-box-arrow-up"></i> Share, then <b>Add to Home Screen</b>.';
            banner.querySelector('[data-install-accept]').classList.add('d-none');
            show();
        }

        banner.querySelector('[data-install-accept]').addEventListener('click', async () => {
            if (await runPrompt()) hide();
            else if (!window.__installPrompt) location.href = '/install';
        });
        banner.querySelector('[data-install-dismiss]').addEventListener('click', () => {
            try { sessionStorage.setItem('installBannerHidden', '1'); } catch (e) { /* storage blocked */ }
            hide();
        });
        window.addEventListener('appinstalled', hide);
        checkInstalled().then((installed) => installed && hide());
    }

    if (installPage) {
        const states = [...installPage.querySelectorAll('[data-install-state]')];
        const showState = (name) => states.forEach((el) => el.classList.toggle('d-none', el.dataset.installState !== name));

        const decide = async () => {
            if (standalone) return showState('opened');
            if (await checkInstalled()) return showState('installed');
            if (window.__installPrompt) return showState('ready');
            if (isIos) return showState('ios');
            showState('manual');
        };

        // Chrome may take a moment to decide the site is installable
        showState('checking');
        document.addEventListener('installable', decide);
        setTimeout(decide, 1800);

        installPage.querySelectorAll('[data-install-accept]').forEach((btn) => btn.addEventListener('click', async () => {
            if (await runPrompt()) showState('done');
        }));
        window.addEventListener('appinstalled', () => showState('done'));
    }

    // Header checkbox that ticks every row checkbox with the given name: <input data-toggle-rows="ids[]">
    document.addEventListener('change', (e) => {
        const head = e.target.closest('[data-toggle-rows]');
        if (!head) return;
        document.querySelectorAll(`input[type=checkbox][name="${head.dataset.toggleRows}"]`).forEach((b) => { b.checked = head.checked; });
    });

    // Copy-to-clipboard buttons: <button data-copy="text">
    document.querySelectorAll('[data-copy]').forEach((btn) => btn.addEventListener('click', async () => {
        try {
            await navigator.clipboard.writeText(btn.dataset.copy);
            const original = btn.innerHTML;
            btn.innerHTML = '<i class="bi bi-check2"></i> Copied';
            setTimeout(() => { btn.innerHTML = original; }, 2000);
        } catch (e) { window.prompt('Copy this link:', btn.dataset.copy); }
    }));
})();
