@extends('layouts.app')

@section('title', 'Meetings')

@section('content')
<div class="d-flex flex-wrap align-items-center gap-2 mb-3">
    <div class="flex-grow-1">
        <h1 class="h5 fw-bold mb-0">Meetings</h1>
        <div class="small text-muted">Customer meetings of {{ $portal === 'admin' ? 'all employees' : 'your team' }}. A meeting you give stays <b>Pending</b> until the employee accepts it. Meetings never close by themselves — <b>Review</b> closes, re-opens or reschedules them.</div>
    </div>
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#assignModal"><i class="bi bi-plus-lg me-1"></i>Give Meeting</button>
</div>

@if (session('wa_links'))
    <div class="alert alert-warning small d-flex flex-wrap align-items-center gap-2"><i class="bi bi-whatsapp"></i> Your WhatsApp is not linked in the portal — tap to send:
        @foreach (session('wa_links') as $w)<a href="{{ $w['link'] }}" target="_blank" rel="noopener" class="btn btn-sm btn-success">+{{ $w['to'] }}</a>@endforeach
    </div>
@endif
<nav class="seg-tabs mb-3">
    @foreach (['today' => 'Today', 'review' => 'Needs Review', 'upcoming' => 'Upcoming', 'pending' => 'Pending Acceptance', 'past' => 'Closed'] as $key => $label)
        <a href="{{ route($portal.'.meetings.index', ['tab' => $key]) }}" @class(['active' => $tab === $key])>{{ $label }} ({{ $counts[$key] }})</a>
    @endforeach
</nav>

