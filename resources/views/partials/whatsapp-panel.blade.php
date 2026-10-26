{{--
    WhatsApp mini panel (public/js/whatsapp-panel.js).
    WhatsApp Web refuses to load inside other websites (X-Frame-Options: DENY), so the QR cannot be shown
    inside this page. The panel opens WhatsApp Web in its own small window and controls it: dock left/right,
    size S/M/L, bring to front, close. The panel itself can be dragged, resized and minimized.
--}}
<div class="wa-panel d-none" id="waPanel" role="dialog" aria-label="WhatsApp">
    <div class="wa-head" data-wa-drag>
        <i class="bi bi-whatsapp"></i>
        <b class="flex-grow-1">WhatsApp</b>
        <span class="wa-state" data-wa-state>Not open</span>
        <button type="button" class="wa-btn" data-wa-min title="Minimize"><i class="bi bi-dash-lg"></i></button>
        <button type="button" class="wa-btn" data-wa-close title="Close panel"><i class="bi bi-x-lg"></i></button>
    </div>
    <div class="wa-body">
        <div class="wa-qr">
            <span class="wa-qr-icon"><i class="bi bi-qr-code"></i><i class="bi bi-whatsapp"></i></span>
            <div class="small">Open WhatsApp Web, then <b>scan the QR</b> with your phone.</div>
        </div>
        <button type="button" class="btn btn-success w-100 mb-2" data-wa-open><i class="bi bi-box-arrow-up-right me-1"></i>Open WhatsApp Web</button>

        <div class="wa-controls">
            <div class="small-xs text-muted fw-600 mb-1">Window position & size</div>
            <div class="d-flex gap-1 flex-wrap">
                <button type="button" class="btn btn-sm btn-light" data-wa-dock="left" title="Stick to the left side"><i class="bi bi-layout-sidebar"></i> Left</button>
                <button type="button" class="btn btn-sm btn-light" data-wa-dock="right" title="Stick to the right side"><i class="bi bi-layout-sidebar-reverse"></i> Right</button>
                <button type="button" class="btn btn-sm btn-light" data-wa-size="-1" title="Smaller"><i class="bi bi-dash-square"></i></button>
                <button type="button" class="btn btn-sm btn-light" data-wa-size="1" title="Bigger"><i class="bi bi-plus-square"></i></button>
                <button type="button" class="btn btn-sm btn-light" data-wa-front title="Bring the WhatsApp window to the front"><i class="bi bi-front"></i> Show</button>
                <button type="button" class="btn btn-sm btn-light text-danger" data-wa-quit title="Close the WhatsApp window"><i class="bi bi-power"></i></button>
            </div>
        </div>

        <ol class="wa-steps small">
            <li>Open <b>WhatsApp</b> on your mobile</li>
            <li>Tap <b>Menu ( ⋮ )</b> or Settings → <b>Linked Devices</b></li>
            <li>Tap <b>Link a Device</b></li>
            <li>Scan the QR code in the WhatsApp window</li>
        </ol>
        <div class="small-xs text-muted"><i class="bi bi-info-circle"></i> WhatsApp does not allow its page inside other websites, so it opens in its own small window. Once linked, it stays logged in.</div>
    </div>
</div>
