{{-- Department → Team Work Report. $team from DepartmentDataController::teamPerformance(), $tabUrl, $department --}}
@use('App\Support\Duration')
@php
    $k = $team['kpis'];
    $period = request('period', request()->has('from') ? 'custom' : 'month');
    $tiles = [
        ['people-fill', 'Total Employees', $k['employees'], 'primary'],
        ['telephone-fill', 'Total Calls', number_format($k['calls']), 'success'],
        ['people', 'Interested', number_format($k['interested']), 'warning'],
        ['file-earmark-text-fill', 'Leads Created', number_format($k['created']), 'purple'],
        ['clock-fill', 'Avg. Work Time', Duration::hm($k['work_avg']), 'danger'],
        ['check-circle-fill', 'Avg. Conversion', $k['conversion'].'%', 'info'],
    ];
@endphp

<div class="d-flex flex-wrap align-items-center gap-2 mb-3">
    <div class="flex-grow-1">
        <h3 class="h5 fw-bold mb-0">{{ $department->name }} — Team Work Report</h3>
        <div class="small text-muted">Employee-wise performance and work details · {{ $from->format('d M') }} – {{ $to->format('d M Y') }}</div>
    </div>
    <nav class="seg-tabs seg-sm">
        <a href="{{ route('admin.departments.show', ['department' => $department, 'tab' => 'team', 'period' => 'week']) }}" @class(['active' => $period === 'week'])>This Week</a>
        <a href="{{ route('admin.departments.show', ['department' => $department, 'tab' => 'team', 'period' => 'month']) }}" @class(['active' => $period === 'month'])>This Month</a>
        <a href="#" onclick="document.querySelector('.header-filters input[name=from]')?.focus(); return false;" @class(['active' => $period === 'custom'])>Custom Range</a>
    </nav>
</div>

<div class="team-kpis mb-3">
    @foreach ($tiles as [$icon, $label, $value, $tone])
        <div class="live-kpi tone-{{ $tone }}">
            <span class="stat-icon"><i class="bi bi-{{ $icon }}"></i></span>
            <div class="min-w-0"><div class="stat-label">{{ $label }}</div><div class="stat-value">{{ $value }}</div></div>
        </div>
    @endforeach
</div>

<div class="card-soft">
    <div class="card-pad pb-2 d-flex flex-wrap align-items-center gap-2">
        <h3 class="section-title mb-0 flex-grow-1"><i class="bi bi-people-fill me-1"></i>Team Performance</h3>
        <nav class="seg-tabs seg-sm">
            <a href="#" class="active" data-filter-state="">All</a>
            <a href="#" data-filter-state="working">Working</a>
            <a href="#" data-filter-state="idle">Idle</a>
            <a href="#" data-filter-state="offline">Offline</a>
        </nav>
        <div class="input-icon"><i class="bi bi-search"></i><input type="search" class="form-control form-control-sm" placeholder="Search by name, employee ID…" data-filter-q></div>
        <a href="{{ $tabUrl('team', ['export' => 1]) }}" class="btn btn-sm btn-outline-success"><i class="bi bi-download"></i> Export</a>
    </div>
    <div class="table-responsive">
        <table class="table table-soft align-middle live-table team-table mb-0">
            <thead>
                <tr>
                    <th rowspan="2">#</th>
                    <th rowspan="2">Employee Name (ID)</th>
                    <th rowspan="2" class="text-center">Total<br>Records</th>
                    <th colspan="2" class="text-center">Calls</th>
                    <th colspan="2" class="text-center">Interested</th>
                    <th colspan="2" class="text-center">Leads Created</th>
                    <th colspan="2" class="text-center">Work Time</th>
                    <th rowspan="2">Last Active<br>Date</th>
                    <th rowspan="2">Status</th>
                    <th rowspan="2"></th>
                </tr>
                <tr class="sub">
                    <th class="text-center">Done</th><th class="text-center">Pending</th>
                    <th class="text-center">Count</th><th class="text-center">%</th>
                    <th class="text-center">Count</th><th class="text-center">%</th>
                    <th class="text-center">Avg / Day</th><th class="text-center">Login</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($team['rows'] as $i => $r)
                    @php $u = $r['user']; @endphp
                    <tr data-state="{{ in_array($r['state'], ['offline', 'out'], true) ? 'offline' : (in_array($r['state'], ['idle', 'break'], true) ? 'idle' : 'working') }}" data-team="" data-search="{{ strtolower($u->name.' '.$u->employee_code) }}">
                        <td class="text-muted small">{{ $i + 1 }}</td>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <span class="avatar tone-primary" style="width:32px;height:32px;font-size:.7rem">{{ $u->initials }}</span>
                                <div><div class="fw-600">{{ $u->name }}</div><div class="small-xs text-muted">{{ $u->employee_code }}</div></div>
                            </div>
                        </td>
                        <td class="text-center">{{ $r['total'] }}</td>
                        <td class="text-center text-success fw-600">{{ $r['done'] }}</td>
                        <td class="text-center text-danger fw-600">{{ $r['pending'] }}</td>
                        <td class="text-center">{{ $r['interested'] }}</td>
                        <td class="text-center"><span class="pct-pill tone-success">{{ $r['interested_pct'] }}%</span></td>
                        <td class="text-center">{{ $r['created'] }}</td>
                        <td class="text-center"><span class="pct-pill tone-info">{{ $r['created_pct'] }}%</span></td>
                        <td class="text-center small">{{ $r['days'] ? Duration::hm($r['work_avg']) : '—' }}</td>
                        <td class="text-center small">{{ $r['login']?->format('h:i A') ?? '—' }}</td>
                        <td class="small">{{ $r['last_date']?->format('d M Y') ?? '—' }}</td>
                        <td><x-badge :tone="$r['tone']">{{ $r['label'] }}</x-badge></td>
                        <td class="text-end text-nowrap">
                            <div class="dropdown">
                                <button class="btn btn-sm btn-primary" data-bs-toggle="dropdown">View</button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li><a class="dropdown-item" href="{{ $tabUrl('shared', ['employee' => $u->id]) }}"><i class="bi bi-list-check me-2"></i>Leads of this employee</a></li>
                                    <li><a class="dropdown-item" href="{{ route('admin.reports.daily', ['q' => $u->employee_code]) }}"><i class="bi bi-file-earmark-text me-2"></i>Daily report</a></li>
                                    @if ($r['session_today'])
                                        <li><a class="dropdown-item" href="{{ route('admin.corrections.create', $r['session_today']) }}"><i class="bi bi-pencil-square me-2"></i>Correct today's data</a></li>
                                    @endif
                                    <li><a class="dropdown-item" href="{{ route('admin.tracking', ['user' => $u->id]) }}"><i class="bi bi-geo-alt me-2"></i>Live tracking</a></li>
                                </ul>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="14"><x-empty-state icon="people" title="No employees in this department" text="Set the department on the employee profile, or share this department's data with employees." /></td></tr>
                @endforelse
                <tr class="live-no-match d-none"><td colspan="14" class="text-center text-muted small py-4">No employee matches this filter.</td></tr>
            </tbody>
        </table>
    </div>
    <div class="card-pad py-2 small text-muted">Showing {{ $team['rows']->count() }} employee(s). Interested % and Leads Created % are out of calls done.</div>
</div>

@push('scripts')
<script src="{{ asset('js/live-status.js') }}?v={{ filemtime(public_path('js/live-status.js')) }}"></script>
@endpush
