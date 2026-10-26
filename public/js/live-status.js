/* Live status dashboards (admin / manager): status donut + browser-side table filters.
 * Both re-apply after a live refresh ("live:updated" from app.js), so filters survive. */
(() => {
    const charts = new Map();

    const drawDonuts = () => {
        if (!window.Chart) return;
        document.querySelectorAll('canvas[data-donut]').forEach((canvas) => {
            const parts = JSON.parse(canvas.dataset.donut);
            charts.get(canvas.id)?.destroy();
            const empty = parts.every((p) => !p[1]);
            charts.set(canvas.id, new Chart(canvas, {
                type: 'doughnut',
                data: {
                    labels: parts.map((p) => p[0]),
                    datasets: [{ data: empty ? [1] : parts.map((p) => p[1]), backgroundColor: empty ? ['#E9E2DA'] : parts.map((p) => p[2]), borderWidth: 0 }],
                },
                options: { cutout: '72%', animation: false, plugins: { legend: { display: false }, tooltip: { enabled: !empty } } },
            }));
        });
    };

    const filter = { state: '', team: '', q: '' };
    const applyFilter = () => {
        document.querySelectorAll('.live-table tbody').forEach((body) => {
            let shown = 0;
            body.querySelectorAll('tr[data-state]').forEach((tr) => {
                const ok = (!filter.state || tr.dataset.state === filter.state)
                    && (!filter.team || tr.dataset.team === filter.team)
                    && (!filter.q || tr.dataset.search.includes(filter.q));
                tr.classList.toggle('d-none', !ok);
                if (ok) shown++;
            });
            body.querySelector('.live-no-match')?.classList.toggle('d-none', shown > 0 || !body.querySelector('tr[data-state]'));
        });
    };

    document.addEventListener('click', (e) => {
        const tab = e.target.closest('[data-filter-state]');
        if (!tab) return;
        e.preventDefault();
        tab.parentElement.querySelectorAll('[data-filter-state]').forEach((a) => a.classList.toggle('active', a === tab));
        filter.state = tab.dataset.filterState;
        applyFilter();
    });
    document.addEventListener('input', (e) => {
        if (e.target.matches('[data-filter-q]')) { filter.q = e.target.value.trim().toLowerCase(); applyFilter(); }
    });
    document.addEventListener('change', (e) => {
        if (e.target.matches('[data-filter-team]')) { filter.team = e.target.value; applyFilter(); }
    });

    document.addEventListener('live:updated', (e) => {
        if (e.detail.key !== 'status') return;
        drawDonuts();
        applyFilter();
    });

    drawDonuts();
    applyFilter();
})();
