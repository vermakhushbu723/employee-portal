@extends('layouts.app')

@use('App\Enums\LeadStatus')
@use('App\Support\Duration')

@section('title', 'Review & Correction')

@section('content')
@php
    $tiles = $hr
        ? [['', 'Total Candidates', $total, 'people-fill', 'primary'], ['interested', 'Interested', $counts['interested'] ?? 0, 'hand-thumbs-up-fill', 'success'], ['meeting', 'Interview Scheduled', $counts['meeting'] ?? 0, 'calendar-event-fill', 'purple'], ['follow_up', 'Follow-up', $counts['follow_up'] ?? 0, 'arrow-repeat', 'warning'], ['not_interested', 'Not Interested', $counts['not_interested'] ?? 0, 'hand-thumbs-down-fill', 'danger'], ['converted', 'Hired / Joined', $counts['converted'] ?? 0, 'person-check-fill', 'success'], ['on_hold', 'On Hold', $counts['on_hold'] ?? 0, 'pause-circle-fill', 'warning'], ['rejected', 'Rejected', $counts['rejected'] ?? 0, 'x-circle-fill', 'danger']]
        : [['', 'Total Leads', $total, 'people-fill', 'primary'], ['interested', 'Interested', $counts['interested'] ?? 0, 'hand-thumbs-up-fill', 'success'], ['follow_up', 'Follow-up', $counts['follow_up'] ?? 0, 'arrow-repeat', 'warning'], ['meeting', 'Meeting', $counts['meeting'] ?? 0, 'calendar-event-fill', 'purple'], ['not_interested', 'Not Interested', $counts['not_interested'] ?? 0, 'hand-thumbs-down-fill', 'danger'], ['ready', 'Ready', $counts['ready'] ?? 0, 'check-circle-fill', 'info'], ['converted', 'Converted', $counts['converted'] ?? 0, 'trophy-fill', 'success']];
    $keep = array_filter(['user' => $employee?->id, 'date' => $date?->toDateString(), 'department' => $department?->id, 'city' => $filters['city'] ?: null, 'source' => $filters['source'] ?: null, 'q' => $filters['q'] ?: null]);
    $url = fn ($extra = []) => route($portal.'.review.index', array_filter($keep + $extra, fn ($v) => $v !== null && $v !== ''));
    $chipTones = ['primary', 'info', 'purple', 'success', 'warning', 'danger', 'secondary'];
@endphp

<div class="d-flex flex-wrap align-items-center gap-2 mb-3">
    <div class="flex-grow-1 min-w-0">
        <div class="dropdown d-inline-block">
            <button class="btn btn-link p-0 text-dark text-decoration-none h4 fw-bold mb-0 dropdown-toggle" data-bs-toggle="dropdown">
                {{ $department ? $department->name.($hr ? '' : ' Department') : 'ALL Department' }}
            </button>
            <ul class="dropdown-menu">
                <li><a class="dropdown-item" href="{{ $url(['department' => null]) }}">ALL Department</a></li>
                @foreach ($departments as $d)<li><a class="dropdown-item" href="{{ $url(['department' => $d->id]) }}">{{ $d->name }}</a></li>@endforeach
            </ul>
        </div>
        <div class="small text-muted">
            @if ($employee)
                Reviewing <b>{{ $employee->name }}</b> ({{ $employee->employee_code }})@if ($date) · work of {{ $date->format('D, d M Y') }}@endif ·
                <a href="{{ route($portal.'.review.index', array_filter(['department' => $department?->id])) }}">show everyone</a>
            @else
                {{ $hr ? 'Track candidates, interviews and hiring status' : 'Manage leads, calls, meetings and update status' }}@if ($date) · {{ $date->format('d M Y') }}@endif
            @endif
        </div>
    </div>
    <form method="GET" class="d-flex gap-2 flex-wrap">
        @foreach ($keep as $k => $v)@if (! in_array($k, ['q', 'date'], true))<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endif @endforeach
        <div class="input-icon"><i class="bi bi-calendar3"></i><input type="date" name="date" value="{{ $date?->toDateString() }}" class="form-control form-control-sm" onchange="this.form.submit()" aria-label="Work date"></div>
        <div class="input-icon"><i class="bi bi-search"></i><input type="search" name="q" value="{{ $filters['q'] }}" class="form-control form-control-sm" placeholder="Search by name, mobile, city…"></div>
    </form>
