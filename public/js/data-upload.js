/*
 * Admin → Data Upload & Share
 *  - reads the chosen Excel/CSV in the browser (SheetJS) to list its columns and preview rows
 *  - admin ticks which columns to share; only those are sent
 *  - converts the sheet to CSV before upload, so .xls / .xlsx / .csv all work the same
 *  - department → managers → employees filtering, search, select all, counts
 */
(() => {
    'use strict';

    const form = document.getElementById('uploadForm');
    if (!form) return;

    const $ = (id) => document.getElementById(id);
    const fileInput = $('fileInput');
    const fieldGrid = $('fieldGrid');
    const MOBILE_NAMES = ['mobile', 'mobile number', 'mobile no', 'mobile no.', 'phone', 'phone number', 'phone no', 'number', 'contact', 'contact number', 'contact no', 'whatsapp'];
    const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

    let sheet = null;      // SheetJS worksheet of the chosen file
    let columns = [];      // column titles (same rules as the server)
    let rows = [];         // data rows (arrays)

    /* ------------------------------------------------------------ employees */
    const deptSelect = $('deptSelect');
    const mgrSelect = $('mgrSelect');
    const empRows = [...document.querySelectorAll('#empList .emp-row')];

    const filterManagers = () => {
        const dept = deptSelect.value;
        let any = false;
        [...mgrSelect.options].forEach((o) => {
            if (!o.value) return;
            const match = !dept || o.dataset.department === dept;
            o.hidden = !match;
            any ||= match;
        });
        // No manager mapped to this department yet: show all managers
        if (!any) [...mgrSelect.options].forEach((o) => { o.hidden = false; });
        if (mgrSelect.selectedOptions[0]?.hidden) mgrSelect.value = '';
    };

    const filterEmployees = () => {
        const mgr = mgrSelect.value;
        const dept = deptSelect.value;
        const q = $('empSearch').value.trim().toLowerCase();
        const inDept = empRows.filter((r) => dept && r.dataset.department === dept);
        let note = '';

        empRows.forEach((r) => {
            let show;
            if (mgr) show = r.dataset.manager === mgr;
            else if (inDept.length) show = r.dataset.department === dept;
            else show = true;
            if (q && !r.dataset.search.includes(q)) show = false;
            r.classList.toggle('d-none', !show);
            if (!show) r.querySelector('input').checked = false;
        });

        if (!mgr && dept && !inDept.length) note = 'No employee mapped to this department — showing all';
        if (mgr) note = `Team of ${mgrSelect.selectedOptions[0].text.split(' (')[0]}`;
        $('empNote').textContent = note;
        countEmployees();
    };

    const countEmployees = () => {
        $('empCount').textContent = empRows.filter((r) => r.querySelector('input').checked).length;
    };

    deptSelect.addEventListener('change', () => { filterManagers(); filterEmployees(); });
    mgrSelect.addEventListener('change', filterEmployees);
    $('empSearch').addEventListener('input', filterEmployees);
    $('empList').addEventListener('change', countEmployees);
    $('empAll').addEventListener('change', (e) => {
        empRows.filter((r) => !r.classList.contains('d-none')).forEach((r) => {
            const box = r.querySelector('input');
            if (!box.disabled) box.checked = e.target.checked;
        });
        countEmployees();
    });

    /* ------------------------------------------------------------ file → columns */
    const labelsFrom = (header, width) => {
        const labels = [];
        for (let i = 0; i < width; i++) {
            let label = String(header[i] ?? '').trim().slice(0, 60) || `Column ${i + 1}`;
            const base = label;
            for (let n = 2; labels.includes(label); n++) label = `${base} ${n}`;
            labels.push(label);
        }
        return labels;
    };

    const isMobile = (label) => MOBILE_NAMES.includes(label.toLowerCase().replace(/[_\s]+/g, ' ').trim());

    const readFile = async (file) => {
        $('dropText').textContent = `${file.name} (${(file.size / 1024).toFixed(0)} KB)`;
        $('originalName').value = file.name;
        if (!window.XLSX) { fieldGrid.innerHTML = '<div class="small text-muted">Preview not available — all columns will be uploaded.</div>'; return; }

        try {
            const wb = XLSX.read(await file.arrayBuffer(), { type: 'array' });
            sheet = wb.Sheets[wb.SheetNames[0]];
            const all = XLSX.utils.sheet_to_json(sheet, { header: 1, defval: '', raw: false, blankrows: false })
                .filter((r) => r.some((c) => String(c).trim() !== ''));
            if (!all.length) throw new Error('The file is empty.');
            const width = Math.max(...all.map((r) => r.length));
            columns = labelsFrom(all[0], width);
            rows = all.slice(1);
            renderFields();
        } catch (e) {
            sheet = null;
            fieldGrid.innerHTML = `<div class="small text-danger">Could not read this file: ${esc(e.message)}</div>`;
        }
    };

    const renderFields = () => {
        fieldGrid.innerHTML = columns.map((c, i) => `
            <label class="field-box ${isMobile(c) ? 'required-field' : ''}">
                <input type="checkbox" class="form-check-input m-0" name="columns[]" value="${esc(c)}" data-index="${i}" checked>
                <span>${esc(c)}</span>${isMobile(c) ? '<span class="badge-soft tone-danger ms-auto">required</span>' : ''}
            </label>`).join('');
        $('fieldsAll').checked = true;
        renderPreview();
    };

    const selected = () => [...fieldGrid.querySelectorAll('input[name="columns[]"]')].filter((b) => b.checked);

    const renderPreview = () => {
        const sel = selected();
        $('fieldCount').textContent = sel.length;
        $('fieldTotal').textContent = columns.length;
        $('chipCount').textContent = sel.length;
        $('fieldChips').innerHTML = sel.map((b) => `<span class="badge-soft tone-primary">${esc(b.value)}</span>`).join('');

        const idx = sel.map((b) => Number(b.dataset.index));
        const head = `<thead><tr><th>#</th>${idx.map((i) => `<th>${esc(columns[i])}</th>`).join('')}</tr></thead>`;
        const body = rows.slice(0, 5).map((r, n) => `<tr><td>${n + 1}</td>${idx.map((i) => `<td>${esc(r[i])}</td>`).join('')}</tr>`).join('');
        $('previewTable').innerHTML = head + `<tbody>${body || '<tr><td class="text-muted">No data rows</td></tr>'}</tbody>`;
        if (rows.length > 5) $('previewTable').insertAdjacentHTML('beforeend', `<tfoot><tr><td colspan="${idx.length + 1}" class="small text-muted">…and ${rows.length - 5} more row(s)</td></tr></tfoot>`);
    };

    fieldGrid.addEventListener('change', (e) => {
        const box = e.target.closest('input[name="columns[]"]');
        if (!box) return;
        if (!box.checked && isMobile(box.value)) {
            box.checked = true;
            window.liveToast?.({ type: 'alert', title: 'Required field', body: 'The Mobile Number column is needed for calling.' });
        }
        $('fieldsAll').checked = selected().length === columns.length;
        renderPreview();
    });

    $('fieldsAll').addEventListener('change', (e) => {
        fieldGrid.querySelectorAll('input[name="columns[]"]').forEach((b) => { b.checked = e.target.checked || isMobile(b.value); });
        renderPreview();
    });

    fileInput.addEventListener('change', () => fileInput.files[0] && readFile(fileInput.files[0]));

    // Drag & drop
    const zone = $('dropzone');
    ['dragenter', 'dragover'].forEach((ev) => zone.addEventListener(ev, (e) => { e.preventDefault(); zone.classList.add('over'); }));
    ['dragleave', 'drop'].forEach((ev) => zone.addEventListener(ev, (e) => { e.preventDefault(); zone.classList.remove('over'); }));
    zone.addEventListener('drop', (e) => {
        if (!e.dataTransfer.files.length) return;
        fileInput.files = e.dataTransfer.files;
        readFile(e.dataTransfer.files[0]);
    });

    /* ------------------------------------------------------------ submit */
    form.addEventListener('submit', (e) => {
        if (!deptSelect.value) { e.preventDefault(); deptSelect.focus(); window.liveToast?.({ type: 'alert', title: 'Department required', body: 'Select a department first (step 1).' }); return; }
        if (!fileInput.files.length) { e.preventDefault(); window.liveToast?.({ type: 'alert', title: 'No file', body: 'Choose an Excel or CSV file (step 4).' }); return; }
        if (columns.length && !selected().some((b) => isMobile(b.value))) { e.preventDefault(); window.liveToast?.({ type: 'alert', title: 'Mobile column missing', body: 'Your file needs a Mobile / Phone column.' }); return; }

        // "Upload & Share" needs employees; "Only Upload" puts the data in the pool
        const btn = e.submitter && e.submitter.name === 'action' ? e.submitter : $('uploadBtn');
        const onlyUpload = btn.value === 'upload';
        if (!onlyUpload && !form.querySelector('input[name="users[]"]:checked')) {
            e.preventDefault();
            window.liveToast?.({ type: 'alert', title: 'No employee selected', body: 'Select employees in step 2 — or use "Only Upload" to share it later.' });
            return;
        }

        // Send the sheet as CSV (works for .xls, .xlsx and .csv alike)
        if (sheet && window.XLSX && window.DataTransfer) {
            const csv = XLSX.utils.sheet_to_csv(sheet, { FS: ',', blankrows: false, rawNumbers: true });
            const name = ($('originalName').value || 'data').replace(/\.[^.]+$/, '') + '.csv';
            const dt = new DataTransfer();
            dt.items.add(new File(['﻿' + csv], name, { type: 'text/csv' }));
            fileInput.files = dt.files;
        }
        // A disabled submitter is not sent, so carry the choice in a hidden field
        let action = form.querySelector('input[type=hidden][name=action]');
        if (!action) { action = document.createElement('input'); action.type = 'hidden'; action.name = 'action'; form.appendChild(action); }
        action.value = btn.value;
        form.querySelectorAll('button[name=action]').forEach((b) => { b.disabled = true; });
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Uploading…';
    });

    filterManagers();
    filterEmployees();
})();

