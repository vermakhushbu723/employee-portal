@extends('layouts.app')

@section('title', 'Team Performance')

@section('content')
@php
    $route = $portal.'.team.performance';
    $keep = array_filter(['from' => $from->toDateString(), 'to' => $to->toDateString(), 'department' => $dept, 'q' => $q ?: null]);
    $roleTone = ['hr' => 'purple', 'manager' => 'primary', 'team_leader' => 'info', 'employee' => 'success'];
    $roleShort = ['hr' => 'HR', 'manager' => 'Manager', 'team_leader' => 'TL', 'employee' => 'Employee'];
    $pct = fn ($v) => $v === null ? '—' : $v.'%';
    $barTone = fn ($v) => $v === null ? 'secondary' : ($v >= 70 ? 'success' : ($v >= 50 ? 'warning' : 'danger'));
@endphp

<div class="d-flex flex-wrap align-items-center gap-2 mb-3">
    <div class="flex-grow-1">
        <h1 class="h4 fw-bold mb-0">Team Performance</h1>
        <div class="small text-muted">{{ $portal === 'admin' ? 'Everyone, in reporting order (HR → Manager → TL → Employee)' : 'Your team (everyone reporting to you, directly or through your TLs)' }} · {{ $from->format('d M Y') }} – {{ $to->format('d M Y') }}</div>
    </div>
</div>

<form method="GET" class="card-soft card-pad mb-3 row g-2 align-items-end">
    <input type="hidden" name="tab" value="{{ $tab }}">
    <div class="col-6 col-md-2"><label class="form-label small">From</label><input type="date" name="from" value="{{ $from->toDateString() }}" class="form-control form-control-sm"></div>
    <div class="col-6 col-md-2"><label class="form-label small">To</label><input type="date" name="to" value="{{ $to->toDateString() }}" class="form-control form-control-sm"></div>
    <div class="col-md-3"><label class="form-label small">Department</label>
        <select name="department" class="form-select form-select-sm"><option value="">All departments</option>@foreach ($departments as $d)<option value="{{ $d->id }}" @selected($dept === $d->id)>{{ $d->name }}</option>@endforeach</select></div>
    <div class="col-md-3"><label class="form-label small">Search</label><input type="search" name="q" value="{{ $q }}" class="form-control form-control-sm" placeholder="Name or employee ID"></div>
    <div class="col-md-2"><button class="btn btn-primary btn-sm w-100"><i class="bi bi-funnel me-1"></i>Apply</button></div>
</form>

<div class="tp-kpis mb-3">
    @foreach ([
        ['person-badge', 'Managers / HR', $kpi['managers'], 'purple'], ['people', 'Team Leaders', $kpi['tls'], 'info'], ['person', 'Employees', $kpi['employees'], 'success'],
        ['clipboard-data', 'Assigned Leads', number_format($kpi['assigned']), 'primary'], ['check2-circle', 'Completed', number_format($kpi['completed']).($kpi['assigned'] ? ' ('.round($kpi['completed'] / $kpi['assigned'] * 100).'%)' : ''), 'success'],
        ['hourglass-split', 'Pending', number_format($kpi['pending']), 'danger'], ['telephone', 'Calls', number_format($kpi['calls']), 'info'], ['calendar-event', 'Meetings', number_format($kpi['meetings']), 'purple'],
        ['trophy', 'Converted / Hired', number_format($kpi['converted']), 'success'], ['calendar-week', 'Weekly Attendance', $kpi['week_att'].'%', 'warning'], ['calendar3', 'Monthly Attendance', $kpi['month_att'].'%', 'warning'], ['speedometer2', 'Avg Work Score (month)', $kpi['month_score'].'%', 'primary'],
    ] as [$ic, $lb, $val, $tn])
        <div class="tp-kpi tone-{{ $tn }}"><span class="icon-box sm tone-{{ $tn }}"><i class="bi bi-{{ $ic }}"></i></span><div><div class="small-xs text-muted">{{ $lb }}</div><b>{{ $val }}</b></div></div>
    @endforeach
</div>

<nav class="seg-tabs mb-3">
    @foreach (['hierarchy' => 'Hierarchy View', 'work' => 'Assigned Work', 'leads' => 'Lead Progress & Review'] as $key => $label)
        <a href="{{ route($route, $keep + ['tab' => $key]) }}" @class(['active' => $tab === $key])>{{ $label }}</a>
    @endforeach
</nav>

