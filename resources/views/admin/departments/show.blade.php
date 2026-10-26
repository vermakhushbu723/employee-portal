@extends('layouts.app')

@section('title', $department->name.' Department')
@section('back', route('admin.departments.index'))

@php
    // Keep the filters when switching tabs
    $q = array_filter(['from' => $from->toDateString(), 'to' => $to->toDateString(), 'manager' => $filters['manager'], 'employee' => $filters['employee']]);
    $tabUrl = fn ($t, $extra = []) => route('admin.departments.show', ['department' => $department, 'tab' => $t] + $q + $extra);
    $tabList = [
        ['team', 'Team Work', 'people-fill'],
        ['overview', 'Overview', 'grid-1x2'],
        ['uploaded', 'Uploaded Data', 'cloud-arrow-up'],
        ['shared', 'Shared Data', 'share'],
        ['employees', 'Employee Tracking', 'person-lines-fill'],
        ['interested', 'Interested Leads', 'hand-thumbs-up'],
        ['converted', 'Converted Leads', 'trophy'],
        ['unassigned', 'Unassigned Data', 'database'],
        ['reports', 'Reports', 'clipboard-data'],
    ];
@endphp

@section('content')
<nav class="small text-muted mb-2"><a href="{{ route('admin.departments.index') }}">Department Data</a> <i class="bi bi-chevron-right small"></i> {{ $department->name }}</nav>

{{-- Header: title + filters --}}
<div class="d-flex flex-wrap align-items-center gap-3 mb-3">
    <span class="icon-box lg tone-primary"><i class="bi bi-{{ $department->icon() }}"></i></span>
    <div class="flex-grow-1 min-w-0">
        <h2 class="h4 fw-bold mb-0">{{ $department->name }} Department</h2>
        <div class="small text-muted">Manage, track and analyze all data for {{ $department->name }} department.</div>
    </div>
    <form method="GET" class="d-flex flex-wrap gap-2 align-items-center header-filters">
        <input type="hidden" name="tab" value="{{ $tab }}">
        <div class="input-icon">
            <i class="bi bi-calendar3"></i>
            <input type="date" name="from" value="{{ $from->toDateString() }}" class="form-control form-control-sm" aria-label="From date">
        </div>
        <input type="date" name="to" value="{{ $to->toDateString() }}" class="form-control form-control-sm w-auto" aria-label="To date">
        <select name="manager" class="form-select form-select-sm w-auto" aria-label="Manager">
            <option value="">All Managers</option>
            @foreach ($allManagers as $m)
                <option value="{{ $m->id }}" @selected($filters['manager'] === $m->id)>{{ $m->name }}</option>
            @endforeach
        </select>
        <select name="employee" class="form-select form-select-sm w-auto" aria-label="Employee">
            <option value="">All Employees</option>
            @foreach ($allEmployees as $e)
                <option value="{{ $e->id }}" @selected($filters['employee'] === $e->id)>{{ $e->name }}</option>
            @endforeach
        </select>
        <button class="btn btn-primary btn-sm px-4">Apply</button>
    </form>
</div>

{{-- Page tabs --}}
<nav class="page-tabs mb-3">
    @foreach ($tabList as [$key, $label, $icon])
        <a href="{{ $tabUrl($key) }}" @class(['active' => $tab === $key])><i class="bi bi-{{ $icon }}"></i>{{ $label }}</a>
    @endforeach
</nav>

@if ($tab !== 'team')
    @include('admin.departments.partials.kpis')
@endif

@if ($tab === 'team')
    @include('admin.departments.partials.team-performance')
