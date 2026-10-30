@extends('layouts.app')

@use('App\Support\Duration')
@use('App\Services\WorkScoreService')
@use('App\Services\LiveStatusService')
@use('App\Http\Controllers\DailyWorkReportController')

@section('title', 'Daily Work Report')

@section('content')
@php
    $stateMeta = [
        'working' => ['Working', 'Live Now', 'success', 'broadcast'],
        'busy' => ['Busy', 'On Meeting', 'warning', 'telephone-fill'],
        'idle' => ['Idle', 'No activity', 'warning', 'hourglass-split'],
        'offline' => ['Offline', 'Portal closed', 'danger', 'wifi-off'],
        'punched_out' => ['Punch Out', 'Shift Completed', 'danger', 'box-arrow-right'],
        'not_logged_in' => ['Not Logged In', 'Offline', 'secondary', 'circle'],
    ];
@endphp

<form method="GET" class="report-head mb-3">
    <div class="flex-grow-1">
        <h1 class="h4 fw-bold mb-0">Daily Work Report</h1>
        <div class="small text-muted">Track attendance, work progress, leads, meetings, emails and real-time activity</div>
    </div>
    <div class="input-icon">
        <i class="bi bi-calendar3"></i>
        <input type="date" name="date" value="{{ $date->toDateString() }}" max="{{ today()->toDateString() }}" class="form-control form-control-sm" onchange="this.form.submit()" aria-label="Date">
    </div>
    <select name="department" class="form-select form-select-sm w-auto" onchange="this.form.submit()" aria-label="Department">
        <option value="">All Departments</option>
        @foreach ($departments as $d)<option value="{{ $d->id }}" @selected($filters['department'] === $d->id)>{{ $d->name }}</option>@endforeach
    </select>
    <select name="status" class="form-select form-select-sm w-auto" onchange="this.form.submit()" aria-label="Status">
        <option value="">All Status</option>
        @foreach ($stateMeta as $key => [$label])<option value="{{ $key }}" @selected($filters['status'] === $key)>{{ $label }}</option>@endforeach
    </select>
    <div class="input-icon">
        <i class="bi bi-search"></i>
        <input type="search" name="q" value="{{ $filters['q'] }}" class="form-control form-control-sm" placeholder="Search by name, ID, manager…">
    </div>
    @if ($isAdmin)
        <a href="{{ request()->fullUrlWithQuery(['export' => 1]) }}" class="btn btn-sm btn-success"><i class="bi bi-download me-1"></i>Download</a>
    @endif
</form>

@if ($isAdmin && ! $mailReady)
    <div class="alert alert-warning small py-2"><i class="bi bi-envelope-exclamation me-1"></i>Report e-mails are not set up yet.</div>
@endif

<div class="report-kpis mb-3" data-live-region="status">
    @foreach ($kpis as [$icon, $label, $value, $pct, $tone])
        <div class="report-kpi tone-{{ $tone }}">
            <span class="stat-icon"><i class="bi bi-{{ $icon }}"></i></span>
            <div class="min-w-0"><div class="stat-label">{{ $label }}</div><div class="stat-value">{{ $value }}</div>@if ($pct)<div class="stat-hint">{{ $pct }}</div>@endif</div>
        </div>
    @endforeach
</div>