@if ($tab === 'hierarchy')
<div class="tp-layout">
    <div class="card-soft min-w-0">
        <div class="d-flex align-items-center gap-2 card-pad py-2">
            <span class="small text-muted flex-grow-1">Managers / TLs show their <b>whole team's</b> totals. Click a name for details.</span>
            <button type="button" class="btn btn-sm btn-light" data-tp-expand="1"><i class="bi bi-arrows-expand"></i> Expand all</button>
            <button type="button" class="btn btn-sm btn-light" data-tp-expand="0"><i class="bi bi-arrows-collapse"></i> Collapse</button>
        </div>
        <div class="table-responsive">
            <table class="table table-soft align-middle mb-0 tp-table">
                <thead><tr>
                    <th>Name / ID</th><th>Role</th><th class="text-center">Team</th><th class="text-center">Assigned</th><th class="text-center">Done</th><th class="text-center">Pending</th>
                    <th class="text-center">Created</th><th class="text-center">Calls</th><th class="text-center">Follow-ups</th><th class="text-center">Meetings</th><th class="text-center">Converted</th>
                    <th style="min-width:120px">Work Completion</th><th>Today</th><th class="text-center">Weekly Att.</th><th class="text-center">Monthly Att.</th><th class="text-center">Score (M)</th><th>Status</th><th></th>
                </tr></thead>
                <tbody>
                @forelse ($rows as $r)
                    @php
                        $u = $r['user']; $m = $r['has_children'] ? $r['team'] : $r['own']; $o = $r['own'];
                        $role = $u->role->value; [$stLabel, $stTone] = $r['status'];
                        $digits = preg_replace('/\D/', '', (string) $u->mobile);
                        $person = [
                            'name' => $u->name, 'code' => $u->employee_code, 'role' => $roleShort[$role] ?? $u->roleLabel(), 'mobile' => $u->mobile, 'email' => $u->email,
                            'dept' => $u->employee?->department?->name, 'designation' => $u->employee?->designation, 'reports' => $u->employee?->manager?->name,
                            'members' => $r['members'], 'own' => $o, 'team' => $r['has_children'] ? $r['team'] : null, 'status' => $stLabel, 'tone' => $stTone,
                        ];
                    @endphp
                    <tr data-tp-row="{{ $u->id }}" data-tp-parent="{{ $r['parent'] }}" data-depth="{{ $r['depth'] }}" @class(['tp-lead' => $r['has_children']])>
                        <td class="text-nowrap">
                            <div class="d-flex align-items-center gap-2" style="padding-left: {{ $r['depth'] * 1.25 }}rem">
                                @if ($r['has_children'])<button type="button" class="tp-toggle" data-tp-toggle="{{ $u->id }}" aria-label="Show / hide team of {{ $u->name }}"><i class="bi bi-chevron-down"></i></button>@else<span class="tp-toggle-gap"></span>@endif
                                <span class="avatar sm tone-{{ $roleTone[$role] ?? 'secondary' }}">{{ $u->initials ?? strtoupper(substr($u->name, 0, 2)) }}</span>
                                <button type="button" class="tp-name" data-tp-person='@json($person)'><b>{{ $u->name }}</b><small>{{ $u->employee_code }}</small></button>
                            </div>
                        </td>
                        <td><x-badge :tone="$roleTone[$role] ?? 'secondary'">{{ $roleShort[$role] ?? $u->roleLabel() }}</x-badge></td>
                        <td class="text-center small">{{ $r['members'] ?: '—' }}</td>
                        <td class="text-center">{{ $m['assigned'] }}</td>
                        <td class="text-center text-success fw-600">{{ $m['completed'] }}</td>
                        <td class="text-center text-danger">{{ $m['pending'] }}</td>
                        <td class="text-center">{{ $m['created'] }}</td>
                        <td class="text-center">{{ $m['calls'] }}</td>
                        <td class="text-center">{{ $m['follow_ups'] }}</td>
                        <td class="text-center">{{ $m['meetings'] }}</td>
                        <td class="text-center fw-600">{{ $m['converted'] }}</td>
                        <td><div class="d-flex align-items-center gap-2"><x-progress class="flex-grow-1" :value="$m['completion'] ?? 0" :tone="$barTone($m['completion'])" /><span class="small fw-bold">{{ $pct($m['completion']) }}</span></div></td>
                        <td class="small text-nowrap">
                            @if ($r['has_children'])<span class="text-muted">{{ $m['present'] }}/{{ $m['people'] }} present</span>
                            @elseif ($o['today'][0] === 'present')<span class="text-success"><i class="bi bi-check-circle-fill"></i> {{ $o['today'][2] ? 'Late' : 'Present' }}</span><div class="small-xs text-muted">{{ $o['today'][1] }}</div>
                            @elseif ($o['today'][0] === 'leave')<span class="text-info"><i class="bi bi-calendar2-x"></i> Leave</span>
                            @else<span class="text-danger"><i class="bi bi-x-circle-fill"></i> Absent</span>@endif
                        </td>
                        <td class="text-center small">{{ $m['week_att'] }}%</td>
                        <td class="text-center small">{{ $m['month_att'] }}%</td>
                        <td class="text-center small">{{ $pct($m['month_score']) }}</td>
                        <td><x-badge :tone="$stTone">{{ $stLabel }}</x-badge></td>
                        <td class="text-nowrap">
                            @if ($digits)<a href="tel:{{ $digits }}" class="btn btn-sm btn-light" title="Call {{ $u->name }}"><i class="bi bi-telephone"></i></a>
                                <a href="https://wa.me/{{ strlen($digits) === 10 ? '91'.$digits : $digits }}" class="btn btn-sm btn-light text-success" title="WhatsApp {{ $u->name }}"><i class="bi bi-whatsapp"></i></a>@endif
                            @if ($u->email)@php $mailCtx = ['to' => $u->email, 'name' => $u->name, 'template' => 'Follow-up']; @endphp<button type="button" class="btn btn-sm btn-light text-primary" data-email-compose='@json($mailCtx)' title="E-mail {{ $u->name }}"><i class="bi bi-envelope"></i></button>@endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="18"><x-empty-state icon="diagram-3" title="Nobody here" text="Set each person's Reporting Manager in Employees → Edit to build the hierarchy." /></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Details of the clicked person --}}
    <aside class="card-soft card-pad tp-side" id="tpSide">
        <div class="text-muted small text-center py-4">Click a name to see the details.</div>
    </aside>
