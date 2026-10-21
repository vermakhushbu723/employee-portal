@extends('layouts.auth')

@section('title', 'Install App')

@section('content')
<div class="card-soft p-4 text-center" id="installPage">
    <img src="{{ asset('images/logo.png') }}" alt="Employee Portal" class="auth-brand-logo" width="640" height="517">
    <h1 class="h5 fw-bold mb-1">Install the Employee Portal app</h1>
    <p class="text-muted small mb-4">Attendance, work timer, tasks and leads — opens like an app from your home screen.</p>

    {{-- Waiting for the browser to tell us whether it can install --}}
    <div data-install-state="checking">
        <div class="spinner-border text-secondary mb-2" role="status"></div>
        <div class="small text-muted">Checking your phone…</div>
    </div>

    {{-- Chrome / Edge on Android or desktop: one-tap install --}}
    <div data-install-state="ready" class="d-none">
        <button type="button" class="btn btn-primary btn-lg w-100" data-install-accept>
            <i class="bi bi-download me-1"></i>Install App
        </button>
        <div class="small text-muted mt-2">Free · no Play Store needed · takes 2 seconds</div>
    </div>

    {{-- iPhone / iPad (Safari) --}}
    <div data-install-state="ios" class="d-none text-start">
        <div class="alert alert-info small mb-3"><i class="bi bi-apple me-1"></i>On iPhone, open this link in <b>Safari</b>, then:</div>
        <ol class="small ps-3 mb-0">
            <li class="mb-2">Tap the <b>Share</b> button <i class="bi bi-box-arrow-up"></i> at the bottom.</li>
            <li class="mb-2">Scroll and tap <b>Add to Home Screen</b> <i class="bi bi-plus-square"></i>.</li>
            <li>Tap <b>Add</b>. The app icon appears on your home screen.</li>
        </ol>
    </div>

    {{-- Browser that can't show the prompt right now --}}
    <div data-install-state="manual" class="d-none text-start">
        <div class="alert alert-warning small mb-3"><i class="bi bi-info-circle me-1"></i>Open this link in <b>Google Chrome</b>, then:</div>
        <ol class="small ps-3 mb-0">
            <li class="mb-2">Tap Chrome's menu <b><i class="bi bi-three-dots-vertical"></i></b> (top-right).</li>
            <li class="mb-2">Tap <b>Install app</b> or <b>Add to Home screen</b>.</li>
            <li>Tap <b>Install</b>.</li>
        </ol>
    </div>

    {{-- Already installed --}}
    <div data-install-state="installed" class="d-none">
        <div class="alert alert-success small"><i class="bi bi-check-circle-fill me-1"></i>The app is already installed on this phone.</div>
        <div class="small text-muted">Open <b>Emp Portal</b> from your home screen.</div>
    </div>

    {{-- Just installed --}}
    <div data-install-state="done" class="d-none">
        <div class="alert alert-success small"><i class="bi bi-check-circle-fill me-1"></i>Installed! Find <b>Emp Portal</b> on your home screen.</div>
    </div>

    {{-- Opened inside the installed app --}}
    <div data-install-state="opened" class="d-none">
        <div class="alert alert-success small"><i class="bi bi-check-circle-fill me-1"></i>You are using the app.</div>
    </div>

    <div class="divider-or my-4">OR</div>

    <a href="{{ route('login') }}" class="btn btn-outline-primary w-100">Continue in browser <i class="bi bi-arrow-right"></i></a>
</div>

<p class="text-center small text-muted mt-3 mb-0">
    Share this page: <b>{{ url('/install') }}</b>
    <button type="button" class="btn btn-link btn-sm p-0 ms-1" data-copy="{{ url('/install') }}"><i class="bi bi-clipboard"></i> Copy</button>
</p>
@endsection
