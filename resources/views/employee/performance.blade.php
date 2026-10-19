@extends('layouts.app')

@section('title', 'My Performance')
@section('back', route('employee.dashboard'))

@section('content')
<form method="GET" class="mb-3">
    <select name="month" class="form-select" onchange="this.form.submit()" aria-label="Select month">
        @foreach ($months as $m)
            <option value="{{ $m->format('Y-m') }}" @selected($m->equalTo($month))>
                {{ $m->isSameMonth(now()) ? 'This Month ('.$m->format('M Y').')' : $m->format('F Y') }}
            </option>
        @endforeach
    </select>
</form>

<div class="row g-2 g-md-3 mb-3">
    @foreach ($report['metrics'] as $metric)
        <div class="col-6 col-lg-3">
            <div class="card-soft card-pad h-100">
                <div class="d-flex gap-2 align-items-start">
                    <span class="icon-box tone-{{ $metric['tone'] }}"><i class="bi bi-{{ $metric['icon'] }}"></i></span>
                    <div class="min-w-0">
                        <div class="small text-muted">{{ $metric['label'] }}</div>
                        <div class="fs-4 fw-bold lh-1">{{ $metric['value'] }}</div>
                        <div class="small-xs text-muted">Target: {{ $metric['target'] ?: '—' }}</div>
                    </div>
                </div>
                <div class="text-center mt-2">
                    <x-badge :tone="$metric['tone']">{{ $metric['percent'] }}%</x-badge>
                </div>
            </div>
        </div>
    @endforeach
</div>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card-soft card-pad h-100">
            <div class="section-head">
                <h3 class="section-title">Daily Performance</h3>
                <x-badge tone="primary">Productivity {{ $report['productivity'] }}%</x-badge>
            </div>
            <div style="position: relative; height: 260px">
                <canvas id="weeklyChart" aria-label="Weekly performance chart" role="img"></canvas>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card-soft card-pad h-100">
            <h3 class="section-title mb-3">Today's Summary ({{ now()->format('d M Y') }})</h3>
            @forelse ($today as $row)
                <div class="d-flex align-items-center gap-3 mb-3">
                    <span class="icon-box sm soft tone-{{ $row['tone'] }}"><i class="bi bi-{{ $row['icon'] }}"></i></span>
                    <div class="flex-grow-1 min-w-0">
                        <div class="d-flex justify-content-between small mb-1">
                            <span class="fw-600 text-truncate">{{ $row['label'] }}</span>
                            <span class="fw-600">{{ $row['completed'] }} / {{ $row['target'] }}</span>
                        </div>
                        <x-progress :value="$row['percent']" :tone="$row['tone']" />
                    </div>
                </div>
            @empty
                <x-empty-state icon="bar-chart" title="No work assigned today" />
            @endforelse
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
    (() => {
        const weeks = @json($report['weeks']);
        const css = (v) => getComputedStyle(document.documentElement).getPropertyValue(v).trim();
        new Chart(document.getElementById('weeklyChart'), {
            type: 'bar',
            data: {
                labels: weeks.map(w => [w.label, `(${w.range})`]),
                datasets: [
                    { label: 'Calls', data: weeks.map(w => w.calls), backgroundColor: css('--success'), borderRadius: 6 },
                    { label: 'Leads', data: weeks.map(w => w.leads), backgroundColor: css('--brand'), borderRadius: 6 },
                    { label: 'Conversions', data: weeks.map(w => w.conversions), backgroundColor: css('--warning'), borderRadius: 6 },
                ],
            },
            options: {
                maintainAspectRatio: false,
                plugins: { legend: { position: 'top', labels: { usePointStyle: true, boxWidth: 8 } } },
                scales: { x: { grid: { display: false } }, y: { beginAtZero: true, ticks: { precision: 0 } } },
            },
        });
    })();
</script>
@endpush
