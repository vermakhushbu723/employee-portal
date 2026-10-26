{{-- Give a lead-creation task. $taskAction = form URL, $taskUsers = employees who can receive it. --}}
<form method="POST" action="{{ $taskAction }}" class="card-soft card-pad mb-3">
    @csrf
    <div class="section-head">
        <h3 class="section-title"><i class="bi bi-file-earmark-plus me-1" style="color: var(--purple)"></i>Assign Lead Creation Task</h3>
        <span class="small text-muted">Shows on the employee's dashboard with progress</span>
    </div>
    @if ($errors->has('task_users') || $errors->has('title') || $errors->has('lead_target'))
        <div class="alert alert-danger small py-2">{{ $errors->first('task_users') ?: ($errors->first('title') ?: $errors->first('lead_target')) }}</div>
    @endif
    <div class="row g-2">
        <div class="col-md-5">
            <label class="form-label required">Task / file name</label>
            <input name="title" value="{{ old('title') }}" maxlength="150" class="form-control" placeholder="e.g. Loan_Lead_Creation_24Oct">
        </div>
        <div class="col-md-2">
            <label class="form-label required">Lead target</label>
            <input type="number" name="lead_target" min="1" max="1000" value="{{ old('lead_target', 20) }}" class="form-control">
        </div>
        <div class="col-md-5">
            <label class="form-label">Work Details</label>
            <input name="message" value="{{ old('message') }}" maxlength="1000" class="form-control" placeholder="e.g. Loan ke interested customers dhoond kar 20 new leads create karni hai.">
        </div>
    </div>
    <div class="row g-1 mt-2" style="max-height: 120px; overflow-y: auto">
        @forelse ($taskUsers as $emp)
            <div class="col-md-4 col-lg-3">
                <label class="d-flex align-items-center gap-2 small" style="cursor:pointer">
                    <input class="form-check-input m-0" type="checkbox" name="task_users[]" value="{{ $emp->id }}" @checked(in_array($emp->id, old('task_users', [])))> {{ $emp->name }} <span class="text-muted">{{ $emp->employee_code }}</span>
                </label>
            </div>
        @empty
            <div class="small text-muted">No employees available.</div>
        @endforelse
    </div>
    <button class="btn mt-2 text-white" style="background: var(--purple)"><i class="bi bi-send me-1"></i>Give Task</button>
</form>
