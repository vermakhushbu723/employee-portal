<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') · {{ config('portal.company') }}</title>
    @include('partials.pwa-head')

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}" rel="stylesheet">
    @stack('styles')
</head>
<body class="has-bottom-nav" data-reopen-modal="@yield('reopen-modal')"
      data-pulse-url="{{ route('live.pulse') }}"
      data-pulse-after="{{ \App\Models\Announcement::visibleTo(auth()->user())->max('id') ?? 0 }}">
    <div class="nav-progress" id="navProgress"></div>

    <div class="app-shell">
        @include('partials.sidebar')

        <div class="app-main">
            @include('partials.topbar')
            @if (auth()->user()->tracksAttendance())
                @include('partials.employee-status-bar')
            @endif

            <main class="app-content">
                @include('partials.flash')
                @yield('content')
            </main>
        </div>
    </div>

    @include('partials.bottom-nav')

    {{-- WhatsApp: QR button + small floating panel that opens / docks / sizes the WhatsApp Web window --}}
    <a href="https://web.whatsapp.com/" target="_blank" rel="noopener" class="wa-fab" id="waFab" title="WhatsApp" aria-label="Open WhatsApp panel">
        <i class="bi bi-qr-code-scan"></i>
        <span class="wa-badge"><i class="bi bi-whatsapp"></i></span>
    </a>
    @include('partials.whatsapp-panel')

    {{-- Location mandatory for this ID: nothing can be used until location is allowed (public/js/app.js) --}}
    @if (auth()->user()?->location_required)
        <div class="geo-gate d-none" id="geoGate" role="alertdialog" aria-modal="true" aria-labelledby="geoGateTitle">
            <div class="geo-gate-card">
                <span class="geo-gate-icon"><i class="bi bi-geo-alt-fill"></i></span>
                <h5 class="fw-bold mt-3 mb-1" id="geoGateTitle">Location is mandatory</h5>
                <p class="small text-muted mb-3" data-geo-gate-msg>Your company requires location access to use the portal.</p>
                <ol class="small text-start mb-3">
                    <li>Turn on <b>Location / GPS</b> on your phone or computer.</li>
                    <li>Tap the <b><i class="bi bi-lock-fill"></i> lock icon</b> next to the address (or App info → Permissions) and set <b>Location → Allow</b>.</li>
                    <li>Tap <b>Try again</b>.</li>
                </ol>
                <button type="button" class="btn btn-primary w-100" data-geo-gate-retry><i class="bi bi-arrow-repeat me-1"></i>Try again</button>
            </div>
        </div>
    @endif
    @include('partials.install-banner')
    @include('partials.punch-gate')
    @if (auth()->user()->tracksAttendance())
        @include('partials.logout-status')
    @endif

    {{-- Real-time notifications (filled by public/js/app.js) --}}
    <div class="toast-container live-toasts" id="liveToasts" aria-live="polite"></div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="{{ asset('js/app.js') }}?v={{ filemtime(public_path('js/app.js')) }}"></script>
    <script src="{{ asset('js/whatsapp-panel.js') }}?v={{ filemtime(public_path('js/whatsapp-panel.js')) }}"></script>
    @stack('scripts')
</body>
</html>
