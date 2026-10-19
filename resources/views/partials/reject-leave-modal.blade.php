<div class="modal fade" id="rejectLeaveModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" action="" class="modal-content rounded-4 border-0">
            @csrf
            <div class="modal-header border-0">
                <div>
                    <h5 class="modal-title fw-bold mb-0">Reject Leave</h5>
                    <div class="small text-muted" data-field="employee"></div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body pt-0">
                <label class="form-label required">Rejection Reason</label>
                <textarea name="rejection_reason" rows="3" maxlength="300" class="form-control" required placeholder="e.g. Workload / critical period"></textarea>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-danger">Reject Leave</button>
            </div>
        </form>
    </div>
</div>
