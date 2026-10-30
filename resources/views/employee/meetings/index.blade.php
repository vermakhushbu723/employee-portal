@extends('layouts.app')

@section('title', 'Meetings')

@section('content')
@if (session('wa_links'))
    <div class="alert alert-warning small d-flex flex-wrap align-items-center gap-2">
        <i class="bi bi-whatsapp"></i> Tap a number to send the meeting invite from your WhatsApp (opens with the message ready):
        @foreach (session('wa_links') as $w)<a href="{{ $w['link'] }}" target="_blank" rel="noopener" class="btn btn-sm btn-success">+{{ $w['to'] }}</a>@endforeach
    </div>
@endif

@if ($live)
    <a href="{{ route('employee.meetings.room', $live) }}" class="status-banner tone-success mb-3 text-decoration-none">
        <span class="dot"><i class="bi bi-camera-video-fill small"></i></span>
        <div>You are in a meeting: {{ $live->title }} <small>With {{ $live->customer_name }} · since {{ $live->started_at?->format('h:i A') }}</small></div>
        <span class="ms-auto btn btn-sm btn-success">Return to meeting</span>
    </a>
@endif

<div class="d-flex flex-wrap align-items-center gap-2 mb-3">
    <h1 class="h5 fw-bold mb-0 flex-grow-1">Meeting Schedule</h1>
    <button type="button" class="btn btn-light" data-email-compose='{}'><i class="bi bi-envelope me-1"></i>Send Email</button>
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#bookModal"><i class="bi bi-plus-lg me-1"></i>Book Meeting</button>
</div>

<nav class="seg-tabs mb-3">
    @foreach (['upcoming' => 'Upcoming Meetings', 'requests' => 'Meeting Requests', 'past' => 'Past Meetings', 'emails' => 'E-mails Sent'] as $key => $label)
        <a href="{{ route('employee.meetings.index', ['tab' => $key]) }}" @class(['active' => $tab === $key])>{{ $label }} ({{ $counts[$key] }})</a>
    @endforeach
</nav>

@if ($tab === 'emails')
<div class="card-soft">
    @forelse ($emails as $em)
        <div class="meeting-row">
            <div class="meeting-when"><div class="fw-bold">{{ $em->created_at->format('d M Y') }}</div><div class="small text-muted">{{ $em->created_at->format('h:i A') }}</div></div>
            <div class="flex-grow-1 min-w-0">
                <div class="fw-600 text-truncate">{{ $em->subject }}</div>
                <div class="small text-muted text-truncate">To {{ $em->to }}@if ($em->meeting) · {{ $em->meeting->title }}@endif</div>
                <div class="small-xs text-muted text-truncate">{{ \Illuminate\Support\Str::limit($em->body, 140) }}</div>
            </div>
            <x-badge :tone="$em->status === 'sent' ? 'success' : 'danger'">{{ $em->status === 'sent' ? 'Sent' : 'Not sent' }}</x-badge>
        </div>
    @empty
        <x-empty-state icon="envelope" title="No e-mails sent yet" text="Use Send Email above, or the envelope button on a meeting." />
    @endforelse
    @if ($emails->hasPages())<div class="card-pad pt-2">{{ $emails->links() }}</div>@endif