/* Share Unassigned Data: manager filter, employee search, select all, count */
(() => {
    const form = document.getElementById('shareForm');
    if (!form) return;
    const mgr = form.querySelector('[data-share-manager]');
    const search = form.querySelector('[data-share-search]');
    const rows = [...form.querySelectorAll('.share-emp[data-search]')];
    const all = form.querySelector('[data-share-all]');
    const count = () => { form.querySelector('[data-share-count]').textContent = form.querySelectorAll('input[name="users[]"]:checked').length; };

    const filter = () => {
        const m = mgr.value;
        const q = search.value.trim().toLowerCase();
        let shown = 0;
        rows.forEach((r) => {
            const inTeam = !m || r.dataset.manager === m;
            const ok = inTeam && (!q || r.dataset.search.includes(q));
            r.classList.toggle('d-none', !ok);
            if (!inTeam) r.querySelector('input').checked = false; // other managers' employees are not shared with
            if (ok) shown++;
        });
        form.querySelector('[data-share-empty]').classList.toggle('d-none', shown > 0);
        all.checked = false;
        count();
    };

    mgr.addEventListener('change', filter);
    search.addEventListener('input', filter);
    all.addEventListener('change', () => {
        rows.filter((r) => !r.classList.contains('d-none')).forEach((r) => { r.querySelector('input').checked = all.checked; });
        count();
    });
    form.addEventListener('change', (e) => { if (e.target.name === 'users[]') count(); });
    form.addEventListener('submit', (e) => {
        if (!form.querySelector('input[name="users[]"]:checked')) {
            e.preventDefault();
            window.liveToast?.({ type: 'alert', title: 'No employee selected', body: 'Select at least one employee to share the data with.' });
        }
    });
})();
