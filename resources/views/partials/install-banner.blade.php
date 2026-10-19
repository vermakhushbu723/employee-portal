{{-- "Install app" prompt. Hidden until public/js/app.js decides it can be shown. --}}
<div class="install-banner d-none" id="installBanner" role="dialog" aria-label="Install app">
    <img src="{{ asset('icons/icon-192.png') }}" alt="" width="44" height="44">
    <div class="flex-grow-1 min-w-0">
        <div class="fw-bold">Install Employee Portal</div>
        <div class="small text-muted" data-install-text>Use it like an app from your home screen.</div>
    </div>
    <button type="button" class="btn btn-primary btn-sm" data-install-accept>Install</button>
    <button type="button" class="btn-icon" data-install-dismiss aria-label="Close"><i class="bi bi-x-lg"></i></button>
</div>