</div>

<div class="review-tiles mb-3">
    @foreach ($tiles as [$key, $label, $n, $icon, $tone])
        <a href="{{ $url(['status' => $key ?: null]) }}" @class(['review-tile', "tone-{$tone}", 'active' => ($status?->value ?? '') === $key])>
            <span class="stat-icon"><i class="bi bi-{{ $icon }}"></i></span>
            <span class="min-w-0"><span class="stat-label d-block">{{ $label }}</span><span class="stat-value">{{ $n }}</span></span>
        </a>
    @endforeach
</div>

<form method="GET" class="d-flex flex-wrap gap-2 mb-2">
    @foreach ($keep as $k => $v)@if (! in_array($k, ['city', 'source'], true))<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endif @endforeach
    <select name="status" class="form-select form-select-sm w-auto" onchange="this.form.submit()">
        <option value="">All Status</option>
        @foreach (LeadStatus::cases() as $s)<option value="{{ $s->value }}" @selected($status === $s)>{{ $s->label($hr) }}</option>@endforeach
    </select>
    <select name="city" class="form-select form-select-sm w-auto" onchange="this.form.submit()">
        <option value="">All Cities</option>
        @foreach ($cities as $c)<option @selected($filters['city'] === $c)>{{ $c }}</option>@endforeach
    </select>
    <select name="source" class="form-select form-select-sm w-auto" onchange="this.form.submit()">
        <option value="">All Sources</option>
        @foreach ($sources as $s)<option @selected($filters['source'] === $s)>{{ $s }}</option>@endforeach
    </select>
    <span class="small text-muted align-self-center ms-auto"><i class="bi bi-info-circle"></i> Changes save at once. Moving a lead off <b>Interested / Converted</b> lowers the employee's score and tags the lead "Reviewed & corrected".</span>
</form>

<div class="dept-chips mb-3">
    <a href="{{ $url(['department' => null]) }}" @class(['dept-chip tone-primary', 'active' => ! $department])><i class="bi bi-grid-3x3-gap-fill"></i> All</a>
    @foreach ($departments as $i => $d)
        <a href="{{ $url(['department' => $d->id]) }}" @class(['dept-chip', 'tone-'.$chipTones[$i % count($chipTones)], 'active' => $department?->id === $d->id])><i class="bi bi-{{ $d->icon() }}"></i> {{ $d->name }}</a>
    @endforeach
</div>

