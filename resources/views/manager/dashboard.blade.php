@extends('layouts.app')

@section('title', 'Manager Dashboard')

@section('content')
<div class="d-flex flex-wrap align-items-center gap-2 mb-3">
    <div class="flex-grow-1">
        <h1 class="h4 fw-bold mb-0">Team Manager Dashboard</h1>
        <div class="small text-muted">Track and manage your team · {{ now()->format('d M Y') }} <span class="live-pill ms-1"><i class="bi bi-broadcast"></i> Live</span></div>
    </div>
    <a href="{{ route('manager.tracking') }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-geo-alt me-1"></i>Live Tracking</a>
    <a href="{{ route('manager.corrections.index') }}" class="btn btn-sm {{ $pendingCorrections ? 'btn-warning' : 'btn-outline-primary' }}">
        <i class="bi bi-pencil-square me-1"></i>Corrections @if ($pendingCorrections)<span class="badge rounded-pill bg-danger">{{ $pendingCorrections }}</span>@endif
    </a>
    <a href="{{ route('manager.approvals.index') }}" class="btn btn-sm {{ $pendingApprovals ? 'btn-warning' : 'btn-outline-primary' }}">
        <i class="bi bi-check2-square me-1"></i>Approvals @if ($pendingApprovals)<span class="badge rounded-pill bg-danger">{{ $pendingApprovals }}</span>@endif
    </a>
    <a href="{{ route('manager.assign-work.create') }}" class="btn btn-sm btn-primary"><i class="bi bi-clipboard-plus me-1"></i>Assign Work</a>
</div>

<div data-live-region="status">
    @include('partials.live.kpis', ['summary' => $summary, 'totalLabel' => 'My Team Members'])

    <div class="row g-3 mb-3">
        <div class="col-lg-8">
            @include('partials.live.performance', ['summary' => $summary, 'title' => 'Team Performance (Today)'])
        </div>
        <div class="col-lg-4 mb-3">
            @include('partials.live.status-donut', ['summary' => $summary, 'chartId' => 'teamDonut'])
        </div>
    </div>
</div>

<div class="card-soft mb-3">
    <div class="card-pad pb-0"><h3 class="section-title mb-0">My Team ({{ $rows->count() }})</h3></div>
    @include('partials.live.filters', ['rows' => $rows])
    <div data-live-region="status">
        @include('partials.live.employee-table', ['rows' => $rows, 'portal' => 'manager'])
    </div>
</div>

<div class="row g-3" data-live-region="status">
    <div class="col-lg-7">
        <div class="card-soft card-pad h-100">
            <div class="section-head">
                <h3 class="section-title">Upcoming Meetings ({{ $meetings->count() }})</h3>
                <a href="{{ route('manager.meetings.index') }}" class="small fw-600">View All</a>
            </div>
            @forelse ($meetings as $m)
                @php [$mLabel, $mTone] = $m->badge(); @endphp
                <div class="list-row">
                    <span class="icon-box sm soft tone-info"><i class="bi bi-{{ $m->platformIcon() }}"></i></span>
                    <div class="flex-grow-1 min-w-0">
                        <div class="list-title text-truncate">{{ $m->customer_name }} — {{ $m->title }}</div>
                        <div class="small-xs text-muted">{{ $m->scheduled_at->format('d M, h:i A') }} · {{ $m->user->name }} ({{ $m->user->employee_code }})</div>
                    </div>
                    <x-badge :tone="$mTone">{{ $mLabel }}</x-badge>
                    @if (in_array($m->status, ['upcoming', 'live'], true) && ($m->isPortal() || $m->link))
                        <a href="{{ $m->isPortal() ? route('manager.meetings.room', $m) : $m->link }}" @unless ($m->isPortal()) target="_blank" rel="noopener" @endunless class="btn btn-sm btn-primary">Join</a>
                    @endif
                </div>
            @empty
                <x-empty-state icon="calendar-event" title="No upcoming meetings" />
            @endforelse
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card-soft card-pad h-100">
            <h3 class="section-title mb-3">Live Notifications</h3>
            @forelse ($alerts as [$icon, $tone, $title, $text])
                <div class="list-row">
                    <span class="icon-box sm soft tone-{{ $tone }}"><i class="bi bi-{{ $icon }}"></i></span>
                    <div class="min-w-0"><div class="list-title">{{ $title }}</div><div class="small-xs text-muted">{{ $text }}</div></div>
                </div>
            @empty
                <x-empty-state icon="bell" title="All good" text="Idle, offline and low-battery alerts for your team appear here." />
            @endforelse
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script src="{{ asset('js/live-status.js') }}?v={{ filemtime(public_path('js/live-status.js')) }}"></script>
@endpush
