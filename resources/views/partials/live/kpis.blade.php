{{-- Live status tiles. $summary from LiveStatusService::summary(), $totalLabel --}}
@php
    $st = $summary['states'];
    $pc = $summary['pct'];
    $tiles = [
        ['people-fill', $totalLabel ?? 'Total Employees', $summary['total'], null, 'primary'],
        ['check-circle-fill', 'Available Now', $st['working'], $pc['working'].'% working right now', 'success'],
        ['camera-video-fill', 'In Meeting', $st['meeting'], $pc['meeting'].'% in live meetings', 'info'],
        ['hourglass-split', 'Idle / Not Active', $st['idle'] + $st['break'], ($pc['idle'] + $pc['break']).'% idle or on break', 'warning'],
        ['x-circle-fill', 'Offline', $st['offline_all'], $st['offline'].' not logged in · '.$st['out'].' punched out', 'danger'],
    ];
@endphp
<div class="live-kpis mb-3">
    @foreach ($tiles as [$icon, $label, $value, $hint, $tone])
        <div class="live-kpi tone-{{ $tone }}">
            <span class="stat-icon"><i class="bi bi-{{ $icon }}"></i></span>
            <div class="min-w-0">
                <div class="stat-label">{{ $label }}</div>
                <div class="stat-value">{{ $value }}</div>
                @if ($hint)<div class="stat-hint">{{ $hint }}</div>@endif
            </div>
        </div>
    @endforeach
</div>