</div>
@else
<div class="card-soft">
    @forelse ($meetings as $m)
        @php
            [$label, $tone] = $m->badge();
            $wa = $m->customer_mobile && $m->joinLink()
                ? 'https://wa.me/'.preg_replace('/\D/', '', strlen(preg_replace('/\D/', '', $m->customer_mobile)) === 10 ? '91'.$m->customer_mobile : $m->customer_mobile)
                    .'?text='.rawurlencode("Hello {$m->customer_name}, your meeting \"{$m->title}\" is on {$m->scheduled_at->format('d M Y, h:i A')}. Join here: {$m->joinLink()}")
                : null;
            $mailTo = $m->lead?->email ?: collect($m->notified ?? [])->firstWhere('channel', 'email')['to'] ?? '';
            $mailFill = ['to' => $mailTo, 'name' => $m->customer_name, 'title' => $m->title, 'date' => $m->scheduled_at->format('d M Y'), 'time' => $m->scheduled_at->format('h:i A'), 'link' => $m->joinLink(), 'meeting_id' => $m->id];
            $fill = ['title_text' => $m->title.' — '.$m->customer_name, 'link' => $m->link, 'date' => $m->scheduled_at->toDateString(), 'time' => $m->scheduled_at->format('H:i'), 'end_time' => $m->endsAt()->format('H:i')];
        @endphp
        <div class="meeting-row">
            <div class="meeting-when">
                <div class="fw-bold">{{ $m->scheduled_at->format('d M Y') }}</div>
                <div class="small text-muted">{{ $m->timeRange() }}</div>
            </div>
            <div class="flex-grow-1 min-w-0">
                <div class="fw-600 text-truncate">{{ $m->customer_name }} @if ($m->city)<span class="small text-muted fw-normal">· {{ $m->city }}</span>@endif</div>
                <div class="small text-muted text-truncate">{{ $m->title }} · <i class="bi bi-{{ $m->platformIcon() }}"></i> {{ $m->platformLabel() }}@if ($m->customer_mobile) · {{ $m->customer_mobile }}@endif</div>
                @if ($m->creator && $m->creator->id !== $m->user_id)<div class="small-xs text-muted">Given by {{ $m->creator->publicName() }}</div>@endif
                @if ($m->notes)<div class="small-xs text-muted text-truncate">{{ $m->notes }}</div>@endif
                @if ($m->participants->isNotEmpty())<div class="small-xs text-muted"><i class="bi bi-people"></i> {{ $m->participants->map(fn ($p) => $p->name.($p->pivot->role === 'primary' ? ' (primary)' : ''))->implode(', ') }}</div>@endif
                @if ($m->notified)<div class="small-xs text-muted"><i class="bi bi-send"></i> Invite: {{ collect($m->notified)->where('ok', true)->count() }} sent of {{ count($m->notified) }}</div>@endif
            </div>
            @if ($m->status === 'completed' && ($om = $m->outcomeMeta()))
                <span class="text-end">
                    <x-badge :tone="$om[1]"><i class="bi bi-{{ $om[2] }}"></i> {{ $om[0] }}</x-badge>
                    @if ($tm = $m->tagMeta())<x-badge :tone="$tm[1]"><i class="bi bi-{{ $tm[2] }}"></i> {{ $tm[0] }}</x-badge>@endif
                    @if ($m->durationLabel())<div class="small-xs text-muted">{{ $m->durationLabel() }}</div>@endif
                </span>
            @else
                <span class="text-end">
                    <x-badge :tone="$tone">{{ $label }}</x-badge>
                    @if ($m->needsReview())<div class="small-xs text-muted">Stays open — management closes it</div>@endif
                    @if ($tm = $m->tagMeta())<div><x-badge :tone="$tm[1]">{{ $tm[0] }}</x-badge></div>@endif
                </span>
            @endif
            <div class="meeting-actions">
                @if ($m->status === 'pending')
                    <form method="POST" action="{{ route('employee.meetings.accept', $m) }}" class="d-inline">@csrf<button class="btn btn-sm btn-success"><i class="bi bi-check-lg"></i> Accept</button></form>
                    <form method="POST" action="{{ route('employee.meetings.decline', $m) }}" class="d-inline" data-confirm="Decline this meeting request?">@csrf<button class="btn btn-sm btn-outline-danger">Decline</button></form>
                @elseif (in_array($m->status, ['upcoming', 'live'], true))
                    <form method="POST" action="{{ route('employee.meetings.join', $m) }}" class="d-inline">@csrf<button class="btn btn-sm btn-success"><i class="bi bi-camera-video"></i> {{ $m->status === 'live' ? 'Return' : 'Join Meeting' }}</button></form>
                    @if ($wa)<a href="{{ $wa }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-success" title="Send link to customer on WhatsApp"><i class="bi bi-whatsapp"></i></a>@endif
                    <button type="button" class="btn btn-sm btn-outline-primary" data-email-compose='@json($mailFill)' title="E-mail the customer (invite, reminder, reschedule…)"><i class="bi bi-envelope"></i></button>
                    <button type="button" class="btn btn-sm btn-light" data-modal="#editModal" data-action="{{ route('employee.meetings.update', $m) }}"
                            data-fill='@json($fill)'>
                        {{ $m->link || $m->isPortal() ? 'Reschedule' : 'Add link' }}
                    </button>
                @endif
            </div>
        </div>
    @empty
        <x-empty-state icon="calendar-event" title="No meetings here" text="Book a meeting with a customer, or accept a request from your manager." />
    @endforelse
    @if ($meetings->hasPages())<div class="card-pad pt-2">{{ $meetings->links() }}</div>@endif
