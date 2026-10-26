{{--
    Built-in video meeting room (public/js/meeting-room.js).
    $m = Meeting, $api = MeetingRoomController::api(), $endForm = optional route to end the meeting (host),
    $backUrl = where "Leave" goes, $askName = customer must type a name first.
--}}
@php
    $wa = $m->customer_mobile && ($api['invite'] ?? null)
        ? 'https://wa.me/'.preg_replace('/\D/', '', strlen(preg_replace('/\D/', '', $m->customer_mobile)) === 10 ? '91'.$m->customer_mobile : $m->customer_mobile)
            .'?text='.rawurlencode("Hello {$m->customer_name}, join our meeting \"{$m->title}\" ({$m->scheduled_at->format('d M, h:i A')}) here: {$api['invite']}")
        : null;
    $cfg = $api + ['name' => auth()->user()?->name, 'back' => $backUrl ?? url('/')];
@endphp
<div class="meet" id="meetRoom" data-room='@json($cfg)'>
    {{-- Lobby: camera preview + Join --}}
    <div class="meet-lobby" data-lobby>
        <div class="meet-preview">
            <video data-preview autoplay playsinline muted></video>
            <div class="meet-preview-off d-none" data-preview-off><i class="bi bi-camera-video-off"></i><span>Camera is off</span></div>
        </div>
        <div class="meet-lobby-side">
            <div class="small text-uppercase fw-bold opacity-75">{{ $m->platformLabel() }}</div>
            <h4 class="fw-bold mb-1">{{ $m->title }}</h4>
            <div class="small opacity-75 mb-3">{{ $m->scheduled_at->format('d M Y, h:i A') }} · with {{ $m->customer_name }}</div>
            @if ($askName ?? false)
                <label class="form-label small">Your name</label>
                <input type="text" class="form-control mb-3" maxlength="60" placeholder="Enter your name" data-guest-name value="{{ $m->customer_name }}">
            @endif
            <div class="d-flex gap-2 mb-3">
                <button type="button" class="meet-round" data-toggle-mic title="Microphone"><i class="bi bi-mic-fill"></i></button>
                <button type="button" class="meet-round" data-toggle-cam title="Camera"><i class="bi bi-camera-video-fill"></i></button>
            </div>
            <button type="button" class="btn btn-success btn-lg w-100" data-join><i class="bi bi-camera-video me-1"></i>Join Meeting</button>
            <div class="small mt-2 opacity-75" data-lobby-msg>Allow camera and microphone when the browser asks.</div>
        </div>
    </div>

    {{-- In the meeting --}}
    <div class="meet-call d-none" data-call>
        <div class="meet-top">
            <b class="text-truncate">{{ $m->title }}</b>
            <span class="live-pill"><i class="bi bi-circle-fill"></i> <span data-timer>00:00</span></span>
            <span class="small opacity-75"><i class="bi bi-people-fill"></i> <span data-count>1</span></span>
            <span class="ms-auto small opacity-75" data-net></span>
        </div>
        <div class="meet-body">
            <div class="meet-grid" data-grid>
                <div class="meet-tile me" data-tile="me">
                    <video data-local autoplay playsinline muted></video>
                    <div class="meet-avatar d-none" data-local-off>{{ strtoupper(substr(auth()->user()?->name ?? 'You', 0, 1)) }}</div>
                    <span class="meet-name">You <i class="bi bi-mic-mute-fill d-none" data-local-muted></i></span>
                </div>
            </div>
            <aside class="meet-side d-none" data-side>
                <div class="meet-side-head">
                    <button type="button" class="active" data-side-tab="chat">Chat</button>
                    <button type="button" data-side-tab="people">Participants (<span data-count2>1</span>)</button>
                    <button type="button" data-side-tab="info">Details</button>
                    <button type="button" class="ms-auto" data-side-close aria-label="Close"><i class="bi bi-x-lg"></i></button>
                </div>
                <div class="meet-side-pane" data-pane="chat">
                    <div class="meet-chat" data-chat><div class="small opacity-75 text-center py-3">Messages are visible to everyone in the meeting.</div></div>
                    <form class="meet-chat-form" data-chat-form>
                        <input type="text" maxlength="500" placeholder="Type a message" autocomplete="off">
                        <button aria-label="Send"><i class="bi bi-send-fill"></i></button>
                    </form>
                </div>
                <div class="meet-side-pane d-none" data-pane="people"><ul class="list-unstyled mb-0" data-people></ul></div>
                <div class="meet-side-pane d-none p-3 small" data-pane="info">
                    <div class="opacity-75">Meeting</div><div class="mb-2">{{ $m->title }}</div>
                    <div class="opacity-75">Date & time</div><div class="mb-2">{{ $m->scheduled_at->format('d M Y | h:i A') }}</div>
                    <div class="opacity-75">Customer</div><div class="mb-2">{{ $m->customer_name }}@if ($m->customer_mobile) · {{ $m->customer_mobile }}@endif</div>
                    @if ($api['invite'] && ! $api['guest'])
                        <div class="opacity-75">Customer join link</div>
                        <div class="text-break mb-2"><a href="{{ $api['invite'] }}" target="_blank" rel="noopener" class="link-light">{{ $api['invite'] }}</a></div>
                    @endif
                    @if ($m->notes)<div class="opacity-75">Notes</div><div>{!! nl2br(e($m->notes)) !!}</div>@endif
                </div>
            </aside>
        </div>
        <div class="meet-controls">
            <button type="button" class="ctrl" data-toggle-mic><i class="bi bi-mic-fill"></i><span>Mic</span></button>
            <button type="button" class="ctrl" data-toggle-cam><i class="bi bi-camera-video-fill"></i><span>Camera</span></button>
            <button type="button" class="ctrl d-none d-md-flex" data-screen><i class="bi bi-display"></i><span>Screen</span></button>
            <button type="button" class="ctrl" data-open-side="chat"><i class="bi bi-chat-dots-fill"></i><span>Chat</span><b class="meet-dot d-none" data-chat-dot></b></button>
            <button type="button" class="ctrl" data-open-side="people"><i class="bi bi-people-fill"></i><span>Participants</span></button>
            @if ($api['invite'] && ! $api['guest'])
                <button type="button" class="ctrl" data-copy="{{ $api['invite'] }}"><i class="bi bi-link-45deg"></i><span>Invite Link</span></button>
                @if ($wa)<a class="ctrl" href="{{ $wa }}" target="_blank" rel="noopener"><i class="bi bi-whatsapp"></i><span>Invite</span></a>@endif
            @endif
            <button type="button" class="ctrl end" data-leave><i class="bi bi-telephone-x-fill"></i><span>Leave</span></button>
            @isset($endForm)
                <form method="POST" action="{{ $endForm }}" class="d-inline" data-confirm="End the meeting for everyone?" data-end-form>@csrf<button class="ctrl end"><i class="bi bi-x-octagon-fill"></i><span>End Meeting</span></button></form>
            @endisset
        </div>
    </div>

    {{-- After leaving --}}
    <div class="meet-left d-none text-center" data-left>
        <i class="bi bi-telephone-x display-5"></i>
        <h5 class="fw-bold mt-2" data-left-title>You left the meeting</h5>
        <div class="d-flex gap-2 justify-content-center mt-3">
            <button type="button" class="btn btn-light" data-rejoin>Rejoin</button>
            @unless ($api['guest'])<a href="{{ $backUrl ?? url('/') }}" class="btn btn-success">Back to meetings</a>@endunless
        </div>
    </div>
</div>
