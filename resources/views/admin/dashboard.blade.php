@extends('layouts.app')

@section('title', 'Admin Dashboard')

@section('content')
<div class="d-flex flex-wrap align-items-center gap-2 mb-3">
    <div class="flex-grow-1">
        <h1 class="h4 fw-bold mb-0">Admin Dashboard</h1>
        <div class="small text-muted">Live overview of all employees and teams · {{ now()->format('d M Y') }} <span class="live-pill ms-1"><i class="bi bi-broadcast"></i> Live</span></div>
    </div>
    <a href="{{ route('admin.tracking') }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-geo-alt me-1"></i>Live Tracking</a>
    <a href="{{ route('admin.corrections.index') }}" class="btn btn-sm {{ $pending['corrections'] ? 'btn-warning' : 'btn-outline-primary' }}">
        <i class="bi bi-pencil-square me-1"></i>Corrections @if ($pending['corrections'])<span class="badge rounded-pill bg-danger">{{ $pending['corrections'] }}</span>@endif
    </a>
    <a href="{{ route('admin.late-requests.index') }}" class="btn btn-sm {{ $pending['late'] ? 'btn-warning' : 'btn-outline-primary' }}">
        <i class="bi bi-alarm me-1"></i>Late @if ($pending['late'])<span class="badge rounded-pill bg-danger">{{ $pending['late'] }}</span>@endif
    </a>
    <a href="{{ route('admin.leaves.index') }}" class="btn btn-sm {{ $pending['leaves'] ? 'btn-warning' : 'btn-outline-primary' }}">
        <i class="bi bi-calendar2-check me-1"></i>Leaves @if ($pending['leaves'])<span class="badge rounded-pill bg-danger">{{ $pending['leaves'] }}</span>@endif
    </a>
</div>

<div data-live-region="status">
    @include('partials.live.kpis', ['summary' => $summary])
    @include('partials.live.performance', ['summary' => $summary])

    <div class="row g-3 mb-3">
        <div class="col-lg-4">
            @include('partials.live.status-donut', ['summary' => $summary, 'chartId' => 'statusDonut'])
        </div>
        <div class="col-lg-4">
            <div class="card-soft card-pad h-100">
                <h3 class="section-title mb-3">Top 5 Performers (Today)</h3>
                @forelse ($top as $i => $r)
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <span class="rank-badge">{{ $i + 1 }}</span>
                        <div class="flex-grow-1 min-w-0">
                            <div class="d-flex justify-content-between small"><b class="text-truncate">{{ $r['user']->name }}</b><b>{{ $r['progress'] }}%</b></div>
                            <x-progress class="mt-1" :value="$r['progress']" tone="success" />
                            <div class="small-xs text-muted">{{ $r['calls'] }}/{{ $r['calls_target'] }} calls · {{ $r['interested'] }} interested</div>
                        </div>
                    </div>
                @empty
                    <x-empty-state icon="trophy" title="No one punched in yet" />
                @endforelse
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card-soft card-pad h-100">
                <h3 class="section-title mb-3">Departments Performance</h3>
                @forelse ($departments as $d)
                    <div class="d-flex align-items-center gap-2 mb-2 small">
                        <span class="avatar tone-primary" style="width:28px;height:28px;font-size:.7rem">{{ strtoupper(substr($d['name'], 0, 1)) }}</span>
                        <div class="flex-grow-1 min-w-0">
                            <div class="d-flex justify-content-between"><span class="text-truncate">{{ $d['name'] }}</span><b>{{ $d['calls'] }}/{{ $d['target'] }}</b></div>
                            <x-progress class="mt-1" :value="\App\Support\Duration::percent($d['calls'], $d['target'])" tone="warning" />
                            <div class="small-xs text-muted">{{ $d['working'] }} of {{ $d['count'] }} working now</div>
                        </div>
                    </div>
                @empty
                    <x-empty-state icon="building" title="No departments" />
                @endforelse
            </div>
        </div>
    </div>
</div>

<div class="card-soft mb-3">
    <div class="card-pad pb-0 d-flex align-items-center gap-2">
        <h3 class="section-title mb-0">All Employees ({{ $rows->count() }})</h3>
        <span class="small text-muted">Status, battery and location update automatically</span>
    </div>
    @include('partials.live.filters', ['rows' => $rows, 'exportUrl' => route('admin.dashboard', ['export' => 1])])
    <div data-live-region="status">
        @include('partials.live.employee-table', ['rows' => $rows, 'portal' => 'admin'])
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-5">
        <div class="card-soft card-pad h-100">
            <div class="section-head">
                <h3 class="section-title">Today's Meetings</h3>
                <a href="{{ route('admin.meetings.index') }}" class="small fw-600">View All</a>
            </div>
            @forelse ($meetingsToday as $m)
                @php [$mLabel, $mTone] = $m->badge(); @endphp
                <div class="list-row">
                    <span class="icon-box sm soft tone-info"><i class="bi bi-{{ $m->platformIcon() }}"></i></span>
                    <div class="flex-grow-1 min-w-0">
                        <div class="list-title text-truncate">{{ $m->title }} · {{ $m->customer_name }}</div>
                        <div class="small-xs text-muted">{{ $m->scheduled_at->format('h:i A') }} · {{ $m->user->name }}</div>
                    </div>
                    <x-badge :tone="$mTone">{{ $mLabel }}</x-badge>
                </div>
            @empty
                <x-empty-state icon="calendar-event" title="No meetings today" />
            @endforelse
        </div>
    </div>
    <div class="col-lg-7">
        @php $installUrl = url('/install'); @endphp
        <div class="card-soft card-pad mb-3 d-flex flex-wrap align-items-center gap-3">
            <span class="icon-box lg tone-success"><i class="bi bi-phone"></i></span>
            <div class="flex-grow-1 min-w-0">
                <div class="fw-bold">App install link for employees</div>
                <code class="small">{{ $installUrl }}</code>
            </div>
            <button type="button" class="btn btn-outline-primary btn-sm" data-copy="{{ $installUrl }}"><i class="bi bi-clipboard"></i> Copy</button>
            <a href="https://wa.me/?text={{ rawurlencode('Install our Employee Portal app on your phone: '.$installUrl) }}" target="_blank" rel="noopener" class="btn btn-success btn-sm"><i class="bi bi-whatsapp"></i> Share</a>
        </div>
        <div class="card-soft">
            <div class="card-pad pb-2"><h3 class="section-title">Recent Activity</h3></div>
            <div class="table-responsive">
                <table class="table table-soft mb-0">
                    <tbody>
                        @forelse ($logs as $log)
                            <tr>
                                <td class="text-nowrap small">{{ $log->created_at->format('d M, h:i A') }}</td>
                                <td class="text-nowrap small">{{ $log->user?->name ?? 'System' }}</td>
                                <td class="small">{{ $log->description }}</td>
                            </tr>
                        @empty
                            <tr><td><x-empty-state icon="journal" title="No activity yet" /></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script src="{{ asset('js/live-status.js') }}?v={{ filemtime(public_path('js/live-status.js')) }}"></script>
@endpush