<div class="card-soft" data-live-region="status">
    <div class="table-responsive">
        <table class="table table-soft align-middle mb-0">
            <thead><tr><th>Date & Time</th><th>Customer / Lead</th><th>Title</th><th>Employee</th><th>Managers</th><th>Platform</th><th>Status / Result</th><th></th></tr></thead>
            <tbody>
                @forelse ($meetings as $m)
                    @php [$label, $tone] = $m->badge(); $seeContact = $people->canSeeContact(auth()->user(), $m); $om = $m->outcomeMeta(); @endphp
                    <tr>
                        <td class="text-nowrap"><b>{{ $m->scheduled_at->format('d M Y') }}</b><div class="small text-muted">{{ $m->timeRange() }}</div></td>
                        <td>
                            {{ $m->customer_name }}
                            <div class="small-xs text-muted">{{ $seeContact ? $m->customer_mobile : \App\Services\MeetingPeopleService::mask($m->customer_mobile) }} {{ $m->city ? '· '.$m->city : '' }}</div>
                            @if ($m->lead)
                                <div class="small-xs"><x-badge :tone="$m->lead->status->tone()">{{ $m->lead->status->label($m->lead->isHr()) }}</x-badge> {{ $m->lead->purpose ?? $m->lead->interest }}</div>
                                @if ($m->lead->remarks)<div class="small-xs text-muted text-truncate" style="max-width:220px">{{ $m->lead->remarks }}</div>@endif
                                @if ($seeContact && $m->lead->email)<div class="small-xs text-muted">{{ $m->lead->email }}</div>@endif
                            @endif
                            @unless ($seeContact)<div class="small-xs text-muted"><i class="bi bi-lock"></i> Mobile / e-mail: primary manager only</div>@endunless
                        </td>
                        <td class="small">{{ $m->title }}</td>
                        <td class="small text-nowrap">{{ $m->user->name }}<div class="small-xs text-muted">{{ $m->user->employee_code }}</div></td>
                        <td class="small">@forelse ($m->participants as $p)<div class="text-nowrap">{{ $p->name }}@if ($p->pivot->role === 'primary') <x-badge tone="primary">Primary</x-badge>@endif</div>@empty <span class="text-muted">—</span> @endforelse</td>
                        <td class="small text-nowrap"><i class="bi bi-{{ $m->platformIcon() }}"></i> {{ $m->platformLabel() }}</td>
                        <td>
                            <x-badge :tone="$tone">{{ $label }}</x-badge>
                            @if ($m->status === 'live' && $m->started_at && $m->hasPeopleInRoom())<div class="small-xs text-muted">since {{ $m->started_at->format('h:i A') }}</div>@endif
                            @if ($om)<div><x-badge :tone="$om[1]"><i class="bi bi-{{ $om[2] }}"></i> {{ $om[0] }}</x-badge></div>@endif
                            @if ($tm = $m->tagMeta())<div><x-badge :tone="$tm[1]"><i class="bi bi-{{ $tm[2] }}"></i> {{ $tm[0] }}</x-badge></div>@endif
                            @if ($m->durationLabel() || $m->outcome_note)<div class="small-xs text-muted">{{ $m->durationLabel() ? 'Connected '.$m->durationLabel() : '' }}{{ $m->outcome_note ? ' · '.$m->outcome_note : '' }}</div>@endif
                            @if ($m->reviewer)<div class="small-xs text-muted">Reviewed by {{ $m->reviewer->name }}</div>@endif
                            @if ($m->notified)<div class="small-xs text-muted"><i class="bi bi-send"></i> {{ collect($m->notified)->where('ok', true)->count() }}/{{ count($m->notified) }} invites</div>@endif
                        </td>
                        <td class="text-end text-nowrap">
                            @if (in_array($m->status, ['upcoming', 'live'], true) && ($m->isPortal() || $m->link))
                                @if ($m->isPortal())
                                    <a href="{{ route($portal.'.meetings.room', $m) }}" class="btn btn-sm btn-primary"><i class="bi bi-camera-video"></i> Join</a>
                                    <button type="button" class="btn btn-sm btn-light" data-copy="{{ $m->joinLink() }}" title="Copy customer join link"><i class="bi bi-link-45deg"></i></button>
                                @else
                                    <a href="{{ $m->link }}" target="_blank" rel="noopener" class="btn btn-sm btn-primary">Join</a>
                                @endif
                            @endif
                            @if (in_array($m->status, ['upcoming', 'live', 'completed'], true))
                                @php $reviewFill = ['title_text' => $m->title.' — '.$m->customer_name, 'action' => $m->status === 'completed' ? 'open' : 'close', 'outcome' => $m->outcome, 'tag' => $m->tag, 'outcome_note' => $m->outcome_note, 'date' => $m->scheduled_at->toDateString(), 'time' => $m->scheduled_at->format('H:i'), 'end_time' => $m->endsAt()->format('H:i')]; @endphp
                                <button type="button" @class(['btn btn-sm', 'btn-warning' => $m->needsReview(), 'btn-light' => ! $m->needsReview()]) data-modal="#meetingReviewModal" data-action="{{ route($portal.'.meetings.review', $m) }}" data-fill='@json($reviewFill)'><i class="bi bi-clipboard-check"></i> Review</button>
                            @endif
                            @if (in_array($m->status, ['pending', 'upcoming'], true))
                                <form method="POST" action="{{ route($portal.'.meetings.cancel', $m) }}" class="d-inline" data-confirm="Cancel this meeting?">@csrf<button class="btn btn-sm btn-outline-danger">Cancel</button></form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8"><x-empty-state icon="calendar-event" title="No meetings here" /></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($meetings->hasPages())<div class="card-pad pt-2">{{ $meetings->links() }}</div>@endif
</div>

