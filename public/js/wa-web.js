/*
 * WhatsApp Web mode: the real https://web.whatsapp.com/ runs in ONE popup window docked on the right
 * side of the screen (WhatsApp does not allow itself inside another page, so it gets its own window).
 * Scan the QR there once — the browser keeps you logged in, chats load exactly like on a laptop.
 *
 *   window.portalWA.open()               open / bring the WhatsApp window to the front
 *   window.portalWA.chat(number, text)   open that customer's chat with the message typed — press Send
 *
 * On phones the WhatsApp app opens instead (WhatsApp Web does not work on phones).
 * Every wa.me link in the portal is routed here too, so all WhatsApp sending uses the same window.
 */
(() => {
    const NAME = 'portalWhatsApp';
    const mobile = matchMedia('(pointer: coarse)').matches || /Android|iPhone|iPad|Mobi/i.test(navigator.userAgent);
    let win = null;

    const digits = (n) => {
        let d = String(n || '').replace(/\D/g, '');
        if (d.length === 10) d = `91${d}`;   // Indian number without country code
        return d;
    };
    const features = () => {
        const w = Math.min(560, Math.max(420, Math.round(screen.availWidth * 0.34)));
        const h = screen.availHeight;
        const left = (screen.availLeft || 0) + screen.availWidth - w;
        return `popup=yes,width=${w},height=${h},left=${left},top=${screen.availTop || 0},resizable=yes,scrollbars=yes`;
    };
    const go = (url, reload) => {
        if (win && !win.closed) {
            if (reload) win.location.href = url;   // a new chat needs WhatsApp Web to open that link
            win.focus();
            return true;
        }
        win = window.open(url, NAME, features());
        if (!win) {
            window.liveToast?.({ type: 'alert', title: 'Pop-up blocked', body: 'Allow pop-ups for this site (icon at the right of the address bar), then tap WhatsApp again.' });
            return false;
        }
        win.focus();
        return true;
    };

    window.portalWA = {
        mobile,
        open() {
            if (mobile) { location.href = 'https://wa.me/'; return true; }
            return go('https://web.whatsapp.com/', false);
        },
        chat(number, text = '') {
            const phone = digits(number);
            const q = new URLSearchParams(text ? { phone, text } : { phone });
            if (mobile) { location.href = `https://wa.me/${phone}${text ? `?text=${encodeURIComponent(text)}` : ''}`; return true; }
            return go(`https://web.whatsapp.com/send?${q}`, true);
        },
        /** wa.me link → same docked WhatsApp Web window */
        fromLink(href) {
            const u = new URL(href);
            const phone = u.searchParams.get('phone') || u.pathname.replace(/\D/g, '');
            return phone ? this.chat(phone, u.searchParams.get('text') || '') : this.open();
        },
    };

    // All WhatsApp links in the portal open in the docked WhatsApp Web window (desktop)
    document.addEventListener('click', (e) => {
        const a = e.target.closest('a[href^="https://wa.me/"], a[href^="https://api.whatsapp.com/"], a[href^="https://web.whatsapp.com/"]');
        if (!a || mobile || e.ctrlKey || e.metaKey) return;
        e.preventDefault();
        a.href.startsWith('https://web.whatsapp.com/') && !a.href.includes('/send') ? window.portalWA.open() : window.portalWA.fromLink(a.href);
    });
})();