<div class="card-soft" id="reviewTable" data-update-url="{{ route($portal.'.review.update', 0) }}">
    <div class="table-responsive">
        <table class="table table-soft align-middle review-table mb-0">
            <thead>
                <tr>
                    <th>#</th>
                    <th>{{ $hr ? 'Candidate Name' : 'Name & Mobile' }}</th>
                    <th>City</th>
                    <th>Department</th>
                    <th style="min-width:170px">Purpose of Connect</th>
                    <th style="min-width:150px">Status</th>
                    <th style="min-width:190px">{{ $hr ? 'Interview / Next Meeting' : 'Next Action / Meeting' }}</th>
                    <th>Last Msg</th>
                    <th>Talk Time</th>
                    <th>Mic</th>
                    <th style="min-width:180px">{{ $hr ? 'Remark / Hiring Status' : 'Remark' }}</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($leads as $i => $lead)
                    @php
                        $last = $lead->communications->first(fn ($c) => in_array($c->channel, ['whatsapp', 'email', 'call'], true));
                        $lhr = $lead->isHr() || $hr;
                        $phone = preg_replace('/\D/', '', $lead->mobile);
                        $wa = 'https://wa.me/'.(strlen($phone) === 10 ? '91'.$phone : $phone);
                    @endphp
                    <tr data-lead="{{ $lead->id }}" data-status="{{ $lead->status->value }}" data-name="{{ $lead->name }}">
                        <td class="text-muted small">{{ $leads->firstItem() + $i }}</td>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <span class="avatar tone-{{ $lead->status->tone() }}" style="width:34px;height:34px;font-size:.72rem">{{ $lead->initials }}</span>
                                <div class="min-w-0">
                                    <div class="fw-600 text-nowrap">{{ $lead->name }}</div>
                                    <div class="small-xs text-muted">{{ $lead->mobile }}</div>
                                    @if (! $employee && $lead->assignee)<div class="small-xs text-muted"><i class="bi bi-person"></i> {{ $lead->assignee->name }}</div>@endif
                                    @if ($lead->corrected_at)<span class="corrected-tag" title="{{ $lead->correction_note }}"><i class="bi bi-patch-check-fill"></i> Corrected by {{ $lead->corrector?->name }}</span>@endif
                                </div>
                            </div>
                        </td>
                        <td class="small">{{ $lead->city ?: '—' }}</td>
                        <td class="small text-nowrap">@if ($lead->department)<span class="dept-chip sm tone-info"><i class="bi bi-{{ $lead->department->icon() }}"></i> {{ $lead->department->name }}</span>@else — @endif</td>
                        <td><input class="form-control form-control-sm rv-input" data-field="purpose" value="{{ $lead->purpose ?? $lead->interest }}" maxlength="150" placeholder="Purpose…"></td>
                        <td>
                            <select class="form-select form-select-sm rv-input rv-status tone-{{ $lead->status->tone() }}" data-field="status">
                                @foreach (LeadStatus::cases() as $s)<option value="{{ $s->value }}" @selected($lead->status === $s)>{{ $s->label($lhr) }}</option>@endforeach
                            </select>
                        </td>
                        <td>
                            <div class="d-flex gap-1">
                                <select class="form-select form-select-sm rv-input" data-field="next_action_type" style="width:96px">
                                    <option value="">—</option>
                                    @foreach (['call' => 'Call Back', 'meeting' => $lhr ? 'Interview' : 'Meeting', 'visit' => 'Site Visit', 'document' => 'Document'] as $k => $l)<option value="{{ $k }}" @selected($lead->next_action_type === $k)>{{ $l }}</option>@endforeach
                                </select>
                                <input type="datetime-local" class="form-control form-control-sm rv-input" data-field="next_action_at" value="{{ $lead->next_action_at?->format('Y-m-d\TH:i') }}">
                            </div>
                        </td>
                        <td class="small text-nowrap">
                            @if ($last)
                                @php [$cl, $ci, $ct] = $last->meta(); @endphp
                                <span class="text-{{ $ct }}" title="{{ \Illuminate\Support\Str::limit($last->body, 160) }}"><i class="bi bi-{{ $ci }}"></i> {{ $last->created_at->format('h:i A') }}</span>
                            @else <span class="text-muted">—</span> @endif
                        </td>
                        <td class="small text-nowrap"><i class="bi bi-clock text-muted"></i> {{ $lead->talk_seconds ? gmdate($lead->talk_seconds >= 3600 ? 'H:i:s' : 'i:s', (int) $lead->talk_seconds) : '00:00' }}</td>
                        <td>@if ($lead->voice_notes)<i class="bi bi-mic-fill text-success" title="{{ $lead->voice_notes }} voice note(s)"></i>@else<i class="bi bi-mic text-muted"></i>@endif</td>
                        <td><input class="form-control form-control-sm rv-input" data-field="remarks" value="{{ $lead->remarks }}" maxlength="1000" placeholder="Remark…"></td>
                        <td class="text-nowrap">
                            <a class="act-btn tone-success" href="tel:{{ $lead->mobile }}" title="Call"><i class="bi bi-telephone-fill"></i></a>
                            <a class="act-btn tone-success" href="{{ $wa }}" target="_blank" rel="noopener" title="WhatsApp"><i class="bi bi-whatsapp"></i></a>
                            @if ($lead->email)<a class="act-btn tone-primary" href="mailto:{{ $lead->email }}" title="Email"><i class="bi bi-envelope-fill"></i></a>@endif
                            <a class="act-btn tone-purple" href="{{ route($portal.'.meetings.index', ['for_lead' => $lead->id]) }}" title="Meeting"><i class="bi bi-calendar-plus-fill"></i></a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="12"><x-empty-state icon="clipboard-check" title="Nothing to review" text="No leads match these filters for this day." /></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="d-flex align-items-center card-pad py-2 small">
        <span class="text-muted">Showing {{ $leads->firstItem() ?? 0 }} – {{ $leads->lastItem() ?? 0 }} of {{ $leads->total() }}</span>
        <div class="ms-auto">{{ $leads->onEachSide(1)->links() }}</div>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/review.js') }}?v={{ filemtime(public_path('js/review.js')) }}"></script>
@endpush
