{{-- Update lead status. Opened via data-modal="#statusModal" (see public/js/app.js). --}}
<div class="modal fade" id="statusModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" action="{{ $action ?? '' }}" class="modal-content rounded-4 border-0">
            @csrf
            @method('PATCH')
            <div class="modal-header border-0">
                <div>
                    <h5 class="modal-title fw-bold mb-0">Update Lead Status</h5>
                    <div class="small text-muted" data-field="lead_name">{{ $leadName ?? '' }}</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body pt-0">
                <div class="mb-3">
                    <label class="form-label required">Status</label>
                    <select name="status" class="form-select @error('status') is-invalid @enderror" data-follow-up-toggle="#followUpField">
                        @foreach (\App\Enums\LeadStatus::cases() as $status)
                            <option value="{{ $status->value }}" @selected(old('status', $current ?? '') === $status->value)>{{ $status->label() }}</option>
                        @endforeach
                    </select>
                    @error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="mb-3" id="followUpField">
                    <label class="form-label required">Follow-up Date</label>
                    <input type="date" name="follow_up_date" value="{{ old('follow_up_date') }}" min="{{ today()->toDateString() }}" class="form-control @error('follow_up_date') is-invalid @enderror">
                    @error('follow_up_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div>
                    <label class="form-label">Call Remarks</label>
                    <textarea name="note" rows="2" maxlength="500" class="form-control" placeholder="What did the customer say?">{{ old('note') }}</textarea>
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-primary">Save</button>
            </div>
        </form>
    </div>
</div>
