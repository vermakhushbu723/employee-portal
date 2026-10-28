@extends('layouts.app')

@section('title', 'Live Meeting')
@section('back', route('employee.meetings.index'))

@section('content')
@if ($m->isPortal())
    {{-- Built-in room: the call happens right here in the dashboard --}}
    @include('partials.meeting-room', [
        'api' => \App\Http\Controllers\MeetingRoomController::api($m, false),
        'backUrl' => route('employee.meetings.index'),
    ])
@else
@php $live = $m->status === 'live'; @endphp
<div class="row g-3">
    <div class="col-lg-8">
        <div class="meeting-stage">
            <div class="d-flex align-items-center gap-2 mb-3">
                <span class="fw-bold">{{ $m->title }}</span>
                @if ($live)
                    <span class="live-pill"><i class="bi bi-circle-fill"></i> <span data-meeting-timer data-start="{{ $m->started_at?->getTimestamp() }}">00:00:00</span></span>
                @else
                    <x-badge :tone="$m->badge()[1]">{{ $m->badge()[0] }}</x-badge>
                @endif
            </div>
            <div class="meeting-screen">
                <span class="icon-box lg"><i class="bi bi-{{ $m->platformIcon() }}"></i></span>
                <div class="fs-5 fw-bold mt-2">{{ $m->platformLabel() }} meeting</div>
                <div class="small opacity-75 mb-3">The meeting opens in its own window next to the portal. Keep this page open — your status shows <b>In Meeting</b> until you end it.</div>
                @if ($m->link)
                    <button type="button" class="btn btn-light" data-open-meeting="{{ $m->link }}"><i class="bi bi-box-arrow-up-right me-1"></i>Open meeting window</button>
                @else
                    <div class="alert alert-warning small mb-0">No meeting link added yet. Go back and use <b>Add link</b>.</div>
                @endif
            </div>
            <div class="meeting-controls">
                @if ($m->link)<button type="button" class="ctrl" data-open-meeting="{{ $m->link }}"><i class="bi bi-camera-video"></i><span>Meeting</span></button>@endif
                <button type="button" class="ctrl" data-copy="{{ $m->link }}" @disabled(! $m->link)><i class="bi bi-link-45deg"></i><span>Copy Link</span></button>
                @if ($m->customer_mobile && $m->link)
                    <a class="ctrl" target="_blank" rel="noopener" href="https://wa.me/{{ preg_replace('/\D/', '', strlen(preg_replace('/\D/', '', $m->customer_mobile)) === 10 ? '91'.$m->customer_mobile : $m->customer_mobile) }}?text={{ rawurlencode("Join the meeting here: {$m->link}") }}"><i class="bi bi-whatsapp"></i><span>Invite</span></a>
                @endif
                @if ($next)
                    <form method="POST" action="{{ route('employee.meetings.join', $next) }}" class="d-inline">@csrf<button class="ctrl" title="{{ $next->title }} · {{ $next->scheduled_at->format('h:i A') }}"><i class="bi bi-skip-end"></i><span>Next Meeting</span></button></form>
                @endif
                <a href="{{ route('employee.meetings.index') }}" class="ctrl end" title="The meeting stays open — management closes it after review"><i class="bi bi-telephone-x-fill"></i><span>Leave</span></a>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card-soft card-pad">
            <div class="section-head">
                <h3 class="section-title">Meeting Details</h3>
                @if ($m->link)<button type="button" class="btn btn-sm btn-light" data-copy="{{ $m->link }}"><i class="bi bi-clipboard"></i> Copy Link</button>@endif
            </div>
            <dl class="meeting-dl">
                <dt>Meeting Title</dt><dd>{{ $m->title }}</dd>
                <dt>Date & Time</dt><dd>{{ $m->scheduled_at->format('d M Y | h:i A') }}</dd>
                <dt>Meeting Link</dt><dd class="text-break">@if ($m->link)<a href="{{ $m->link }}" target="_blank" rel="noopener">{{ $m->link }}</a>@else — @endif</dd>
                <dt>Customer</dt><dd>{{ $m->customer_name }}@if ($m->customer_mobile) · {{ $m->customer_mobile }}@endif @if ($m->city)· {{ $m->city }}@endif</dd>
                @if ($m->creator && $m->creator->id !== $m->user_id)<dt>Given by</dt><dd>{{ $m->creator->publicName() }}</dd>@endif
                @if ($m->notes)<dt>Notes</dt><dd>{!! nl2br(e($m->notes)) !!}</dd>@endif
            </dl>
        </div>
    </div>
</div>
@endif
@endsection

@push('scripts')
@if ($m->isPortal())
<script src="{{ asset('js/meeting-room.js') }}?v={{ filemtime(public_path('js/meeting-room.js')) }}"></script>
@else
<script>
(() => {
    let win = null;
    document.addEventListener('click', (e) => {
        const b = e.target.closest('[data-open-meeting]');
        if (!b) return;
        const w = Math.min(1100, screen.availWidth - 80), h = Math.min(760, screen.availHeight - 80);
        if (win && !win.closed) { win.focus(); return; }
        win = window.open(b.dataset.openMeeting, 'portalMeeting', `width=${w},height=${h},left=40,top=40`);
    });
    const t = document.querySelector('[data-meeting-timer]');
    if (t && t.dataset.start) {
        const start = Number(t.dataset.start);
        const pad = (n) => String(n).padStart(2, '0');
        const tick = () => { const s = Math.max(0, Math.floor(Date.now() / 1000) - start); t.textContent = `${pad(Math.floor(s / 3600))}:${pad(Math.floor(s % 3600 / 60))}:${pad(s % 60)}`; };
        tick(); setInterval(tick, 1000);
    }
})();
</script>
@endif
@endpush