<div class="card-soft" data-live-region="status">
    <div class="table-responsive">
        <table class="table table-soft align-middle report-table mb-0">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Employee<br><span class="sub">ID / Name</span></th>
                    <th>Dept<br><span class="sub">Manager</span></th>
                    <th>Punch In / Out<br><span class="sub">Actual / Extended</span></th>
                    <th>Shift Time</th>
                    <th>Live Status</th>
                    <th>Work Time<br><span class="sub">Active | Inactive | Break</span></th>
                    <th class="text-center">Calls<br><span class="sub">Done / Target</span></th>
                    <th class="text-center">Leads<br><span class="sub">Created</span></th>
                    <th class="text-center">Interested<br><span class="sub">Done</span></th>
                    <th class="text-center">Matured<br><span class="sub">Immatured</span></th>
                    <th class="text-center">Meetings<br><span class="sub">Done / Total</span></th>
                    <th class="text-center">Emails<br><span class="sub">Sent</span></th>
                    <th class="text-center">WhatsApp<br><span class="sub">Sent</span></th>
                    <th style="min-width:120px">Progress<br><span class="sub">% (Pending)</span></th>
                    <th>Battery<br><span class="sub">Location</span></th>
                    <th class="text-center">Work Score</th>
                    <th class="text-center">Attendance Avg<br><span class="small-xs text-muted">Weekly | Monthly</span></th>
                    <th>Work Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $i => $r)
                    @php
                        $u = $r['user'];
                        $s = $r['session'];
                        $w = $r['work'];
                        $st = DailyWorkReportController::state($r);
                        [$stLabel, $stSub, $stTone, $stIcon] = $stateMeta[$st];
                        $win = $r['window'];
                        $ext = $win['extension'] ?? 0;
                        [$batIcon, $batTone] = LiveStatusService::batteryIcon($u->battery_level, $u->battery_charging);
                        $score = $w['score'] ?? 0;
                        [$wsLabel, $wsTone] = match ($r['work_status']) { 'completed' => ['Completed', 'success'], 'incomplete' => ['Incomplete', 'danger'], default => ['Not Started', 'secondary'] };
                    @endphp
                    <tr>
                        <td class="text-muted small">{{ $rows->firstItem() + $i }}</td>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <span class="avatar tone-primary" style="width:32px;height:32px;font-size:.7rem">{{ $u->initials }}</span>
                                <div><div class="fw-600 text-nowrap">{{ $u->name }}</div><div class="small-xs text-muted">{{ $u->employee_code }}</div></div>
                            </div>
                        </td>
                        <td class="small text-nowrap">{{ $u->employee?->department?->name ?? '—' }}<div class="small-xs text-muted">{{ $u->employee?->manager?->name ?? 'No Manager' }}</div></td>
                        <td class="small text-nowrap">
                            @if ($s)
                                <div><span class="dot-s bg-success"></span>{{ $s->check_in_at->format('h:i A') }}</div>
                                <div>
                                    @if ($s->check_out_at)<span class="dot-s bg-danger"></span>{{ $s->check_out_at->format('h:i A') }}
                                    @else <span class="dot-s bg-primary"></span><span class="text-primary">In Progress</span> @endif
                                </div>
                                @if ($ext)<div class="small-xs text-danger">(+{{ Duration::hm($ext) }}){{ $s->check_out_at ? '' : ' will extend' }}</div>@endif
                            @else
                                <span class="text-muted">--:-- <div class="small-xs">Not Punched In</div></span>
                            @endif
                        </td>
                        <td class="small text-nowrap">
                            Actual: {{ $r['shift']->label() }}
                            @if ($win && $ext)<div class="small-xs text-muted">Extended: {{ $win['actual_end']->format('h:i A') }} – {{ $win['extended_end']->format('h:i A') }}</div>
                            @elseif ($s)<div class="small-xs text-muted">(No Extension)</div>@endif
                        </td>
                        <td><span class="live-chip tone-{{ $stTone }}"><i class="bi bi-{{ $stIcon }}"></i> {{ $stLabel }}<small>{{ $stSub }}</small></span></td>
                        <td class="small text-nowrap font-monospace">
                            @if ($w)
                                <div class="text-success">{{ Duration::hm($w['active']) }}</div>
                                <div class="text-danger">{{ Duration::hm($w['idle']) }}</div>
                                <div class="text-warning">{{ Duration::hm($w['break']) }}</div>
                            @else — @endif
                        </td>
                        <td class="text-center small"><b>{{ $r['calls'] }}</b> / {{ $r['calls_target'] }}</td>
                        <td class="text-center fw-600">{{ $w['created'] ?? 0 }}</td>
                        <td class="text-center fw-600">{{ $w['interested'] ?? 0 }}</td>
                        <td class="text-center small"><b class="text-success">{{ $r['matured'] }}</b><div class="text-muted">{{ $r['immatured'] }}</div></td>
                        <td class="text-center small"><b class="text-primary">{{ $r['meetings_done'] }}</b> / {{ $r['meetings_total'] }}</td>
                        <td class="text-center">{{ $r['emails'] }}</td>
                        <td class="text-center">{{ $r['whatsapp'] }}</td>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <x-progress class="flex-grow-1" :value="$r['progress']" :tone="$r['progress'] >= 85 ? 'success' : ($r['progress'] >= 50 ? 'warning' : 'danger')" />
                                <b class="small">{{ $r['progress'] }}%</b>
                            </div>
                            <div class="small-xs text-muted">{{ $r['calls_pending'] }} Pending</div>
                        </td>
                        <td class="small text-nowrap">
                            @if ($u->battery_level !== null && $u->device_at?->isSameDay($date))<div class="text-{{ $batTone }}"><i class="bi bi-{{ $batIcon }}"></i> {{ $u->battery_level }}%</div>@else <div class="text-muted">--</div>@endif
                            <div class="{{ $u->location_status === 'denied' ? 'text-danger' : ($u->latitude ? 'text-success' : 'text-muted') }}"><i class="bi bi-geo-alt-fill"></i> {{ $u->location_status === 'denied' ? 'Off' : ($u->latitude ? 'On' : '--') }}</div>
                        </td>
                        <td class="text-center"><span class="score-pill tone-{{ $score >= 85 ? 'success' : ($score >= 50 ? 'warning' : 'danger') }}">{{ $score }}%</span></td>
                        <td class="text-center small text-nowrap">
                            @if ($av = $r['avg'] ?? null)
                                <b class="text-{{ $av['week_att'] >= 85 ? 'success' : ($av['week_att'] >= 60 ? 'warning' : 'danger') }}">{{ $av['week_att'] }}%</b> | <b class="text-{{ $av['month_att'] >= 85 ? 'success' : ($av['month_att'] >= 60 ? 'warning' : 'danger') }}">{{ $av['month_att'] }}%</b>
                                <div class="small-xs text-muted" title="Average work score: last 7 days | this month">Score {{ $av['week_score'] ?? '—' }}{{ $av['week_score'] !== null ? '%' : '' }} | {{ $av['month_score'] ?? '—' }}{{ $av['month_score'] !== null ? '%' : '' }}</div>
                            @else — @endif
                        </td>
                        <td><x-badge :tone="$wsTone">{{ $wsLabel }}</x-badge></td>
                        <td class="text-nowrap">
                            <a href="{{ route($portal.'.review.index', ['user' => $u->id, 'date' => $date->toDateString(), 'department' => $u->employee?->department_id]) }}" class="btn btn-sm btn-outline-primary">Review</a>
                            <div class="dropdown d-inline">
                                <button class="btn btn-sm btn-light" data-bs-toggle="dropdown" aria-label="More"><i class="bi bi-three-dots-vertical"></i></button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    @if ($s && ! $s->isOpen())
                                        <li><a class="dropdown-item" href="{{ route($portal.'.corrections.create', $s) }}"><i class="bi bi-pencil-square me-2"></i>Correct work time / numbers</a></li>
                                    @endif
                                    <li><a class="dropdown-item" href="{{ route($portal.'.tracking', ['user' => $u->id]) }}"><i class="bi bi-geo-alt me-2"></i>Live tracking</a></li>
                                    @if ($isAdmin && $s)
                                        @if ($s->isOpen())
                                            <li><form method="POST" action="{{ route('admin.reports.punch-out', $s) }}" data-confirm="Punch out {{ $u->name }} now?">@csrf<button class="dropdown-item text-danger"><i class="bi bi-box-arrow-right me-2"></i>Punch out now</button></form></li>
                                        @else
                                            <li><form method="POST" action="{{ route('admin.reports.send', $s) }}">@csrf<button class="dropdown-item"><i class="bi bi-envelope me-2"></i>E-mail report</button></form></li>
                                        @endif
                                    @endif
                                </ul>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="20"><x-empty-state icon="people" title="No employees to show" /></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="d-flex flex-wrap align-items-center gap-3 card-pad py-2 small">
        <span class="text-muted">Showing {{ $rows->firstItem() ?? 0 }} – {{ $rows->lastItem() ?? 0 }} of {{ $rows->total() }} employees</span>
        <span class="report-legend ms-lg-3">
            <span><i class="bi bi-circle-fill text-success"></i> Working (Live)</span>
            <span><i class="bi bi-circle-fill text-warning"></i> Busy / Idle</span>
            <span><i class="bi bi-circle-fill text-danger"></i> Punch Out</span>
            <span><i class="bi bi-circle-fill text-secondary"></i> Not Logged In</span>
        </span>
        <div class="ms-auto">{{ $rows->onEachSide(1)->links() }}</div>
    </div>
</div>
@endsection
