{{-- Six KPI tiles with change vs the previous period. --}}
@php
    $kpis = [
        ['uploaded', 'Total Uploaded', 'cloud-arrow-up', 'primary'],
        ['shared', 'Total Shared', 'share', 'success'],
        ['unassigned', 'Unassigned', 'database', 'warning'],
        ['called', 'Called / Worked', 'telephone-fill', 'purple'],
        ['interested', 'Interested / Follow-up', 'people-fill', 'danger'],
        ['converted', 'Converted', 'trophy-fill', 'info'],
    ];
@endphp
<div class="row g-3 mb-3">
    @foreach ($kpis as [$key, $label, $icon, $tone])
        <div class="col-6 col-md-4 col-xl-2">
            <div class="stat-tile tone-{{ $tone }}">
                <span class="stat-icon"><i class="bi bi-{{ $icon }}"></i></span>
                <div class="min-w-0">
                    <div class="stat-label">{{ $label }}</div>
                    <div class="stat-value">{{ number_format($stats[$key] ?? 0) }}</div>
                    @if (($change[$key] ?? null) !== null)
                        <div class="stat-hint {{ $change[$key] >= 0 ? 'text-success' : 'text-danger' }}">
                            <i class="bi bi-arrow-{{ $change[$key] >= 0 ? 'up' : 'down' }}"></i> {{ abs($change[$key]) }}% vs last period
                        </div>
                    @else
                        <div class="stat-hint">—</div>
                    @endif
                </div>
            </div>
        </div>
    @endforeach
</div>
