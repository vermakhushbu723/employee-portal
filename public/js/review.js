/* Review & correction: every field in the table saves on change. Moving a lead off
 * Interested / Converted asks for a reason (the employee sees it, and their score drops). */
(() => {
    const table = document.getElementById('reviewTable');
    if (!table) return;
    const base = table.dataset.updateUrl.replace(/\/0$/, '');
    const csrf = () => document.querySelector('meta[name=csrf-token]')?.content;
    const POSITIVE = ['interested', 'converted'];
    // Next Action results that also change the status (Lead::NEXT_ACTIONS)
    const RESULT = JSON.parse(table.dataset.nextResults || '{}');

    const save = async (input) => {
        const row = input.closest('tr[data-lead]');
        const field = input.dataset.field;
        const body = { [field]: input.value };

        const newStatus = field === 'status' ? input.value : (field === 'next_action_type' ? RESULT[input.value] : null);
        if (newStatus && POSITIVE.includes(row.dataset.status) && !POSITIVE.includes(newStatus)) {
            const note = prompt(`${row.dataset.name}: why is this not ${row.dataset.status.replace('_', ' ')}? (the employee will see this, and their score goes down)`);
            if (note === null) { input.value = row.dataset.status; return; }
            body.note = note;
        }

        row.classList.add('saving');
        try {
            const res = await fetch(`${base}/${row.dataset.lead}`, {
                method: 'PATCH',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf() },
                body: JSON.stringify(body),
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok) throw new Error(data.message || 'Not saved');
            if (data.status && data.status !== row.dataset.status) {
                // Status changed (here or by a Next Action result): show it in the status dropdown
                row.dataset.status = data.status;
                const sel = row.querySelector('select[data-field="status"]');
                if (sel) {
                    if (![...sel.options].some((o) => o.value === data.status)) sel.add(new Option(data.status_label, data.status));
                    sel.value = data.status;
                    sel.className = sel.className.replace(/tone-\S+/, '');
                }
            }
            input.classList.add('saved');
            setTimeout(() => input.classList.remove('saved'), 1200);
            if (!row.querySelector('.corrected-tag')) {
                row.querySelector('td:nth-child(2) .min-w-0')?.insertAdjacentHTML('beforeend', '<span class="corrected-tag"><i class="bi bi-patch-check-fill"></i> Corrected now</span>');
            }
            window.liveToast?.({ type: data.score ? 'alert' : 'general', title: data.score ? 'Corrected — score updated' : 'Saved', body: data.message });
        } catch (e) {
            window.liveToast?.({ type: 'alert', title: 'Not saved', body: e.message });
        } finally {
            row.classList.remove('saving');
        }
    };

    table.addEventListener('change', (e) => { if (e.target.matches('.rv-input')) save(e.target); });
    // Text fields: save when leaving the field (change), or on Enter
    table.addEventListener('keydown', (e) => { if (e.key === 'Enter' && e.target.matches('input.rv-input')) { e.preventDefault(); e.target.blur(); } });
})();
