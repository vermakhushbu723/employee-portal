{{--
    WhatsApp inside the portal (public/js/whatsapp-panel.js).
    Each user links their own WhatsApp by scanning the QR here; chats and messages then show in this box.
    Served by the WhatsApp gateway on the server (App\Services\WhatsAppGateway).
--}}
@php
    $waUrls = collect(['start', 'status', 'chats', 'messages', 'send', 'logout'])->mapWithKeys(fn ($a) => [$a => route('wa.'.$a)]);
@endphp
<aside class="wa-side d-none" id="waPanel" aria-label="WhatsApp" data-urls='@json($waUrls)'>
    <div class="wa-side-head">
        <button type="button" class="wa-btn d-none" data-wa-back title="Back to chats"><i class="bi bi-arrow-left"></i></button>
        <i class="bi bi-whatsapp" data-wa-logo></i>
        <div class="flex-grow-1 min-w-0">
            <b class="d-block text-truncate" data-wa-title>WhatsApp</b>
            <span class="small-xs text-muted d-block text-truncate" data-wa-sub></span>
        </div>
        <div class="dropdown d-none" data-wa-menu>
            <button type="button" class="wa-btn" data-bs-toggle="dropdown" title="Menu"><i class="bi bi-three-dots-vertical"></i></button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><button type="button" class="dropdown-item" data-wa-new><i class="bi bi-chat-square-text me-2"></i>New chat</button></li>
                <li><button type="button" class="dropdown-item text-danger" data-wa-logout><i class="bi bi-box-arrow-right me-2"></i>Log out WhatsApp</button></li>
            </ul>
        </div>
        <button type="button" class="wa-btn" data-wa-min title="Minimize"><i class="bi bi-dash-lg"></i></button>
        <button type="button" class="wa-btn" data-wa-max title="Maximize"><i class="bi bi-arrows-angle-expand"></i></button>
        <button type="button" class="wa-btn" data-wa-close title="Close"><i class="bi bi-x-lg"></i></button>
    </div>

    <div class="wa-side-body" data-wa-body>
        {{-- Loading / service starting --}}
        <div class="wa-view text-center py-5" data-view="loading">
            <div class="spinner-border text-success"></div>
            <div class="small text-muted mt-3" data-wa-loading-text>Connecting to WhatsApp…</div>
        </div>

        {{-- Not linked yet: real QR --}}
        <div class="wa-view d-none" data-view="qr">
            <div class="wa-hero">
                <span class="wa-hero-art"><i class="bi bi-laptop"></i><i class="bi bi-whatsapp"></i></span>
                <div class="small">Use WhatsApp on {{ str_contains(request()->userAgent() ?? '', 'Mac') ? 'Mac' : 'Windows' }} with Employee Portal</div>
            </div>
            <div class="wa-login-card">
                <div class="wa-qr-real">
                    <img alt="WhatsApp QR code" data-wa-qr>
                    <span class="wa-qr-logo"><i class="bi bi-whatsapp"></i></span>
                </div>
                <h6 class="fw-bold mt-3 mb-2">Scan to log in</h6>
                <ol class="wa-steps small">
                    <li>Open WhatsApp on your phone</li>
                    <li>Tap the <b>Menu ⋮</b> or <b>Settings ⚙</b></li>
                    <li>Tap <b>Linked Devices</b> → <b>Link a device</b></li>
                    <li>Point your phone at this QR code</li>
                </ol>
                <div class="small-xs text-muted"><i class="bi bi-arrow-repeat"></i> The QR code refreshes by itself. Once linked, you stay signed in.</div>
            </div>
            <div class="small-xs text-muted text-center mt-2"><i class="bi bi-lock-fill"></i> Only you can see your chats here</div>
        </div>

        {{-- Linked: chat list --}}
        <div class="wa-view d-none" data-view="chats">
            <div class="wa-search">
                <i class="bi bi-search"></i>
                <input type="search" placeholder="Search or start new chat" data-wa-search>
            </div>
            <div class="wa-chat-list" data-wa-chats></div>
        </div>

        {{-- One conversation --}}
        <div class="wa-view d-none" data-view="chat">
            <div class="wa-messages" data-wa-messages></div>
            <form class="wa-compose" data-wa-compose>
                <input type="text" maxlength="4000" placeholder="Type a message" autocomplete="off">
                <button aria-label="Send"><i class="bi bi-send-fill"></i></button>
            </form>
        </div>

        {{-- New chat by number --}}
        <div class="wa-view d-none" data-view="new">
            <form class="wa-login-card text-start" data-wa-new-form>
                <h6 class="fw-bold">New chat</h6>
                <label class="form-label small">Mobile number</label>
                <input type="tel" name="number" class="form-control mb-2" placeholder="98765 43210" required maxlength="20">
                <label class="form-label small">Message</label>
                <textarea name="text" rows="3" class="form-control mb-3" required maxlength="4000"></textarea>
                <button class="btn btn-success w-100"><i class="bi bi-send me-1"></i>Send</button>
            </form>
        </div>

        {{-- Service problem --}}
        <div class="wa-view d-none text-center py-5" data-view="error">
            <i class="bi bi-wifi-off fs-1 text-muted"></i>
            <div class="small text-muted mt-2" data-wa-error>WhatsApp service is not available right now.</div>
            <button type="button" class="btn btn-sm btn-success mt-3" data-wa-retry>Try again</button>
        </div>
    </div>
</aside>