@elseif ($tab === 'overview')
    <div class="row g-3 mb-3">
        <div class="col-lg-4">
            <div class="card-soft card-pad h-100">
                <h3 class="section-title mb-2">Data Status ({{ $department->name }})</h3>
                @php $total = max(1, $stats['uploaded']); @endphp
                <div class="d-flex align-items-center gap-3">
                    <div style="position:relative;width:170px;height:170px;flex-shrink:0">
                        <canvas id="statusChart"></canvas>
                        <div class="donut-center"><b>{{ number_format($stats['uploaded']) }}</b><span>Total Records</span></div>
                    </div>
                    <div class="flex-grow-1">
                        <div class="kv-row"><span class="k"><span class="online-dot" style="background:var(--brand)"></span>Shared</span><span class="v">{{ number_format($stats['shared']) }} <span class="text-muted fw-normal">{{ round($stats['shared'] / $total * 100, 1) }}%</span></span></div>
                        <div class="kv-row"><span class="k"><span class="online-dot" style="background:var(--peach)"></span>Unassigned</span><span class="v">{{ number_format($stats['unassigned']) }} <span class="text-muted fw-normal">{{ round($stats['unassigned'] / $total * 100, 1) }}%</span></span></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card-soft card-pad h-100">
                <h3 class="section-title mb-2">Lead Flow (Last 7 Days)</h3>
                <div style="position:relative;height:230px"><canvas id="flowChart"></canvas></div>
            </div>
        </div>
        <div class="col-lg-3">
            <div class="card-soft card-pad h-100">
                <div class="section-head"><h3 class="section-title">Top Performing Employees</h3><a href="{{ $tabUrl('employees') }}" class="small fw-600">View All</a></div>
                @forelse ($employees->take(5) as $i => $e)
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <span class="rank-no">{{ $i + 1 }}</span>
                        <span class="small fw-600 text-truncate" style="width:95px">{{ $e['user']->name }}</span>
                        <x-progress class="flex-grow-1" :value="$e['pct']" tone="success" />
                        <span class="small fw-600">{{ $e['pct'] }}%</span>
                    </div>
                @empty
                    <x-empty-state icon="people" title="No data shared yet" />
                @endforelse
            </div>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-lg-8">
            <div class="card-soft h-100">
                <div class="card-pad pb-2 section-head"><h3 class="section-title">Manager Wise Summary</h3></div>
                <div class="table-responsive">
                    <table class="table table-soft">
                        <thead><tr><th>Manager</th><th class="text-end">Uploaded</th><th class="text-end">Shared</th><th class="text-end">Unassigned</th><th class="text-end">Called</th><th class="text-end">Interested</th><th class="text-end">Converted</th><th style="min-width:140px">Progress</th></tr></thead>
                        <tbody>
                            @forelse ($managers as $m)
                                @php $pct = $m['shared'] ? (int) round($m['called'] / $m['shared'] * 100) : 0; @endphp
                                <tr>
                                    <td class="fw-600">{{ $m['name'] }}</td>
                                    <td class="text-end">{{ number_format($m['uploaded']) }}</td>
                                    <td class="text-end">{{ number_format($m['shared']) }}</td>
                                    <td class="text-end">{{ number_format($m['unassigned']) }}</td>
                                    <td class="text-end">{{ number_format($m['called']) }}</td>
                                    <td class="text-end">{{ number_format($m['interested']) }}</td>
                                    <td class="text-end">{{ number_format($m['converted']) }}</td>
                                    <td><div class="d-flex align-items-center gap-2"><x-progress class="flex-grow-1" :value="$pct" :tone="$pct >= 60 ? 'success' : 'warning'" /><span class="small fw-600">{{ $pct }}%</span></div></td>
                                </tr>
                            @empty
                                <tr><td colspan="8"><x-empty-state icon="person-badge" title="No data for this period" /></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card-soft card-pad h-100">
                <div class="section-head"><h3 class="section-title">Data Field Information</h3>
                    @if ($latest)<a href="{{ route('admin.data.show', $latest) }}" class="btn btn-sm btn-outline-primary">View Fields</a>@endif
                </div>
                <div class="kv-row"><span class="k"><i class="bi bi-list-columns"></i>Total Fields in Data</span><span class="v">{{ $fieldInfo['total'] ?: '—' }}</span></div>
                <div class="kv-row"><span class="k"><i class="bi bi-asterisk"></i>Mandatory Fields</span><span class="v">{{ $fieldInfo['total'] ? $fieldInfo['mandatory'] : '—' }}</span></div>
                <div class="kv-row"><span class="k"><i class="bi bi-ui-checks"></i>Optional Fields</span><span class="v">{{ $fieldInfo['total'] ? $fieldInfo['optional'] : '—' }}</span></div>
                <div class="kv-row"><span class="k"><i class="bi bi-file-earmark"></i>Last Uploaded File</span><span class="v text-break">{{ $latest?->file_name ?? '—' }}</span></div>
                <div class="kv-row"><span class="k"><i class="bi bi-calendar"></i>Last Upload Date</span><span class="v">{{ $latest?->created_at->format('d M Y h:i A') ?? '—' }}</span></div>
                <div class="kv-row"><span class="k"><i class="bi bi-list-ol"></i>Rows in Last File</span><span class="v">{{ $latest ? number_format($latest->total_rows) : '—' }}</span></div>
                <div class="kv-row"><span class="k"><i class="bi bi-person"></i>Uploaded By</span><span class="v">{{ $latest?->uploader ? $latest->uploader->name.' ('.$latest->uploader->employee_code.')' : '—' }}</span></div>
            </div>
        </div>
    </div>

    {{-- Bottom tabs --}}
    <div class="card-soft">
        <div class="d-flex flex-wrap gap-2 align-items-center p-3 pb-2">
            <ul class="nav bottom-tabs" role="tablist">
                <li><button class="active" data-bs-toggle="tab" data-bs-target="#bUploads" type="button"><i class="bi bi-cloud-arrow-up"></i> Uploaded Data</button></li>
                <li><button data-bs-toggle="tab" data-bs-target="#bShared" type="button"><i class="bi bi-share"></i> Shared Data</button></li>
                <li><button data-bs-toggle="tab" data-bs-target="#bUnassigned" type="button"><i class="bi bi-database"></i> Unassigned Data</button></li>
                <li><button data-bs-toggle="tab" data-bs-target="#bEmployees" type="button"><i class="bi bi-person-lines-fill"></i> Employee Wise Details</button></li>
            </ul>
            <form method="GET" action="{{ route('admin.departments.show', $department) }}" class="ms-auto d-flex gap-2">
                @foreach ($q as $k => $v)<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endforeach
                <input type="hidden" name="tab" value="shared">
                <div class="input-icon"><i class="bi bi-search"></i><input type="search" name="q" class="form-control form-control-sm" placeholder="Search by name, mobile..."></div>
                <button class="btn btn-sm btn-outline-primary"><i class="bi bi-funnel"></i> Filter</button>
            </form>
            <a href="{{ route('admin.data.index') }}" class="btn btn-sm btn-success"><i class="bi bi-plus-lg"></i> Upload Data</a>
        </div>
        <div class="tab-content">
            <div class="tab-pane fade show active" id="bUploads">@include('admin.departments.partials.uploads-table')</div>
            <div class="tab-pane fade" id="bShared">
                @include('admin.departments.partials.lead-table', ['leads' => $sharedPreview, 'paginate' => false])
                <div class="card-pad pt-0"><a href="{{ $tabUrl('shared') }}" class="small fw-600">View all shared data →</a></div>
            </div>
            <div class="tab-pane fade" id="bUnassigned">
                @include('admin.departments.partials.lead-table', ['leads' => $unassignedPreview, 'paginate' => false])
                <div class="card-pad pt-0"><a href="{{ $tabUrl('unassigned') }}" class="small fw-600">View all unassigned data →</a></div>
            </div>
            <div class="tab-pane fade" id="bEmployees">@include('admin.departments.partials.employee-table')</div>
        </div>
    </div>
