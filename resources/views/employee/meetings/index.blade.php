@extends('layouts.app')

@section('title', 'Meetings')

@section('content')
@if ($live)
    <a href="{{ route('employee.meetings.room', $live) }}" class="status-banner tone-success mb-3 text-decoration-none">
        <span class="dot"><i class="bi bi-camera-video-fill small"></i></span>
        <div>You are in a meeting: {{ $live->title }} <small>With {{ $live->customer_name }} · since {{ $live->started_at?->format('h:i A') }}</small></div>
        <span class="ms-auto btn btn-sm btn-success">Return to meeting</span>
    </a>
@endif

<div class="d-flex flex-wrap align-items-center gap-2 mb-3">
    <h1 class="h5 fw-bold mb-0 flex-grow-1">Meeting Schedule</h1>
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#bookModal"><i class="bi bi-plus-lg me-1"></i>Book Meeting</button>
</div>

<nav class="seg-tabs mb-3">
    @foreach (['upcoming' => 'Upcoming Meetings', 'requests' => 'Meeting Requests', 'past' => 'Past Meetings'] as $key => $label)
        <a href="{{ route('employee.meetings.index', ['tab' => $key]) }}" @class(['active' => $tab === $key])>{{ $label }} ({{ $counts[$key] }})</a>
    @endforeach
</nav>

<div class="card-soft">
    @forelse ($meetings as $m)
        @php
            [$label, $tone] = $m->badge();
            $wa = $m->customer_mobile && $m->link
                ? 'https://wa.me/'.preg_replace('/\D/', '', strlen(preg_replace('/\D/', '', $m->customer_mobile)) === 10 ? '91'.$m->customer_mobile : $m->customer_mobile)
                    .'?text='.rawurlencode("Hello {$m->customer_name}, your meeting \"{$m->title}\" is on {$m->scheduled_at->format('d M Y, h:i A')}. Join here: {$m->link}")
                : null;
            $fill = ['title_text' => $m->title.' — '.$m->customer_name, 'link' => $m->link, 'date' => $m->scheduled_at->toDateString(), 'time' => $m->scheduled_at->format('H:i')];
        @endphp
        <div class="meeting-row">
            <div class="meeting-when">
                <div class="fw-bold">{{ $m->scheduled_at->format('d M Y') }}</div>
                <div class="small text-muted">{{ $m->scheduled_at->format('h:i A') }}</div>
            </div>
            <div class="flex-grow-1 min-w-0">
                <div class="fw-600 text-truncate">{{ $m->customer_name }} @if ($m->city)<span class="small text-muted fw-normal">· {{ $m->city }}</span>@endif</div>
                <div class="small text-muted text-truncate">{{ $m->title }} · <i class="bi bi-{{ $m->platformIcon() }}"></i> {{ $m->platformLabel() }}@if ($m->customer_mobile) · {{ $m->customer_mobile }}@endif</div>
                @if ($m->creator && $m->creator->id !== $m->user_id)<div class="small-xs text-muted">Given by {{ $m->creator->name }}</div>@endif
                @if ($m->notes)<div class="small-xs text-muted text-truncate">{{ $m->notes }}</div>@endif
            </div>
            <x-badge :tone="$tone">{{ $label }}</x-badge>
            <div class="meeting-actions">
                @if ($m->status === 'pending')
                    <form method="POST" action="{{ route('employee.meetings.accept', $m) }}" class="d-inline">@csrf<button class="btn btn-sm btn-success"><i class="bi bi-check-lg"></i> Accept</button></form>
                    <form method="POST" action="{{ route('employee.meetings.decline', $m) }}" class="d-inline" data-confirm="Decline this meeting request?">@csrf<button class="btn btn-sm btn-outline-danger">Decline</button></form>
                @elseif (in_array($m->status, ['upcoming', 'live'], true))
                    <form method="POST" action="{{ route('employee.meetings.join', $m) }}" class="d-inline">@csrf<button class="btn btn-sm btn-success"><i class="bi bi-camera-video"></i> {{ $m->status === 'live' ? 'Return' : 'Join Meeting' }}</button></form>
                    @if ($wa)<a href="{{ $wa }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-success" title="Send link to customer on WhatsApp"><i class="bi bi-whatsapp"></i></a>@endif
                    <button type="button" class="btn btn-sm btn-light" data-modal="#editModal" data-action="{{ route('employee.meetings.update', $m) }}"
                            data-fill='@json($fill)'>
                        {{ $m->link ? 'Reschedule' : 'Add link' }}
                    </button>
                @endif
            </div>
        </div>
    @empty
        <x-empty-state icon="calendar-event" title="No meetings here" text="Book a meeting with a customer, or accept a request from your manager." />
    @endforelse
    @if ($meetings->hasPages())<div class="card-pad pt-2">{{ $meetings->links() }}</div>@endif