</div>
@endif

@include('partials.email-compose', ['emailSuggestions' => $emailSuggestions])

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
                                <input type="radio" name="platform" value="{{ $key }}" @checked(old('platform', 'portal') === $key) data-new-url="{{ $newUrl }}">
                                <span><i class="bi bi-{{ $picon }}"></i> {{ $plabel }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
                <div class="col-md-4"><label class="form-label required">Date</label><input type="date" name="date" class="form-control" min="{{ today()->toDateString() }}" value="{{ old('date', today()->toDateString()) }}" required></div>
                <div class="col-6 col-md-4"><label class="form-label required">From time</label><input type="time" name="time" class="form-control" value="{{ old('time') }}" required data-from-time></div>
                <div class="col-6 col-md-4"><label class="form-label required">To time</label><input type="time" name="end_time" class="form-control" value="{{ old('end_time') }}" required data-to-time></div>
                <div class="col-12 small-xs text-muted mt-1"><i class="bi bi-info-circle"></i> The meeting link works the whole time and stays open after it — only management closes the meeting.</div>
                <div class="col-md-7" data-link-field>
                    <label class="form-label">Meeting link</label>
                    <div class="input-group">
                        <input type="url" name="link" class="form-control" value="{{ old('link') }}" placeholder="https://meet.google.com/…">
                        <button type="button" class="btn btn-outline-primary" data-create-link title="Opens the platform to create a new meeting — copy its link here"><i class="bi bi-plus-circle"></i> Create</button>
                    </div>
                </div>
                <div class="col-md-7 d-none" data-portal-note><div class="info-box h-100"><i class="bi bi-camera-video-fill"></i><div>Joins <b>inside the dashboard</b>. The customer gets a join link (send it on WhatsApp from the list) — no app or login needed.</div></div></div>
                <div class="col-12"><label class="form-label">Notes (optional)</label><textarea name="notes" rows="2" class="form-control" maxlength="1000">{{ old('notes') }}</textarea></div>

                {{-- Who joins + who gets the invite --}}
                <div class="col-12"><div class="meet-people">
                    <div class="fw-bold small mb-2"><i class="bi bi-people-fill me-1"></i>Managers in this meeting</div>
                    <div class="row g-2">
                        <div class="col-md-5">
                            <label class="form-label small">Primary manager <span class="text-muted">(sees customer mobile & email)</span></label>
                            <select name="primary_manager_id" class="form-select form-select-sm" data-primary-manager>
                                <option value="">— None —</option>
                                @foreach ($managers as $mg)<option value="{{ $mg->id }}" data-email="{{ $mg->email }}" data-mobile="{{ $mg->mobile }}" @selected((int) old('primary_manager_id', $myManagerId) === $mg->id)>{{ $mg->name }} ({{ $mg->roleLabel() }})</option>@endforeach
                            </select>
                        </div>
                        <div class="col-md-7">
                            <label class="form-label small">Other managers <span class="text-muted">(see lead details, not mobile / email)</span></label>
                            <div class="meet-manager-list">
                                @foreach ($managers as $mg)
                                    <label class="small"><input type="checkbox" name="managers[]" value="{{ $mg->id }}" class="form-check-input me-1" data-email="{{ $mg->email }}" data-mobile="{{ $mg->mobile }}" @checked(in_array($mg->id, old('managers', [])))> {{ $mg->name }}</label>
                                @endforeach
                            </div>
                        </div>
                    </div>
                    <div class="fw-bold small mt-3 mb-2"><i class="bi bi-send-fill me-1"></i>Send the invite (with the meeting link)</div>
                    <div class="row g-2">
                        <div class="col-md-6"><label class="form-label small"><i class="bi bi-envelope"></i> E-mails</label><textarea name="notify_emails" rows="2" class="form-control form-control-sm" placeholder="customer@mail.com, manager@mail.com" data-notify-emails>{{ old('notify_emails') }}</textarea></div>
                        <div class="col-md-6"><label class="form-label small"><i class="bi bi-whatsapp"></i> WhatsApp numbers</label><textarea name="notify_numbers" rows="2" class="form-control form-control-sm" placeholder="9876543210, 9123456780" data-notify-numbers>{{ old('notify_numbers') }}</textarea></div>
                    </div>
                    <div class="small-xs text-muted mt-1">Sent when you book: e-mail from the portal, WhatsApp from your linked WhatsApp (if not linked, you get one-tap WhatsApp links).</div>
                </div></div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-primary"><i class="bi bi-send-check me-1"></i>Book & Send Invite</button>
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
                <div class="col-12"><label class="form-label required">Date</label><input type="date" name="date" class="form-control" min="{{ today()->toDateString() }}" required></div>
                <div class="col-6"><label class="form-label required">From time</label><input type="time" name="time" class="form-control" required data-from-time></div>
                <div class="col-6"><label class="form-label required">To time</label><input type="time" name="end_time" class="form-control" required data-to-time></div>
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
@php $leadContacts = $leads->mapWithKeys(fn ($l) => [$l->id => ['m' => $l->mobile, 'e' => $l->email]]); @endphp
<script>
(() => {
    const emails = document.querySelector('[data-notify-emails]');
    const numbers = document.querySelector('[data-notify-numbers]');
    const addTo = (box, value) => {
        if (!value) return;
        const list = box.value.split(/[\s,;]+/).filter(Boolean);
        if (!list.includes(value)) { list.push(value); box.value = list.join(', '); }
    };
    const leadContacts = {!! json_encode($leadContacts, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) !!};
    document.querySelector('[data-meeting-lead]')?.addEventListener('change', (e) => {
        const c = leadContacts[e.target.value];
        if (c) { addTo(numbers, (c.m || '').replace(/\D/g, '')); addTo(emails, c.e); }
    });
    document.querySelector('[name=customer_mobile]')?.addEventListener('change', (e) => addTo(numbers, e.target.value.replace(/\D/g, '')));
    document.querySelectorAll('[name="managers[]"]').forEach((cb) => cb.addEventListener('change', () => { if (cb.checked) addTo(emails, cb.dataset.email); }));
    document.querySelector('[data-primary-manager]')?.addEventListener('change', (e) => addTo(emails, e.target.selectedOptions[0]?.dataset.email));
    @if ($forLead)
        document.addEventListener('DOMContentLoaded', () => {
            const sel = document.querySelector('[data-meeting-lead]');
            sel.value = '{{ $forLead->id }}'; sel.dispatchEvent(new Event('change'));
            bootstrap.Modal.getOrCreateInstance(document.getElementById('bookModal')).show();
        });
    @endif
    const lead = document.querySelector('[data-meeting-lead]');
    const sync = () => document.querySelectorAll('[data-manual-customer]').forEach((el) => el.classList.toggle('d-none', !!lead.value));
    lead?.addEventListener('change', sync);
    sync();
    // Portal Meeting needs no link; outside platforms do
    const platformSync = () => {
        const portal = document.querySelector('input[name=platform]:checked')?.value === 'portal';
        document.querySelector('[data-link-field]').classList.toggle('d-none', portal);
        document.querySelector('[data-portal-note]').classList.toggle('d-none', !portal);
    };
    document.querySelectorAll('input[name=platform]').forEach((r) => r.addEventListener('change', platformSync));
    platformSync();
    document.querySelector('[data-create-link]')?.addEventListener('click', () => {
        const url = document.querySelector('input[name=platform]:checked')?.dataset.newUrl;
        if (url) window.open(url, 'newMeeting', 'width=1000,height=720');
        else alert('Paste the meeting link of your platform.');
    });
    @if ($errors->any() && old('title')) bootstrap.Modal.getOrCreateInstance(document.getElementById('bookModal')).show(); @endif
})();
</script>
@endpush
