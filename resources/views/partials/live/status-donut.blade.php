{{-- Employee status donut (Chart.js). $summary, $chartId --}}
@php
    $st = $summary['states'];
    $parts = [
        ['Working', $st['working'], '#2E7D6B'],
        ['In Meeting', $st['meeting'], '#3B82C4'],
        ['Idle / Break', $st['idle'] + $st['break'], '#E9A03B'],
        ['Offline', $st['offline_all'], '#C8553D'],
    ];
@endphp
<div class="card-soft card-pad h-100">
    <h3 class="section-title mb-3">Employee Status (Live)</h3>
    <div class="d-flex align-items-center gap-3 flex-wrap">
        <div class="donut-wrap">
            <canvas id="{{ $chartId }}" data-donut='@json($parts)'></canvas>
            <div class="donut-center"><b>{{ $summary['total'] }}</b><span>Employees</span></div>
        </div>
        <ul class="list-unstyled mb-0 small flex-grow-1">
            @foreach ($parts as [$label, $n, $color])
                <li class="d-flex align-items-center gap-2 mb-2">
                    <span class="legend-dot" style="background: {{ $color }}"></span>
                    <b>{{ $n }}</b> {{ $label }}
                    <span class="text-muted ms-auto">{{ $summary['total'] ? round($n / $summary['total'] * 100) : 0 }}%</span>
                </li>
            @endforeach
        </ul>
    </div>
</div>
