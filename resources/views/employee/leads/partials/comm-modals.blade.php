{{-- WhatsApp (templates), e-mail (templates + file), voice note and call-end popups. Driven by public/js/leads.js --}}
<div class="modal fade" id="waModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <form class="modal-content rounded-4 border-0" data-wa-form>
            <div class="modal-header border-0 pb-1">
                <h5 class="modal-title fw-bold"><i class="bi bi-whatsapp text-success me-1"></i>Send WhatsApp Message</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body pt-1">
                <div class="small text-muted mb-2">To <b data-to-name></b> · <span data-to-mobile></span></div>
                <div class="tpl-list" data-wa-templates><div class="text-center py-3"><div class="spinner-border spinner-border-sm text-success"></div></div></div>
                <textarea name="text" rows="4" class="form-control mt-2" maxlength="4000" required placeholder="Type or pick a message above"></textarea>
                <div class="small-xs text-muted mt-1"><i class="bi bi-info-circle"></i> Sent from your WhatsApp linked in the portal. Not linked? It opens WhatsApp with this message ready.</div>
            </div>
            <div class="modal-footer border-0"><button class="btn btn-success w-100"><i class="bi bi-send-fill me-1"></i>Send</button></div>
        </form>
    </div>
</div>

<div class="modal fade" id="emailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <form class="modal-content rounded-4 border-0" data-email-form enctype="multipart/form-data">
            <div class="modal-header border-0 pb-1">
                <h5 class="modal-title fw-bold"><i class="bi bi-envelope-fill text-primary me-1"></i>Send Email</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body pt-1">
                <div class="row g-2">
                    <div class="col-12"><label class="form-label small">To</label><input type="email" name="to" class="form-control form-control-sm" required></div>
                    <div class="col-12"><label class="form-label small">Title</label><select class="form-select form-select-sm" data-email-templates><option value="">— Choose a template —</option></select></div>
                    <div class="col-12"><label class="form-label small">Subject</label><input name="subject" class="form-control form-control-sm" required maxlength="200"></div>
                    <div class="col-12"><label class="form-label small">Message</label><textarea name="body" rows="7" class="form-control form-control-sm" required></textarea></div>
                    <div class="col-12">
                        <label class="attach-box"><i class="bi bi-paperclip"></i> <span data-attach-name>Attach File (Resume, Brochure, JD etc.)</span><input type="file" name="attachment" class="d-none" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.xlsx,.xls,.csv,.txt"></label>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0"><button class="btn btn-primary w-100"><i class="bi bi-send-fill me-1"></i>Send Email</button></div>
        </form>
    </div>
</div>

<div class="modal fade" id="voiceModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content rounded-4 border-0 text-center">
            <div class="modal-body py-4">
                <div class="fw-bold mb-1">Voice note</div>
                <div class="small text-muted mb-3" data-voice-name></div>
                <button type="button" class="rec-btn" data-rec-toggle><i class="bi bi-mic-fill"></i></button>
                <div class="fs-5 fw-bold mt-2 font-monospace" data-rec-time>00:00</div>
                <div class="small text-muted" data-rec-msg>Tap to start recording</div>
            </div>
            <div class="modal-footer border-0 justify-content-center"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button><button type="button" class="btn btn-danger d-none" data-rec-save><i class="bi bi-upload me-1"></i>Save</button></div>
        </div>
    </div>
</div>

<div class="modal fade" id="callEndModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <form class="modal-content rounded-4 border-0" data-call-end-form>
            <div class="modal-body text-center">
                <i class="bi bi-telephone-fill text-success fs-2"></i>
                <div class="fw-bold mt-1">Call with <span data-call-name></span></div>
                <div class="small text-muted">Talk time</div>
                <div class="fs-3 fw-bold font-monospace" data-call-time>00:00</div>
                <input type="text" name="note" class="form-control form-control-sm mt-2" maxlength="500" placeholder="Call note (optional)">
                <label class="call-rec-pick mt-2">
                    <input type="file" name="recording" accept="audio/*,.amr,.m4a,.3gp,.opus" class="d-none" data-call-rec>
                    <i class="bi bi-mic-fill"></i> <span data-call-rec-name>Attach call recording (optional)</span>
                </label>
                <div class="small-xs text-muted">Your phone saves it in Recordings / Call recordings. Management can listen to it.</div>
            </div>
            <div class="modal-footer border-0"><button class="btn btn-success w-100">Save call</button></div>
        </form>
    </div>
</div>
