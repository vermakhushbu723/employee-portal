{{--
    Send Email popup (public/js/email-compose.js). Open with a button:
      <button data-email-compose='{"to":"a@b.com","name":"Ravi","title":"BDE Interview","date":"29 Oct 2026","time":"03:00 PM","link":"https://…","meeting_id":5}'>
    Every key is optional. Templates come from Email Templates (admin); placeholders are filled from the button.
    $emailSuggestions = optional list of known addresses (shown while typing in "To").
--}}
@php
    $composeTemplates = $composeTemplates ?? \App\Models\EmailTemplate::forCompose();
    $composeCtx = ['employee' => auth()->user()->name, 'company' => config('portal.company')];
    $replyTo = auth()->user()->replyEmail();
@endphp
<div class="modal fade" id="composeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <form class="modal-content rounded-4 border-0" method="POST" action="{{ route('emails.send') }}" enctype="multipart/form-data" data-compose-form
              data-templates='@json($composeTemplates)' data-ctx='@json($composeCtx)'>
            @csrf
            <input type="hidden" name="meeting_id">
            <div class="modal-header border-0 pb-1">
                <h5 class="modal-title fw-bold"><i class="bi bi-envelope-fill text-primary me-1"></i>Send Email</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body pt-1">
                <div class="mail-help mb-2">
                    <i class="bi bi-info-circle-fill"></i>
                    <div><b>Sent from:</b> {{ config('portal.company') }} company e-mail. <b>Replies go to:</b>
                        @if ($replyTo)<b class="text-success">{{ $replyTo }}</b>@else<span class="text-danger">not set</span> — <a href="{{ route('profile.show') }}#my-emails">connect your e-mail ID</a>@endif
                    </div>
                </div>
                <div class="row g-2">
                    <div class="col-12">
                        <label class="form-label small required">To <span class="text-muted">(one or more, separated by comma)</span></label>
                        <input type="text" name="to" class="form-control" required maxlength="500" placeholder="customer@gmail.com, manager@gmail.com" list="composeEmails" autocomplete="off">
                        @if (! empty($emailSuggestions))
                            <datalist id="composeEmails">@foreach ($emailSuggestions as $sug)<option value="{{ $sug['email'] }}">{{ $sug['label'] }}</option>@endforeach</datalist>
                        @endif
                    </div>
                    <div class="col-12">
                        <label class="form-label small">Template</label>
                        <div class="tpl-chips" data-compose-chips>
                            @foreach ($composeTemplates as $i => $tpl)
                                <button type="button" class="tpl-chip" data-tpl="{{ $i }}"><i class="bi bi-{{ (\App\Models\EmailTemplate::CATEGORIES[$tpl['category']] ?? \App\Models\EmailTemplate::CATEGORIES['general'])[1] }}"></i> {{ $tpl['name'] }}</button>
                            @endforeach
                            <button type="button" class="tpl-chip" data-tpl="">Write my own</button>
                        </div>
                    </div>
                    <div class="col-12"><label class="form-label small required">Subject</label><input name="subject" class="form-control" required maxlength="200"></div>
                    <div class="col-12"><label class="form-label small required">Message</label><textarea name="body" rows="9" class="form-control" required maxlength="10000"></textarea></div>
                    <div class="col-12">
                        <label class="attach-box"><i class="bi bi-paperclip"></i> <span data-compose-attach>Attach file (PDF, Word, image, Excel — max 10 MB)</span><input type="file" name="attachment" class="d-none" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.xlsx,.xls,.csv,.txt"></label>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-primary"><i class="bi bi-send-fill me-1"></i>Send Email</button>
            </div>
        </form>
    </div>
</div>
@push('scripts')<script src="{{ asset('js/email-compose.js') }}?v={{ filemtime(public_path('js/email-compose.js')) }}"></script>@endpush
