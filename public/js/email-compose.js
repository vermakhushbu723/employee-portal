/* Send Email popup (partials/email-compose): open from any [data-email-compose] button, pick a template
 * (placeholders filled from the button's data), edit, send. */
(() => {
    const form = document.querySelector('[data-compose-form]');
    if (!form) return;
    const templates = JSON.parse(form.dataset.templates || '[]');
    const base = JSON.parse(form.dataset.ctx || '{}');
    const modal = () => bootstrap.Modal.getOrCreateInstance(document.getElementById('composeModal'));
    let ctx = {};

    const fill = (text) => {
        let t = String(text || '');
        if (!ctx.time) t = t.replace(/ at \{Time\}|, \{Time\}| \(\{Time\}\)|\{Time\}/g, '');   // no time known
        if (!ctx.link) t = t.replace(/^.*\{Link\}.*$\n?/gm, '');                              // no link: drop that line
        const map = { Name: ctx.name || 'Sir / Madam', Title: ctx.title || 'our discussion', Date: ctx.date || new Date().toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' }),
            Time: ctx.time || '', Link: ctx.link || '', Purpose: ctx.purpose || ctx.title || 'our service', Employee: base.employee || '', Company: base.company || '' };
        return t.replace(/\{(\w+)\}/g, (m, k) => (k in map ? map[k] : m));
    };
    const pick = (i) => {
        form.querySelectorAll('[data-tpl]').forEach((b) => b.classList.toggle('active', b.dataset.tpl === String(i)));
        const tpl = templates[i];
        if (!tpl) { form.subject.value = ''; form.body.value = ''; form.subject.focus(); return; }
        form.subject.value = fill(tpl.subject);
        form.body.value = fill(tpl.body);
    };

    document.addEventListener('click', (e) => {
        const b = e.target.closest('[data-email-compose]');
        if (!b) return;
        e.preventDefault();
        ctx = JSON.parse(b.dataset.emailCompose || '{}');
        form.reset();
        form.querySelector('[data-compose-attach]').textContent = 'Attach file (PDF, Word, image, Excel — max 10 MB)';
        form.to.value = ctx.to || '';
        form.meeting_id.value = ctx.meeting_id || '';
        // Meetings open with the matching template (interview / invitation)
        const prefer = ctx.template ? templates.findIndex((t) => t.name === ctx.template) : (ctx.meeting_id ? templates.findIndex((t) => t.category === 'meeting') : -1);
        pick(prefer >= 0 ? prefer : '');
        modal().show();
        setTimeout(() => (form.to.value ? form.subject : form.to).focus(), 300);
    });
    form.querySelector('[data-compose-chips]').addEventListener('click', (e) => {
        const c = e.target.closest('[data-tpl]');
        if (c) pick(c.dataset.tpl === '' ? '' : Number(c.dataset.tpl));
    });
    form.attachment.addEventListener('change', () => {
        form.querySelector('[data-compose-attach]').textContent = form.attachment.files[0]?.name || 'Attach file';
    });
    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const btn = form.querySelector('.modal-footer .btn-primary');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Sending…';
        try {
            const res = await fetch(form.action, { method: 'POST', body: new FormData(form), credentials: 'same-origin', headers: { Accept: 'application/json' } });
            const data = await res.json().catch(() => ({}));
            if (!res.ok && res.status !== 502) throw new Error(data.message || Object.values(data.errors || {})[0]?.[0] || 'Not sent');
            window.liveToast?.({ type: data.ok ? 'general' : 'alert', title: data.ok ? 'E-mail sent' : 'Not sent', body: data.message });
            if (data.ok) { modal().hide(); window.liveRefresh?.('emails', true); }
        } catch (err) {
            window.liveToast?.({ type: 'alert', title: 'Not sent', body: err.message });
        } finally {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-send-fill me-1"></i>Send Email';
        }
    });
})();