@elseif ($tab === 'uploaded')
    <div class="card-soft">
        <div class="card-pad pb-2 section-head"><h3 class="section-title">Uploaded Data ({{ $batches->count() }})</h3><a href="{{ route('admin.data.index') }}" class="btn btn-sm btn-success"><i class="bi bi-plus-lg"></i> Upload Data</a></div>
        @include('admin.departments.partials.uploads-table')
    </div>
@elseif ($tab === 'employees')
    <div class="card-soft">
        <div class="card-pad pb-2"><h3 class="section-title">Employee Tracking ({{ $employees->count() }})</h3></div>
        @include('admin.departments.partials.employee-table')
    </div>
@elseif ($tab === 'reports')
    <div class="card-soft">
        <div class="card-pad pb-2 section-head">
            <h3 class="section-title">Day-wise Report</h3>
            <a href="{{ $tabUrl('reports', ['export' => 1]) }}" class="btn btn-sm btn-success"><i class="bi bi-file-earmark-excel"></i> Download</a>
        </div>
        <div class="table-responsive">
            <table class="table table-soft">
                <thead><tr><th>Date</th><th class="text-end">Uploaded</th><th class="text-end">Called</th><th class="text-end">Interested</th><th class="text-end">Converted</th></tr></thead>
                <tbody>
                    @forelse ($daily as $r)
                        <tr><td>{{ $r['date']->format('D, d M Y') }}</td><td class="text-end">{{ $r['uploaded'] }}</td><td class="text-end">{{ $r['called'] }}</td><td class="text-end">{{ $r['interested'] }}</td><td class="text-end text-success fw-600">{{ $r['converted'] }}</td></tr>
                    @empty
                        <tr><td colspan="5"><x-empty-state icon="clipboard-data" title="No activity in this period" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@else
    @php $titles = ['shared' => 'Shared Data', 'interested' => 'Interested Leads', 'converted' => 'Converted Leads', 'unassigned' => 'Unassigned Data']; @endphp
    <div class="card-soft">
        <div class="card-pad pb-2 section-head">
            <h3 class="section-title">{{ $titles[$tab] }} ({{ number_format($list->total()) }})</h3>
            <form method="GET" class="d-flex gap-2">
                <input type="hidden" name="tab" value="{{ $tab }}">
                @foreach ($q as $k => $v)<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endforeach
                <div class="input-icon"><i class="bi bi-search"></i><input type="search" name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="Search by name, mobile..."></div>
                <button class="btn btn-sm btn-outline-primary">Search</button>
            </form>
        </div>
        @include('admin.departments.partials.lead-table', ['leads' => $list])
    </div>