</div>
@endif

@if ($tab === 'work')
<div class="card-soft">
    <div class="card-pad pb-2"><h3 class="section-title mb-0">Assigned Work (Admin direct + Manager / TL)</h3><div class="small text-muted">Work given in this period, who it went through and how far it is done (calls 70% + interested target 30%).</div></div>
    <div class="table-responsive">
        <table class="table table-soft align-middle mb-0">
            <thead><tr><th>Hierarchy</th><th>Work</th><th>Given by</th><th>Assigned</th><th class="text-center">Records</th><th class="text-center">Called</th><th class="text-center">Interested / Target</th><th style="min-width:130px">Completion</th><th>Status</th></tr></thead>
            <tbody>
            @forelse ($work as $w)
                @php $t = $w['task']; $p = $w['p']; @endphp
                <tr>
                    <td class="small">
                        <div class="tp-chain">@foreach ($w['chain'] as $c)<span>{{ $c->name }} <small>({{ $roleShort[$c->role->value] ?? $c->roleLabel() }})</small></span><i class="bi bi-chevron-right"></i>@endforeach<b>{{ $t->user?->name }}</b> <small class="text-muted">{{ $t->user?->employee_code }}</small></div>
                    </td>
                    <td class="small"><b>{{ $t->title }}</b><div class="small-xs text-muted">{{ $t->type === 'calling' ? 'Calling task' : 'Create leads' }}@if ($t->message) · {{ \Illuminate\Support\Str::limit($t->message, 50) }}@endif</div></td>
                    <td class="small">@if ($w['direct'])<x-badge tone="primary">Admin (direct)</x-badge>@else{{ $t->assigner?->name }} <small class="text-muted">({{ $roleShort[$t->assigner?->role->value] ?? '' }})</small>@endif</td>
                    <td class="small text-nowrap">{{ $t->assigned_at?->format('d M Y') }}</td>
                    @if ($t->type === 'calling')
                        <td class="text-center">{{ $p['total'] }}</td>
                        <td class="text-center">{{ $p['called'] }}</td>
                        <td class="text-center">{{ $p['interested'] }} / {{ $p['target'] ?: '—' }}</td>
                    @else
                        <td class="text-center">{{ $t->lead_target }}</td><td class="text-center">—</td><td class="text-center">{{ $p['created'] }} created</td>
                    @endif
                    <td><div class="d-flex align-items-center gap-2"><x-progress class="flex-grow-1" :value="$w['pct']" :tone="$barTone($w['pct'])" /><span class="small fw-bold">{{ $w['pct'] }}%</span></div></td>
                    <td>@if ($p['done'])<x-badge tone="success">Completed</x-badge>@elseif ($w['pct'] > 0)<x-badge tone="info">In Progress</x-badge>@else<x-badge tone="warning">Pending</x-badge>@endif</td>
                </tr>
            @empty
                <tr><td colspan="9"><x-empty-state icon="clipboard" title="No work assigned in this period" /></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endif

