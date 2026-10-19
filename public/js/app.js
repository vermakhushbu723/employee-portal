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

    /* ------------------------------------------------------------------ App (PWA) */

    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => navigator.serviceWorker.register('/sw.js').catch(() => {}));
    }

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

    // "Install app" banner: Android/Chrome uses the native prompt, iPhone gets instructions.
    const banner = document.getElementById('installBanner');
    const standalone = window.matchMedia('(display-mode: standalone)').matches || navigator.standalone === true;
    const dismissedKey = 'installBannerDismissedAt';
    const recentlyDismissed = () => {
        try { return Date.now() - Number(localStorage.getItem(dismissedKey) || 0) < 3 * 24 * 3600 * 1000; } catch (e) { return false; }
    };

    if (banner && !standalone && !recentlyDismissed()) {
        let deferredPrompt = null;
        const show = () => banner.classList.remove('d-none');
        const hide = () => banner.classList.add('d-none');

        window.addEventListener('beforeinstallprompt', (e) => {
            e.preventDefault();
            deferredPrompt = e;
            show();
        });

        const isIos = /iphone|ipad|ipod/i.test(navigator.userAgent);
        if (isIos) {
            banner.querySelector('[data-install-text]').innerHTML = 'Tap <i class="bi bi-box-arrow-up"></i> Share, then <b>Add to Home Screen</b>.';
            banner.querySelector('[data-install-accept]').classList.add('d-none');
            show();
        }

        banner.querySelector('[data-install-accept]').addEventListener('click', async () => {
            if (!deferredPrompt) return;
            deferredPrompt.prompt();
            await deferredPrompt.userChoice;
            deferredPrompt = null;
            hide();
        });

        banner.querySelector('[data-install-dismiss]').addEventListener('click', () => {
            try { localStorage.setItem(dismissedKey, String(Date.now())); } catch (e) { /* storage blocked */ }
            hide();
        });

        window.addEventListener('appinstalled', hide);
    }
})();
