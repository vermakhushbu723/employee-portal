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
        const refreshRegion = async (key) => {
            const regions = [...document.querySelectorAll(`[data-live-region="${key}"]`)];
            if (!regions.length) return true;
            const busy = document.querySelector('.modal.show, .dropdown-menu.show')
                || regions.some((r) => r.contains(document.activeElement) && /INPUT|TEXTAREA|SELECT/.test(document.activeElement.tagName));
            if (busy) return false;

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
    }

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
