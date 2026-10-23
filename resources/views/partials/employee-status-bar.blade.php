{{-- Employee working status, shown under the top bar on every employee page. Live values tick in public/js/app.js. --}}
@use('App\Support\Duration')

@php
    $breakUsed = $sbWork['break'] ?? 0;
    $breakPct = $sbBreakAllowed > 0 ? min(100, round($breakUsed / $sbBreakAllowed * 100)) : 0;
    $open = $sbSession && $sbSession->isOpen();
@endphp

<div class="status-bar" id="statusBar">
    <div class="sb-chip">
        <span class="sb-icon tone-primary"><i class="bi bi-calendar3"></i></span>
        <div><div class="sb-value">{{ now()->format('D, d M Y') }}</div><div class="sb-label">{{ now()->format('l') }}</div></div>
    </div>

    <div class="sb-chip tone-success">
        <span class="sb-icon"><i class="bi bi-clock"></i></span>
        <div><div class="sb-label">Login Time</div><div class="sb-value">{{ $sbSession?->check_in_at->format('h:i A') ?? '—' }}</div></div>
    </div>

    <div class="sb-chip tone-success">
        <span class="sb-icon"><i class="bi bi-play-circle-fill"></i></span>
        <div><div class="sb-label">Working Time</div><div class="sb-value" data-sb="active">{{ Duration::hms($sbWork['active'] ?? 0) }}</div></div>
    </div>

    <div class="sb-chip tone-danger">
        <span class="sb-icon"><i class="bi bi-pause-circle-fill"></i></span>
        <div><div class="sb-label">Not Working Time</div><div class="sb-value" data-sb="idle">{{ Duration::hms($sbWork['idle'] ?? 0) }}</div></div>
    </div>

    <div class="sb-chip tone-primary">
        <span class="sb-icon"><i class="bi bi-clock-fill"></i></span>
        <div><div class="sb-label">Total Time</div><div class="sb-value" data-total-nav>{{ $sbSession ? \App\Support\Duration::hms(max(0, ($sbSession->check_out_at ?? now())->getTimestamp() - $sbSession->check_in_at->getTimestamp())) : '00:00:00' }}</div></div>
    </div>

    <div class="sb-chip tone-warning sb-break">
        <span class="sb-icon"><i class="bi bi-cup-hot-fill"></i></span>
        <div class="min-w-0">
            <div class="sb-label">Break</div>
            <div class="sb-value" data-sb="break_left">{{ intdiv($breakUsed, 60) }} / {{ intdiv($sbBreakAllowed, 60) }} min</div>
            <div class="sb-progress"><span data-sb="break_bar" style="width: {{ $breakPct }}%"></span></div>
            <span class="d-none" data-sb="break">{{ Duration::hms($breakUsed) }}</span>
        </div>
        @if ($open)
            @if ($sbSession->activeBreak())
                <form method="POST" action="{{ route('employee.breaks.end') }}">
                    @csrf
                    <button class="btn btn-sm btn-danger text-nowrap">End Break</button>
                </form>
            @else
                <form method="POST" action="{{ route('employee.breaks.start') }}">
                    @csrf
                    <button class="btn btn-sm btn-warning text-nowrap">Take Break</button>
                </form>
            @endif
        @endif
    </div>

    <div class="sb-chip tone-info">
        <span class="sb-icon"><i class="bi bi-calendar2-check"></i></span>
        <div>
            <div class="sb-label">Leave Used</div>
            <div class="sb-value">{{ $sbLeavesUsed + 0 }} / {{ $sbLeavesUsed + $sbLeavesLeft }}
                <a href="{{ route('employee.leaves.index') }}" class="small-xs fw-600 ms-1">View Leaves</a></div>
        </div>
    </div>

    @if ($sbWork)
        @php [$lvl, $gradeLabel, $gTone] = \App\Services\WorkScoreService::battery($sbWork['grade']); @endphp
        <div class="sb-chip tone-{{ $gTone }}">
            <span class="battery tone-{{ $gTone }}"><span style="width: {{ max(4, min(100, $sbWork['score'])) }}%"></span></span>
            <div><div class="sb-label">Work Score</div><div class="sb-value">{{ $sbWork['score'] }}%</div></div>
        </div>
    @endif

    <div class="sb-chip sb-user">
        <span class="avatar tone-primary" style="width:34px;height:34px;font-size:.72rem">{{ $sbUser->initials }}</span>
        <div>
            <div class="sb-value">{{ $sbUser->name }}</div>
            <div class="sb-label text-uppercase-none">{{ $sbUser->employee_code }} · <span class="online-dot" data-sb="online"></span><span data-sb="online_text">{{ $open ? 'Online' : 'Offline' }}</span></div>
        </div>
    </div>
</div>