<div class="modal fade" id="assignModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <form method="POST" action="{{ route($portal.'.meetings.store') }}" class="modal-content rounded-4 border-0">
            @csrf
            <div class="modal-header border-0">
                <h5 class="modal-title fw-bold">Give a Meeting to an Employee</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body pt-0 row g-3">
                <div class="col-12">
                    <label class="form-label required">Employee</label>
                    <select name="user_id" class="form-select" required>
                        <option value="">Choose employee…</option>
                        @foreach ($staff as $s)<option value="{{ $s->id }}" @selected(old('user_id', $forLead?->assigned_to) == $s->id)>{{ $s->name }} ({{ $s->employee_code }})</option>@endforeach
                    </select>
                </div>
                <div class="col-md-5"><label class="form-label required">Customer name</label><input name="customer_name" class="form-control" value="{{ old('customer_name', $forLead?->name) }}" required maxlength="120"></div>
                <div class="col-md-4"><label class="form-label">Mobile</label><input name="customer_mobile" class="form-control" value="{{ old('customer_mobile', $forLead?->mobile) }}" maxlength="20"></div>
                <div class="col-md-3"><label class="form-label">City</label><input name="city" class="form-control" value="{{ old('city', $forLead?->city) }}" maxlength="80"></div>
                <div class="col-12"><label class="form-label required">Meeting title</label><input name="title" class="form-control" value="{{ old('title') }}" required maxlength="150"></div>
                <div class="col-md-4">
                    <label class="form-label required">Platform</label>
                    <select name="platform" class="form-select">
                        @foreach ($platforms as $key => [$plabel])<option value="{{ $key }}" @selected(old('platform') === $key)>{{ $plabel }}</option>@endforeach
                    </select>
                </div>
                <div class="col-md-4"><label class="form-label required">Date</label><input type="date" name="date" class="form-control" min="{{ today()->toDateString() }}" value="{{ old('date', today()->toDateString()) }}" required></div>
                <div class="col-6 col-md-2"><label class="form-label required">From</label><input type="time" name="time" class="form-control" value="{{ old('time') }}" required data-from-time></div>
                <div class="col-6 col-md-2"><label class="form-label required">To</label><input type="time" name="end_time" class="form-control" value="{{ old('end_time') }}" required data-to-time></div>
                <div class="col-12"><label class="form-label">Meeting link <span class="small text-muted fw-normal">(only for Google Meet / Zoom / Teams — Portal Meeting joins inside the dashboard)</span></label><input type="url" name="link" class="form-control" value="{{ old('link') }}" placeholder="https://meet.google.com/…"></div>
                <div class="col-12"><label class="form-label">Notes</label><textarea name="notes" rows="2" class="form-control" maxlength="1000">{{ old('notes') }}</textarea></div>
                @if ($forLead)<input type="hidden" name="lead_id" value="{{ $forLead->id }}">@endif
                <div class="col-12"><div class="meet-people">
                    <div class="fw-bold small mb-2"><i class="bi bi-people-fill me-1"></i>Managers in this meeting</div>
                    <div class="row g-2">
                        <div class="col-md-5">
                            <label class="form-label small">Primary manager <span class="text-muted">(sees mobile & email)</span></label>
                            <select name="primary_manager_id" class="form-select form-select-sm">
                                <option value="">— None —</option>
                                @foreach ($managers as $mg)<option value="{{ $mg->id }}" @selected((int) old('primary_manager_id', auth()->user()->role->portal() === 'manager' ? auth()->id() : null) === $mg->id)>{{ $mg->name }} ({{ $mg->roleLabel() }})</option>@endforeach
                            </select>
                        </div>
                        <div class="col-md-7">
                            <label class="form-label small">Other managers</label>
                            <div class="meet-manager-list">
                                @foreach ($managers as $mg)<label class="small"><input type="checkbox" name="managers[]" value="{{ $mg->id }}" class="form-check-input me-1" @checked(in_array($mg->id, old('managers', [])))> {{ $mg->name }}</label>@endforeach
                            </div>
                        </div>
                    </div>
                    <div class="row g-2 mt-1">
                        <div class="col-md-6"><label class="form-label small"><i class="bi bi-envelope"></i> Send invite to e-mails</label><textarea name="notify_emails" rows="2" class="form-control form-control-sm" placeholder="a@mail.com, b@mail.com">{{ old('notify_emails', $forLead?->email) }}</textarea></div>
                        <div class="col-md-6"><label class="form-label small"><i class="bi bi-whatsapp"></i> WhatsApp numbers</label><textarea name="notify_numbers" rows="2" class="form-control form-control-sm" placeholder="9876543210">{{ old('notify_numbers', $forLead?->mobile) }}</textarea></div>
                    </div>
                </div></div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-primary"><i class="bi bi-send me-1"></i>Send Meeting Request</button>
            </div>
        </form>
    </div>
</div>
@include('meetings.partials.review-modal')

@if ($errors->any() && old('customer_name'))
    @push('scripts')<script>bootstrap.Modal.getOrCreateInstance(document.getElementById('assignModal')).show();</script>@endpush
@endif
@if ($forLead)
    @push('scripts')<script>document.addEventListener('DOMContentLoaded', () => bootstrap.Modal.getOrCreateInstance(document.getElementById('assignModal')).show());</script>@endpush
@endif
@endsection
