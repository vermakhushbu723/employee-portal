{{-- Date range / department / manager / employee filters. $action = form URL, $showDepartment = bool --}}
<form method="GET" action="{{ $action }}" class="card-soft card-pad mb-3 d-flex flex-wrap gap-2 align-items-end">
    <div>
        <label class="form-label">From</label>
        <input type="date" name="from" value="{{ $from->toDateString() }}" class="form-control form-control-sm">
    </div>
    <div>
        <label class="form-label">To</label>
        <input type="date" name="to" value="{{ $to->toDateString() }}" class="form-control form-control-sm">
    </div>
    @if ($showDepartment ?? true)
        <div>
            <label class="form-label">Department</label>
            <select name="department" class="form-select form-select-sm">
                <option value="">All departments</option>
                @foreach ($allDepartments as $d)
                    <option value="{{ $d->id }}" @selected($filters['department'] === $d->id)>{{ $d->name }}</option>
                @endforeach
            </select>
        </div>
    @endif
    <div>
        <label class="form-label">Manager</label>
        <select name="manager" class="form-select form-select-sm">
            <option value="">All managers</option>
            @foreach ($allManagers as $m)
                <option value="{{ $m->id }}" @selected($filters['manager'] === $m->id)>{{ $m->name }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="form-label">Employee</label>
        <select name="employee" class="form-select form-select-sm">
            <option value="">All employees</option>
            @foreach ($allEmployees as $e)
                <option value="{{ $e->id }}" @selected($filters['employee'] === $e->id)>{{ $e->name }} ({{ $e->employee_code }})</option>
            @endforeach
        </select>
    </div>
    <button class="btn btn-primary btn-sm px-3">Apply</button>
</form>
