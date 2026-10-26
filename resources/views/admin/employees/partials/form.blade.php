{{-- Shared Add / Edit employee form. $user is null when creating. --}}
@php
    $user ??= null;
    $emp = $user?->employee;
    $date = fn ($value) => $value?->toDateString();
    $isSelf = $user && $user->is(auth()->user());
    $currentRole = old('role', $user?->custom_role_id ? "custom:{$user->custom_role_id}" : ($user?->role->value ?? 'employee'));
    $roleGroups = [
        'Employee portal' => ['employee'],
        'Manager portal' => ['manager', 'team_leader'],
        'Admin portal (full access)' => ['hr', 'super_admin'],
    ];
    $customRoles ??= collect();
    $currentShift = old('shift_id', $emp?->shift_id ?? optional($shifts->firstWhere('is_default', true))->id);
@endphp

<div class="col-lg-6">
    <div class="card-soft card-pad h-100">
        <h3 class="section-title mb-3">Login Account</h3>
        <div class="row g-3">
            <div class="col-md-5">
                <label class="form-label required">Employee ID</label>
                <input name="employee_code" value="{{ old('employee_code', $user?->employee_code) }}" class="form-control text-uppercase @error('employee_code') is-invalid @enderror" placeholder="EMP004">
                @error('employee_code')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-7">
                <label class="form-label required">Full Name</label>
                <input name="name" value="{{ old('name', $user?->name) }}" class="form-control @error('name') is-invalid @enderror">
                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6">
                <label class="form-label">Email</label>
                <input type="email" name="email" value="{{ old('email', $user?->email) }}" class="form-control @error('email') is-invalid @enderror">
                @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6">
                <label class="form-label">Mobile</label>
                <input name="mobile" value="{{ old('mobile', $user?->mobile) }}" maxlength="10" class="form-control @error('mobile') is-invalid @enderror">
                @error('mobile')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6">
                <label class="form-label required">Role</label>
                <select name="role" class="form-select @error('role') is-invalid @enderror" data-role-warning="#roleWarning" @disabled($isSelf)>
                    @foreach ($roleGroups as $group => $values)
                        <optgroup label="{{ $group }}">
                            @foreach ($values as $value)
                                <option value="{{ $value }}" @selected($currentRole === $value)>{{ \App\Enums\Role::from($value)->label() }}</option>
                            @endforeach
                            {{-- Admin-made roles that work like this portal --}}
                            @foreach ($customRoles->filter(fn ($r) => in_array($r->base_role->value, $values, true)) as $cr)
                                <option value="custom:{{ $cr->id }}" data-base="{{ $cr->base_role->value }}" @selected($currentRole === "custom:{$cr->id}")>{{ $cr->name }} (custom)</option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
                <a href="{{ route('admin.roles.index') }}" class="small fw-600" target="_blank"><i class="bi bi-plus-circle"></i> Create a new role</a>
                @if ($isSelf)
                    <input type="hidden" name="role" value="{{ $currentRole }}">
                    <div class="form-text">You cannot change your own role.</div>
                @endif
                @error('role')<div class="invalid-feedback">{{ $message }}</div>@enderror
                <div id="roleWarning" class="small text-danger fw-600 mt-1 d-none">
                    <i class="bi bi-exclamation-triangle-fill"></i> This person will open the <u>Admin portal</u>, not the employee dashboard.
                </div>
            </div>
            <div class="col-md-6">
                <label class="form-label {{ $user ? '' : 'required' }}">{{ $user ? 'New Password' : 'Temporary Password' }}</label>
                <input type="password" name="password" class="form-control @error('password') is-invalid @enderror" autocomplete="new-password" placeholder="{{ $user ? 'Leave blank to keep current' : '' }}">
                @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>
    </div>
</div>

