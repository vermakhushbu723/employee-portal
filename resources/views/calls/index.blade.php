@extends('layouts.app')

@use('App\Support\Duration')

@section('title', 'Call Log')

@section('content')
<div class="d-flex flex-wrap align-items-center gap-2 mb-3">
    <div class="flex-grow-1">
        <h1 class="h4 fw-bold mb-0">Call Log</h1>
        <div class="small text-muted">Calls made from My Leads: talk time (from tapping <b>Call</b> until the call is saved), note and the call recording the employee attached.</div>
    </div>
</div>

<form method="GET" class="card-soft card-pad mb-3 row g-2 align-items-end">
    <div class="col-6 col-md-2"><label class="form-label small">From</label><input type="date" name="from" value="{{ $from->toDateString() }}" class="form-control form-control-sm"></div>
    <div class="col-6 col-md-2"><label class="form-label small">To</label><input type="date" name="to" value="{{ $to->toDateString() }}" class="form-control form-control-sm"></div>
    <div class="col-md-4">
        <label class="form-label small">Employee</label>
        <select name="user" class="form-select form-select-sm">
            <option value="">All {{ $portal === 'admin' ? 'employees' : 'team members' }}</option>
            @foreach ($staff as $s)<option value="{{ $s->id }}" @selected($userId === $s->id)>{{ $s->name }} ({{ $s->employee_code }})</option>@endforeach
        </select>
    </div>
    <div class="col-md-2"><label class="form-check small mb-1"><input type="checkbox" name="recorded" value="1" class="form-check-input" @checked(request()->boolean('recorded'))> With recording only</label></div>
    <div class="col-md-2"><button class="btn btn-primary btn-sm w-100"><i class="bi bi-funnel me-1"></i>Show</button></div>
</form>

<div class="row g-3 mb-3">
    <div class="col-6 col-lg-3"><x-stat-tile icon="telephone-outbound" label="Calls" tone="primary" :value="number_format($totals['calls'])" /></div>
    <div class="col-6 col-lg-3"><x-stat-tile icon="telephone-inbound" label="Connected (talk > 0)" tone="success" :value="number_format($totals['connected'])" /></div>
    <div class="col-6 col-lg-3"><x-stat-tile icon="stopwatch" label="Total Talk Time" tone="info" :value="Duration::hms((int) $totals['talk'])" /></div>
    <div class="col-6 col-lg-3"><x-stat-tile icon="mic" label="Recordings" tone="warning" :value="number_format($totals['recordings'])" /></div>
</div>

@if ($summary->isNotEmpty())
    <div class="card-soft mb-3">
        <div class="card-pad pb-0"><h3 class="section-title mb-0">Per employee</h3></div>
        <div class="table-responsive">
            <table class="table table-soft align-middle mb-0">
                <thead><tr><th>Employee</th><th class="text-center">Calls</th><th class="text-center">Connected</th><th>Talk Time</th><th>Average</th><th class="text-center">Recordings</th></tr></thead>
                <tbody>
                    @foreach ($summary as $r)
                        <tr>
                            <td class="small"><b>{{ $r->user?->name }}</b><div class="small-xs text-muted">{{ $r->user?->employee_code }}</div></td>
                            <td class="text-center">{{ $r->calls }}</td>
                            <td class="text-center">{{ $r->connected }}</td>
                            <td class="small">{{ Duration::hms((int) $r->talk) }}</td>
                            <td class="small">{{ $r->connected ? Duration::hms(intdiv((int) $r->talk, (int) $r->connected)) : '—' }}</td>
                            <td class="text-center">{{ $r->recordings }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif

<div class="card-soft">
    <div class="table-responsive">
        <table class="table table-soft align-middle mb-0">
            <thead><tr><th>Time</th><th>Employee</th><th>Lead</th><th>Talk Time</th><th>Note</th><th style="min-width:260px">Recording</th></tr></thead>
            <tbody>
                @forelse ($calls as $c)
                    <tr>
                        <td class="small text-nowrap"><b>{{ $c->created_at->format('d M') }}</b><div class="text-muted">{{ $c->created_at->format('h:i A') }}</div></td>
                        <td class="small">{{ $c->user?->name }}<div class="small-xs text-muted">{{ $c->user?->employee_code }}</div></td>
                        <td class="small">{{ $c->lead?->name ?? '—' }}<div class="small-xs text-muted">{{ $c->lead?->mobile }}{{ $c->lead?->city ? ' · '.$c->lead->city : '' }}</div></td>
                        <td class="small text-nowrap">
                            @if ($c->status === 'started')<x-badge tone="warning">Not saved</x-badge>
                            @else <b class="{{ $c->duration_seconds ? '' : 'text-muted' }}">{{ Duration::hms((int) $c->duration_seconds) }}</b>@endif
                        </td>
                        <td class="small">{{ $c->body ?: '—' }}</td>
                        <td>
                            @if ($c->file_path)
                                <audio controls preload="none" src="{{ route('lead-files.show', $c) }}" class="call-audio"></audio>
                            @else
                                <span class="small text-muted">—</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6"><x-empty-state icon="telephone" title="No calls in this period" /></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($calls->hasPages())<div class="card-pad pt-2">{{ $calls->links() }}</div>@endif
</div>
@endsection