@if ($tab === 'leads')
<div class="card-soft">
    <div class="card-pad pb-2 d-flex flex-wrap align-items-center gap-2">
        <div class="flex-grow-1"><h3 class="section-title mb-0">Lead Progress & Review</h3><div class="small text-muted">Employee → TL → Manager: a lead counts as reviewed when a TL / manager corrected it or pressed <b>✓ Reviewed</b>.</div></div>
        <form method="GET" class="d-flex gap-2">@foreach ($keep + ['tab' => 'leads'] as $k => $v)<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endforeach
            <select name="status" class="form-select form-select-sm" onchange="this.form.submit()"><option value="">All status</option>@foreach ($statuses as $s)<option value="{{ $s->value }}" @selected(request('status') === $s->value)>{{ $s->label() }}</option>@endforeach</select>
        </form>
    </div>
    <div class="table-responsive">
        <table class="table table-soft align-middle mb-0">
            <thead><tr><th>Lead</th><th>Created by</th><th>Current owner</th><th>Status</th><th>TL Review</th><th>Manager Review</th><th>Next action / Meeting</th><th>Updated</th><th></th></tr></thead>
            <tbody>
            @forelse ($leads as $l)
                @php $owner = $l->assignee; $tl = $owner?->employee?->manager; $nextM = $l->meetings->first(); @endphp
                <tr>
                    <td class="small"><b>{{ $l->name }}</b><div class="small-xs text-muted">{{ $l->mobile }}{{ $l->purpose ? ' · '.$l->purpose : '' }}</div></td>
                    <td class="small">{{ $l->creator?->name ?? '—' }}</td>
                    <td class="small">{{ $owner?->name ?? '—' }}@if ($tl)<div class="small-xs text-muted">{{ $roleShort[$tl->role->value] ?? '' }}: {{ $tl->name }}</div>@endif</td>
                    <td><x-badge :tone="$l->status->tone()">{{ $l->status->label($l->isHr()) }}</x-badge></td>
                    <td>@if ($l->tl_review)<x-badge tone="success"><i class="bi bi-check2"></i> Done</x-badge>@else<x-badge tone="warning">Pending</x-badge>@endif</td>
                    <td>@if ($l->manager_review)<x-badge tone="success"><i class="bi bi-check2"></i> Done</x-badge>@else<x-badge tone="warning">Pending</x-badge>@endif</td>
                    <td class="small">@if ($nextM)<i class="bi bi-calendar-event text-purple"></i> {{ $nextM->scheduled_at->format('d M, h:i A') }}@elseif ($l->next_action_at)<i class="bi bi-telephone"></i> {{ \App\Models\Lead::NEXT_ACTIONS[$l->next_action_type][0] ?? 'Next' }} · {{ $l->next_action_at->format('d M, h:i A') }}@else — @endif</td>
                    <td class="small text-nowrap">{{ $l->updated_at->format('d M Y') }}</td>
                    <td class="text-nowrap">
                        <form method="POST" action="{{ route($portal.'.team.reviewed', $l) }}" class="d-inline">@csrf<button class="btn btn-sm btn-outline-success" title="Mark reviewed"><i class="bi bi-check2-square"></i> Reviewed</button></form>
                        <a href="{{ route($portal.'.review.index', ['q' => $l->mobile]) }}" class="btn btn-sm btn-light" title="Open in Review & Correction"><i class="bi bi-pencil-square"></i></a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="9"><x-empty-state icon="people" title="No leads in this period" /></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if ($leads && $leads->hasPages())<div class="card-pad pt-2">{{ $leads->links() }}</div>@endif
</div>
@endif

@include('partials.email-compose')