<div class="col-lg-6">
    <div class="card-soft card-pad h-100">
        <h3 class="section-title mb-3">Job & Shift</h3>
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Department</label>
                <select name="department_id" class="form-select">
                    <option value="">—</option>
                    @foreach ($departments as $department)
                        <option value="{{ $department->id }}" @selected(old('department_id', $emp?->department_id) == $department->id)>{{ $department->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label">Designation</label>
                <input name="designation" value="{{ old('designation', $emp?->designation) }}" class="form-control" placeholder="Telecaller">
            </div>
            <div class="col-md-6">
                <label class="form-label">Reporting Manager</label>
                <select name="manager_id" class="form-select @error('manager_id') is-invalid @enderror">
                    <option value="">—</option>
                    @foreach ($managers as $manager)
                        <option value="{{ $manager->id }}" @selected(old('manager_id', $emp?->manager_id) == $manager->id)>{{ $manager->name }} ({{ $manager->role->label() }})</option>
                    @endforeach
                </select>
                @error('manager_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6">
                <label class="form-label">Shift</label>
                <select name="shift_id" class="form-select @error('shift_id') is-invalid @enderror" data-custom-shift="#customShift">
                    @foreach ($shifts as $shift)
                        <option value="{{ $shift->id }}" @selected((string) $currentShift === (string) $shift->id)>{{ $shift->is_custom ? $shift->name : $shift->name.' ('.$shift->label().')' }} · {{ rtrim(rtrim(number_format($shift->required_minutes / 60, 1), '0'), '.') }}h</option>
                    @endforeach
                    <option value="custom" @selected($currentShift === 'custom')>+ Custom shift (set start / end time)</option>
                </select>
            </div>
            <div class="col-12 {{ $currentShift === 'custom' ? '' : 'd-none' }}" id="customShift">
                <div class="row g-2 p-2 rounded-3" style="background: var(--brand-soft)">
                    <div class="col-6 col-md-3">
                        <label class="form-label">Start time</label>
                        <input type="time" name="custom_start" value="{{ old('custom_start', '10:00') }}" class="form-control form-control-sm @error('custom_start') is-invalid @enderror">
                    </div>
                    <div class="col-6 col-md-3">
                        <label class="form-label">End time</label>
                        <input type="time" name="custom_end" value="{{ old('custom_end', '19:00') }}" class="form-control form-control-sm @error('custom_end') is-invalid @enderror">
                    </div>
                    <div class="col-6 col-md-3">
                        <label class="form-label">Working hours</label>
                        <input type="number" name="custom_hours" step="0.5" min="1" max="16" value="{{ old('custom_hours', 8) }}" class="form-control form-control-sm @error('custom_hours') is-invalid @enderror">
                    </div>
                    <div class="col-6 col-md-3">
                        <label class="form-label">Break (minutes)</label>
                        <input type="number" name="custom_break" min="0" max="180" value="{{ old('custom_break', 60) }}" class="form-control form-control-sm">
                    </div>
                    @foreach (['custom_start', 'custom_end', 'custom_hours'] as $f)
                        @error($f)<div class="col-12 small text-danger">{{ $message }}</div>@enderror
                    @endforeach
                    <div class="col-12 small text-muted">On-time punch-in: 5 min before start up to 15 min after start.</div>
                </div>
            </div>
            <div class="col-md-6">
                <label class="form-label required">Work Type</label>
                <select name="work_type" class="form-select">
                    @foreach (['work_from_home' => 'Work From Home', 'office' => 'Office', 'field' => 'Field Work'] as $value => $label)
                        <option value="{{ $value }}" @selected(old('work_type', $emp?->work_type ?? 'work_from_home') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label">Monthly Salary</label>
                <input type="number" name="monthly_salary" value="{{ old('monthly_salary', $emp?->monthly_salary) }}" min="0" class="form-control">
            </div>
            @php
                $dailyTargets = $user ? app(\App\Services\WorkScoreService::class)->baseTargets($user) : config('portal.work_score.default_targets');
                $taskExtra = $user ? app(\App\Services\WorkAssignmentService::class)->openTargets($user) : ['leads' => 0, 'interested' => 0];
            @endphp
            <div class="col-md-6">
                <label class="form-label">Daily Lead Creation Target</label>
                <input type="number" name="target_leads" min="0" max="1000" value="{{ old('target_leads', $dailyTargets['leads']) }}" class="form-control @error('target_leads') is-invalid @enderror">
            </div>
            <div class="col-md-6">
                <label class="form-label">Daily Interested / Mature Target</label>
                <input type="number" name="target_interested" min="0" max="1000" value="{{ old('target_interested', $dailyTargets['interested']) }}" class="form-control @error('target_interested') is-invalid @enderror">
            </div>
            @if ($taskExtra['leads'] || $taskExtra['interested'])
                <div class="col-12">
                    <div class="info-box"><i class="bi bi-graph-up-arrow"></i>
                        <div>Today's target is higher because of open work:
                            @if ($taskExtra['interested']) <b>+{{ $taskExtra['interested'] }} interested</b> (shared file) @endif
                            @if ($taskExtra['leads']) <b>+{{ $taskExtra['leads'] }} leads</b> (lead creation task) @endif
                            — until that work is completed. Today = {{ $dailyTargets['leads'] + $taskExtra['leads'] }} leads + {{ $dailyTargets['interested'] + $taskExtra['interested'] }} interested.</div>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>

<div class="col-12">
    <div class="card-soft card-pad">
        <div class="section-head">
            <h3 class="section-title">Training & Joining</h3>
            <span class="small text-muted">Training period comes before the joining date</span>
        </div>
        <div class="row g-3 align-items-end">
            <div class="col-md-4">
                <label class="form-label" for="training_from">Training Date — From</label>
                <div class="input-icon">
                    <i class="bi bi-mortarboard"></i>
                    <input type="date" id="training_from" name="training_from" value="{{ old('training_from', $date($emp?->training_from)) }}" class="form-control @error('training_from') is-invalid @enderror">
                </div>
                @error('training_from')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4">
                <label class="form-label" for="training_to">Training Date — To</label>
                <div class="input-icon">
                    <i class="bi bi-mortarboard-fill"></i>
                    <input type="date" id="training_to" name="training_to" value="{{ old('training_to', $date($emp?->training_to)) }}" class="form-control @error('training_to') is-invalid @enderror">
                </div>
                @error('training_to')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4">
                <label class="form-label" for="joining_date">Joining Date</label>
                <div class="input-icon">
                    <i class="bi bi-calendar-check"></i>
                    <input type="date" id="joining_date" name="joining_date" value="{{ old('joining_date', $date($emp?->joining_date) ?? ($user ? '' : today()->toDateString())) }}" class="form-control @error('joining_date') is-invalid @enderror">
                </div>
                @error('joining_date')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>
        </div>
    </div>
</div>
