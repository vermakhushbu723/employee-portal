@extends('layouts.app')

@use('App\Enums\LeadStatus')

@section('title', 'Lead Details')
@section('back', route('employee.leads.index'))

@section('content')
@php
    $hr = $lead->isHr();
    $digits = preg_replace('/\D/', '', $lead->mobile);
    $next = $lead->meetings->whereIn('status', ['pending', 'upcoming', 'live'])->sortBy('scheduled_at')->first();
    $details = array_filter([
        'Department' => $lead->department?->name,
        'Purpose of Connect' => $lead->purpose ?? $lead->interest,
        $hr ? 'Interested Profile' : 'Interested In' => $lead->interest,
        'Source' => $lead->source,
        'Email' => $lead->email,
        'City' => trim(($lead->city ?? '').($lead->state ? ', '.$lead->state : ''), ', '),
    ]);
    foreach ((array) $lead->extra as $k => $v) {
        if ($v !== null && $v !== '' && ! in_array(strtolower($k), ['name', 'mobile', 'city', 'email', 'state'], true)) { $details[$k] = $v; }
    }
@endphp

<div id="leadsList" data-panel-url="{{ route('employee.leads.panel', 0) }}" class="lead-detail" data-lead="{{ $lead->id }}" data-name="{{ $lead->name }}" data-mobile="{{ $lead->mobile }}" data-email="{{ $lead->email }}">
    <div class="card-soft card-pad mb-3">
        <div class="d-flex gap-3 align-items-start">
            <span class="avatar lg tone-{{ $lead->status->tone() }}">{{ $lead->initials }}</span>
            <div class="flex-grow-1 min-w-0">
                <h2 class="h5 fw-bold mb-0">{{ $lead->name }}</h2>
                <div class="text-muted"><a href="tel:+91{{ $digits }}" data-call="{{ route('employee.leads.call.start', $lead) }}" class="text-decoration-none">{{ $lead->mobile }} <i class="bi bi-telephone-fill text-success"></i></a></div>
                @if ($lead->city)<div class="small text-muted"><i class="bi bi-geo-alt"></i> {{ $lead->city }}</div>@endif
            </div>
            <x-badge :tone="$lead->status->tone()" class="fs-6">{{ $lead->status->label($hr) }}</x-badge>
        </div>
        @if ($lead->corrected_at)
            <div class="corrected-box mt-3"><i class="bi bi-patch-check-fill"></i><div><b>Review & correction by {{ $lead->corrector?->name }} ({{ $lead->corrector?->role->portal() === 'admin' ? 'Admin' : 'Manager' }})</b><div class="small">{{ $lead->correction_note }}</div></div></div>
        @endif
    </div>

    <ul class="nav nav-tabs detail-tabs mb-3" role="tablist">
        @foreach (['details' => 'Details', 'comm' => 'Communication', 'meet' => $hr ? 'Interview' : 'Meeting', 'history' => 'History'] as $k => $l)
            <li class="nav-item"><button class="nav-link {{ $loop->first ? 'active' : '' }}" data-bs-toggle="tab" data-bs-target="#tab-{{ $k }}" type="button">{{ $l }}</button></li>
        @endforeach
    </ul>

    <div class="tab-content">
        {{-- Details --}}
        <div class="tab-pane fade show active" id="tab-details">
            <div class="card-soft card-pad mb-3">
                <dl class="detail-dl">
                    @foreach ($details as $k => $v)<dt>{{ $k }}</dt><dd>{{ $v }}</dd>@endforeach
                    <dt>Current Status</dt>
                    <dd>
                        <form class="lead-status d-inline-block" data-inline-status action="{{ route('employee.leads.status', $lead) }}">
                            <select name="status" class="form-select form-select-sm status-select tone-{{ $lead->status->tone() }}">
                                @foreach (LeadStatus::cases() as $s)<option value="{{ $s->value }}" @selected($lead->status === $s)>{{ $s->label($hr) }}</option>@endforeach
                            </select>
                            <div class="follow-up-pick d-none mt-1">
                                <input type="date" name="follow_up_date" min="{{ today()->toDateString() }}" class="form-control form-control-sm"><button type="submit" class="btn btn-sm btn-primary">Save</button>
                            </div>
                        </form>
                    </dd>
                    <dt>Next {{ $hr ? 'Interview' : 'Meeting' }}</dt>
                    <dd>
                        @if ($next)
                            <i class="bi bi-calendar-event text-purple"></i> {{ $next->scheduled_at->format('d M Y, h:i A') }}
                            @if ($next->isPortal() && in_array($next->status, ['upcoming', 'live'], true))
                                <form method="POST" action="{{ route('employee.meetings.join', $next) }}" class="d-inline">@csrf<button class="btn btn-sm btn-primary ms-2">Join</button></form>
                            @endif
                        @else
                            <a href="{{ route('employee.meetings.index', ['lead' => $lead->id]) }}" class="small">+ Book {{ $hr ? 'interview' : 'meeting' }}</a>
                        @endif
                    </dd>
                </dl>
                <label class="form-label small mt-2">Remarks</label>
                <textarea class="form-control lead-inline" data-inline="remarks" rows="2" maxlength="1000" placeholder="Add a remark…">{{ $lead->remarks }}</textarea>
            </div>

            <div class="detail-actions mb-3">
                <a href="tel:+91{{ $digits }}" data-call="{{ route('employee.leads.call.start', $lead) }}" class="btn btn-success"><i class="bi bi-telephone-fill me-1"></i>Call</a>
                <button type="button" class="btn btn-success" data-wa-open-lead><i class="bi bi-whatsapp me-1"></i>WhatsApp</button>
                <button type="button" class="btn btn-primary" data-email-open><i class="bi bi-envelope-fill me-1"></i>Email</button>
                <button type="button" class="btn btn-outline-danger" data-record><i class="bi bi-record-circle me-1"></i>Record</button>
            </div>
        </div>

        {{-- Communication history --}}
        <div class="tab-pane fade" id="tab-comm">
            <div class="card-soft card-pad mb-3">
                <h3 class="section-title mb-2">Communication History</h3>
                @forelse ($lead->communications as $c)
                    @php [$cl, $ci, $ct] = $c->meta(); @endphp
                    <div class="tl-item">
                        <span class="icon-box sm tone-{{ $c->status === 'failed' ? 'danger' : $ct }}"><i class="bi bi-{{ $ci }}"></i></span>
                        <div class="flex-grow-1 min-w-0">
                            <div class="d-flex justify-content-between"><b class="small">{{ $cl }}{{ $c->status === 'failed' ? ' (not sent)' : '' }}</b><span class="small-xs text-muted">{{ $c->created_at->format('d M Y, h:i A') }}</span></div>
                            @if ($c->channel === 'call')<div class="small text-muted">Talk Time: {{ gmdate('i:s', min(3599, (int) $c->duration_seconds)) }}{{ $c->body ? ' · '.$c->body : '' }}</div>@endif
                            @if ($c->subject)<div class="small fw-600">{{ $c->subject }}</div>@endif
                            @if ($c->body && $c->channel !== 'call')<div class="small">{{ \Illuminate\Support\Str::limit($c->body, 220) }}</div>@endif
                            @if ($c->file_path && $c->channel === 'voice_note')<audio controls preload="none" src="{{ route('lead-files.show', $c) }}" class="w-100 mt-1" style="height:34px"></audio>@endif
                            @if ($c->file_path && $c->channel === 'email')<a href="{{ route('lead-files.show', $c) }}" class="small"><i class="bi bi-paperclip"></i> Attachment</a>@endif
                        </div>
                    </div>
                @empty
                    <x-empty-state icon="chat-dots" title="No communication yet" text="Calls, WhatsApp, e-mails and voice notes appear here." />
                @endforelse
            </div>
        </div>

        {{-- Meetings --}}
        <div class="tab-pane fade" id="tab-meet">
            <div class="card-soft card-pad mb-3">
                <div class="section-head"><h3 class="section-title">{{ $hr ? 'Interviews' : 'Meetings' }}</h3><a href="{{ route('employee.meetings.index', ['lead' => $lead->id]) }}" class="btn btn-sm btn-primary"><i class="bi bi-calendar-plus me-1"></i>Book</a></div>
                @forelse ($lead->meetings as $m)
                    @php $om = $m->outcomeMeta(); [$ml, $mt] = $m->badge(); @endphp
                    <div class="tl-item">
                        <span class="icon-box sm tone-purple"><i class="bi bi-calendar-event-fill"></i></span>
                        <div class="flex-grow-1 min-w-0"><b class="small">{{ $m->title }}</b><div class="small-xs text-muted">{{ $m->scheduled_at->format('d M Y, h:i A') }} · {{ $m->platformLabel() }}{{ $m->durationLabel() ? ' · '.$m->durationLabel() : '' }}</div></div>
                        @if ($om)<x-badge :tone="$om[1]">{{ $om[0] }}</x-badge>@else<x-badge :tone="$mt">{{ $ml }}</x-badge>@endif
                    </div>
                @empty
                    <x-empty-state icon="calendar-event" title="No {{ $hr ? 'interview' : 'meeting' }} yet" />
                @endforelse
            </div>
        </div>

        {{-- Status history --}}
        <div class="tab-pane fade" id="tab-history">
            <div class="card-soft card-pad mb-3">
                @forelse ($lead->activities as $a)
                    <div class="tl-item">
                        <span class="icon-box sm tone-{{ $a->type === 'correction' ? 'warning' : 'secondary' }}"><i class="bi bi-{{ $a->type === 'correction' ? 'patch-check' : ($a->type === 'created' ? 'plus-circle' : 'arrow-repeat') }}"></i></span>
                        <div class="min-w-0">
                            <b class="small">{{ $a->type === 'correction' ? 'Reviewed & corrected' : ($a->type === 'created' ? 'Lead created' : 'Status changed') }}</b>
                            <div class="small">{{ LeadStatus::tryFrom((string) $a->from_status)?->label($hr) }}{{ $a->from_status ? ' → ' : '' }}{{ LeadStatus::tryFrom((string) $a->to_status)?->label($hr) }}</div>
                            <div class="small-xs text-muted">{{ $a->created_at->format('d M Y, h:i A') }} · {{ $a->user?->name }}</div>
                            @if ($a->note)<div class="small-xs">{{ $a->note }}</div>@endif
                        </div>
                    </div>
                @empty
                    <x-empty-state icon="clock-history" title="No history yet" />
                @endforelse
            </div>
        </div>
    </div>

    @if ($converted->isNotEmpty())
        <div class="card-soft mb-3">
            <div class="card-pad pb-2 section-head"><h3 class="section-title">{{ $hr ? 'Converted / Hired Candidates' : 'Converted Leads' }}</h3><a href="{{ route('employee.leads.index', ['tab' => 'converted']) }}" class="small fw-600">View All</a></div>
            <div class="table-responsive">
                <table class="table table-soft mb-0 small">
                    <thead><tr><th>Name</th><th>{{ $hr ? 'Profile' : 'Purpose' }}</th><th>{{ $hr ? 'Joining Date' : 'Converted' }}</th><th>Status</th></tr></thead>
                    <tbody>
                        @foreach ($converted as $cv)
                            <tr><td>{{ $cv->name }}</td><td>{{ $cv->purpose ?? $cv->interest }}</td><td>{{ ($cv->next_action_at ?? $cv->converted_at)?->format('d M Y') ?? '—' }}</td><td><x-badge tone="success">{{ $hr ? 'Hired' : 'Converted' }}</x-badge></td></tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>

@include('employee.leads.partials.comm-modals')
@endsection

@push('scripts')
<script src="{{ asset('js/leads.js') }}?v={{ filemtime(public_path('js/leads.js')) }}"></script>
@endpush