@endif
@endsection

@if ($tab === 'overview')
@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
    (() => {
        const css = (v) => getComputedStyle(document.documentElement).getPropertyValue(v).trim();
        new Chart(document.getElementById('statusChart'), {
            type: 'doughnut',
            data: { labels: ['Shared', 'Unassigned'], datasets: [{ data: [{{ $stats['shared'] }}, {{ $stats['unassigned'] }}], backgroundColor: [css('--brand'), css('--peach')], borderWidth: 0 }] },
            options: { maintainAspectRatio: false, cutout: '72%', plugins: { legend: { display: false } } },
        });
        const flow = @json($flow);
        new Chart(document.getElementById('flowChart'), {
            type: 'bar',
            data: {
                labels: flow.labels,
                datasets: [
                    { label: 'Uploaded', data: flow.uploaded, backgroundColor: css('--brand'), borderRadius: 4 },
                    { label: 'Called', data: flow.called, backgroundColor: css('--sage'), borderRadius: 4 },
                    { label: 'Interested', data: flow.interested, backgroundColor: css('--peach'), borderRadius: 4 },
                    { label: 'Converted', data: flow.converted, backgroundColor: css('--purple'), borderRadius: 4 },
                ],
            },
            options: { maintainAspectRatio: false, plugins: { legend: { position: 'top', labels: { usePointStyle: true, boxWidth: 8 } } }, scales: { x: { grid: { display: false } }, y: { beginAtZero: true, ticks: { precision: 0 } } } },
        });
    })();
</script>
@endpush
@endif
