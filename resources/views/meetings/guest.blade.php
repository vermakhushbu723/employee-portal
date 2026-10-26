<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex">
    <title>{{ $m->title }} · Meeting</title>
    <link rel="icon" href="{{ asset('icons/icon-192.png') }}">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}" rel="stylesheet">
</head>
<body class="meet-guest-body">
    <header class="meet-guest-head">
        <img src="{{ asset('icons/icon-192.png') }}" alt="" width="34" height="34">
        <div>
            <b>{{ config('portal.company') }}</b>
            <div class="small opacity-75">Video meeting{{ $m->user ? ' with '.$m->user->name : '' }}</div>
        </div>
    </header>
    <main class="meet-guest-main">
        @if (in_array($m->status, ['completed', 'cancelled', 'declined'], true))
            <div class="meet-left text-center">
                <i class="bi bi-calendar-x display-5"></i>
                <h5 class="fw-bold mt-2">This meeting has {{ $m->status === 'completed' ? 'ended' : 'been cancelled' }}</h5>
                <div class="small opacity-75">Please contact us for a new meeting link.</div>
            </div>
        @else
            @include('partials.meeting-room', ['m' => $m, 'api' => $api, 'askName' => true])
        @endif
    </main>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="{{ asset('js/meeting-room.js') }}?v={{ filemtime(public_path('js/meeting-room.js')) }}"></script>
</body>
</html>