@push('scripts')
<script>
(() => {
    const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    // Expand / collapse a manager's or TL's team
    const kids = (id) => [...document.querySelectorAll(`[data-tp-parent="${id}"]`)];
    const setOpen = (id, open) => {
        document.querySelector(`[data-tp-toggle="${id}"]`)?.classList.toggle('closed', !open);
        kids(id).forEach((r) => { r.classList.toggle('d-none', !open); if (!open) setOpen(r.dataset.tpRow, false); else if (!r.querySelector('.tp-toggle.closed')) setOpen(r.dataset.tpRow, true); });
    };
    document.addEventListener('click', (e) => {
        const t = e.target.closest('[data-tp-toggle]');
        if (t) setOpen(t.dataset.tpToggle, t.classList.contains('closed'));
        const all = e.target.closest('[data-tp-expand]');
        if (all) document.querySelectorAll('tr[data-depth="0"] [data-tp-toggle], tr[data-tp-row] [data-tp-toggle]').forEach((b) => setOpen(b.dataset.tpToggle, all.dataset.tpExpand === '1'));
        const p = e.target.closest('[data-tp-person]');
        if (p) showPerson(JSON.parse(p.dataset.tpPerson));
    });
    // Details panel
    const side = document.getElementById('tpSide');
    const stat = (label, own, team) => `<tr><td>${label}</td><td class="text-end fw-600">${own ?? '—'}</td>${team ? `<td class="text-end text-muted">${team}</td>` : ''}</tr>`;
    const showPerson = (p) => {
        if (!side) return;
        const o = p.own, t = p.team;
        const today = o.today[0] === 'present' ? `<span class="text-success">Present ${esc(o.today[1] || '')}</span>` : (o.today[0] === 'leave' ? '<span class="text-info">On leave</span>' : '<span class="text-danger">Absent</span>');
        side.innerHTML = `
            <div class="d-flex align-items-center gap-2 mb-2"><div class="flex-grow-1 min-w-0"><b>${esc(p.name)}</b> <span class="badge-soft tone-${esc(p.tone)}">${esc(p.status)}</span><div class="small text-muted">${esc(p.code)} · ${esc(p.role)}</div></div><button type="button" class="btn-close" aria-label="Close" onclick="this.closest('aside').innerHTML='<div class=&quot;text-muted small text-center py-4&quot;>Click a name to see the details.</div>'"></button></div>
            <div class="small mb-2">${p.mobile ? `<i class="bi bi-telephone"></i> ${esc(p.mobile)} ` : ''}${p.dept ? `· <i class="bi bi-building"></i> ${esc(p.dept)}` : ''}</div>
            <table class="table table-sm small mb-2"><tbody>
                <tr><td>Designation</td><td class="text-end" colspan="2">${esc(p.designation || '—')}</td></tr>
                <tr><td>Reports to</td><td class="text-end" colspan="2">${esc(p.reports || '—')}</td></tr>
                ${t ? `<tr><td>Team members</td><td class="text-end" colspan="2">${p.members}</td></tr>` : ''}
            </tbody></table>
            <table class="table table-sm small mb-2"><thead><tr><th></th><th class="text-end">Own</th>${t ? '<th class="text-end">Team</th>' : ''}</tr></thead><tbody>
                ${stat('Assigned leads', o.assigned, t && t.assigned)}${stat('Completed', o.completed, t && t.completed)}${stat('Pending', o.pending, t && t.pending)}
                ${stat('Created leads', o.created, t && t.created)}${stat('Calls', o.calls, t && t.calls)}${stat('Follow-ups', o.follow_ups, t && t.follow_ups)}
                ${stat('Meetings', o.meetings, t && t.meetings)}${stat('Converted / Hired', o.converted, t && t.converted)}
                ${stat('Work completion', o.completion === null ? '—' : o.completion + '%', t && (t.completion === null ? '—' : t.completion + '%'))}
            </tbody></table>
            <div class="fw-bold small mb-1">Attendance summary</div>
            <div class="tp-att">
                <div><small>Today</small>${today}</div>
                <div><small>Weekly avg</small><b>${o.week_att}%</b></div>
                <div><small>Monthly avg</small><b>${o.month_att}%</b></div>
                <div><small>Work score (M)</small><b>${o.month_score === null ? '—' : o.month_score + '%'}</b></div>
            </div>`;
        side.scrollIntoView({ block: 'nearest' });
    };
    const first = document.querySelector('[data-tp-person]');
    if (first && side && innerWidth >= 1200) showPerson(JSON.parse(first.dataset.tpPerson));
})();
</script>
@endpush
@endsection
