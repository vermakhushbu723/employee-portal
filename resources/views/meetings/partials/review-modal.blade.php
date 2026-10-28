{{--
    Meeting review (admin / manager / team leader): close / keep open / reschedule + result + next tag.
    Opened with data-modal="#meetingReviewModal" data-action="{review url}" data-fill='{...}' (meetings list),
    or with $action set (inside the meeting room). Only this review closes a meeting.
--}}
<div class="modal fade" id="meetingReviewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <form method="POST" action="{{ $action ?? '' }}" class="modal-content rounded-4 border-0 text-dark" data-review-form>
            @csrf
            <div class="modal-header border-0">
                <div>
                    <h5 class="modal-title fw-bold mb-0">Meeting Review</h5>
                    <div class="small text-muted" data-field="title_text">{{ isset($m) ? $m->title.' — '.$m->customer_name : '' }}</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body pt-0">
                <div class="small fw-bold mb-2">1. Meeting status</div>
                <div class="outcome-pick mb-3">
                    @foreach (['close' => ['Close Meeting', 'check2-circle', 'danger'], 'open' => ['Keep Open', 'unlock', 'success'], 'reschedule' => ['Re-schedule', 'calendar2-week', 'primary']] as $key => [$alabel, $aicon, $atone])
                        <label class="outcome-opt tone-{{ $atone }}"><input type="radio" name="action" value="{{ $key }}" @checked($key === 'close') data-review-action><span><i class="bi bi-{{ $aicon }}"></i> {{ $alabel }}</span></label>
                    @endforeach
                </div>
                <div class="row g-2 mb-3 d-none" data-review-reschedule>
                    <div class="col-md-4"><label class="form-label small required">New date</label><input type="date" name="date" class="form-control" min="{{ today()->toDateString() }}"></div>
                    <div class="col-6 col-md-4"><label class="form-label small required">From time</label><input type="time" name="time" class="form-control" data-from-time></div>
                    <div class="col-6 col-md-4"><label class="form-label small required">To time</label><input type="time" name="end_time" class="form-control" data-to-time></div>
                    <div class="col-12 small-xs text-muted">The same join link works at the new time — the customer and employee use the link they already have.</div>
                </div>

                <div class="small fw-bold mb-2">2. Result</div>
                <div class="outcome-pick mb-3">
                    @foreach (\App\Services\MeetingPeopleService::OUTCOMES as $key => [$olabel, $otone, $oicon])
                        <label class="outcome-opt tone-{{ $otone }}"><input type="radio" name="outcome" value="{{ $key }}"><span><i class="bi bi-{{ $oicon }}"></i> {{ $olabel }}</span></label>
                    @endforeach
                </div>

                <div class="small fw-bold mb-2">3. Next tag</div>
                <div class="outcome-pick mb-3">
                    @foreach (\App\Models\Meeting::TAGS as $key => [$tlabel, $ttone, $ticon])
                        <label class="outcome-opt tone-{{ $ttone }}"><input type="radio" name="tag" value="{{ $key }}"><span><i class="bi bi-{{ $ticon }}"></i> {{ $tlabel }}</span></label>
                    @endforeach
                </div>
                <div class="small-xs text-muted mb-3"><i class="bi bi-info-circle"></i> The tag also updates the lead: Interested / Follow Up as named, Hire &amp; Lead Closed → Converted, Irrelevant → Not Interested, Fake Lead → Invalid.</div>

                <label class="form-label small">Note</label>
                <input type="text" name="outcome_note" maxlength="500" class="form-control" placeholder="e.g. Candidate selected, joining Monday">
                <div class="small-xs text-muted mt-2" data-field="connected_text">Connected time is counted automatically — only while two or more people were in the room.</div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-primary"><i class="bi bi-check2 me-1"></i>Save Review</button>
            </div>
        </form>
    </div>
</div>