</div>

{{-- Book a meeting --}}
<div class="modal fade" id="bookModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <form method="POST" action="{{ route('employee.meetings.store') }}" class="modal-content rounded-4 border-0">
            @csrf
            <div class="modal-header border-0">
                <h5 class="modal-title fw-bold">Book a Meeting</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body pt-0 row g-3">
                <div class="col-12">
                    <label class="form-label">Customer (from My Leads)</label>
                    <select name="lead_id" class="form-select" data-meeting-lead>
                        <option value="">— Type customer manually —</option>
                        @foreach ($leads as $l)<option value="{{ $l->id }}" @selected(old('lead_id') == $l->id)>{{ $l->name }} ({{ $l->mobile }}){{ $l->city ? ' · '.$l->city : '' }}</option>@endforeach
                    </select>
                </div>
                <div class="col-md-5" data-manual-customer><label class="form-label">Customer name</label><input name="customer_name" class="form-control" value="{{ old('customer_name') }}" maxlength="120"></div>
                <div class="col-md-4" data-manual-customer><label class="form-label">Mobile</label><input name="customer_mobile" class="form-control" value="{{ old('customer_mobile') }}" maxlength="20"></div>
                <div class="col-md-3" data-manual-customer><label class="form-label">City</label><input name="city" class="form-control" value="{{ old('city') }}" maxlength="80"></div>
                <div class="col-12"><label class="form-label required">Meeting title</label><input name="title" class="form-control" value="{{ old('title') }}" maxlength="150" required placeholder="e.g. Home loan discussion"></div>
                <div class="col-12">
                    <label class="form-label required">Meeting platform</label>
                    <div class="platform-pick">
                        @foreach ($platforms as $key => [$plabel, $picon, $newUrl])
                            <label class="platform-opt">
                                <input type="radio" name="platform" value="{{ $key }}" @checked(old('platform', 'google_meet') === $key) data-new-url="{{ $newUrl }}">
                                <span><i class="bi bi-{{ $picon }}"></i> {{ $plabel }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
                <div class="col-md-4"><label class="form-label required">Date</label><input type="date" name="date" class="form-control" min="{{ today()->toDateString() }}" value="{{ old('date', today()->toDateString()) }}" required></div>
                <div class="col-md-3"><label class="form-label required">Time</label><input type="time" name="time" class="form-control" value="{{ old('time') }}" required></div>
                <div class="col-md-5">
                    <label class="form-label">Meeting link</label>
                    <div class="input-group">
                        <input type="url" name="link" class="form-control" value="{{ old('link') }}" placeholder="https://meet.google.com/…">
                        <button type="button" class="btn btn-outline-primary" data-create-link title="Opens the platform to create a new meeting — copy its link here"><i class="bi bi-plus-circle"></i> Create</button>
                    </div>
                </div>
                <div class="col-12"><label class="form-label">Notes (optional)</label><textarea name="notes" rows="2" class="form-control" maxlength="1000">{{ old('notes') }}</textarea></div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-primary"><i class="bi bi-calendar-plus me-1"></i>Book Meeting</button>
            </div>
        </form>
    </div>
</div>

{{-- Add link / reschedule --}}
<div class="modal fade" id="editModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" action="" class="modal-content rounded-4 border-0">
            @csrf
            @method('PATCH')
            <div class="modal-header border-0">
                <div><h5 class="modal-title fw-bold mb-0">Meeting link & time</h5><div class="small text-muted" data-field="title_text"></div></div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body pt-0 row g-3">
                <div class="col-12"><label class="form-label">Meeting link</label><input type="url" name="link" class="form-control" placeholder="https://…"></div>
                <div class="col-7"><label class="form-label required">Date</label><input type="date" name="date" class="form-control" min="{{ today()->toDateString() }}" required></div>
                <div class="col-5"><label class="form-label required">Time</label><input type="time" name="time" class="form-control" required></div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-primary">Save</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
(() => {
    const lead = document.querySelector('[data-meeting-lead]');
    const sync = () => document.querySelectorAll('[data-manual-customer]').forEach((el) => el.classList.toggle('d-none', !!lead.value));
    lead?.addEventListener('change', sync);
    sync();
    document.querySelector('[data-create-link]')?.addEventListener('click', () => {
        const url = document.querySelector('input[name=platform]:checked')?.dataset.newUrl;
        if (url) window.open(url, 'newMeeting', 'width=1000,height=720');
        else alert('Paste the meeting link of your platform.');
    });
    @if ($errors->any() && old('title')) bootstrap.Modal.getOrCreateInstance(document.getElementById('bookModal')).show(); @endif
})();
</script>
@endpush
