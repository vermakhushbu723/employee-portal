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
                <select name="department_id" class="form-select" data-dept-select>
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
                <select name="manager_id" class="form-select @error('manager_id') is-invalid @enderror" data-manager-select>
                    <option value="">—</option>
                    @foreach ($managers as $manager)
                        <option value="{{ $manager->id }}" data-dept="{{ $manager->employee?->department_id }}" @selected(old('manager_id', $emp?->manager_id) == $manager->id)>{{ $manager->name }} ({{ $manager->role->label() }}){{ $manager->employee?->department ? ' · '.$manager->employee->department->name : '' }}</option>
                    @endforeach
                </select>
                <div class="small-xs text-muted mt-1" data-manager-hint>Managers / team leaders of the selected department.</div>
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
                <div class="row g-2 p-2 rounded-3" style="background: var(--brand-soft)" data-shift-calc>
                    <div class="col-6 col-md-3">
                        <label class="form-label">Start time</label>
                        <input type="time" name="custom_start" value="{{ old('custom_start', '10:00') }}" class="form-control form-control-sm @error('custom_start') is-invalid @enderror">
                    </div>
                    <div class="col-6 col-md-3">
                        <label class="form-label">End time</label>
                        <input type="time" name="custom_end" value="{{ old('custom_end', '19:00') }}" class="form-control form-control-sm @error('custom_end') is-invalid @enderror">
                    </div>
                    <div class="col-6 col-md-3">
                        <label class="form-label">Working hours <span class="text-muted fw-normal">(auto)</span></label>
                        <input type="text" name="custom_hours" value="{{ old('custom_hours', 8) }}" class="form-control form-control-sm bg-white fw-bold @error('custom_hours') is-invalid @enderror" readonly tabindex="-1" data-shift-hours>
                    </div>
                    <div class="col-6 col-md-3">
                        <label class="form-label">Break (minutes)</label>
                        <input type="number" name="custom_break" min="0" max="180" value="{{ old('custom_break', 60) }}" class="form-control form-control-sm">
                    </div>
                    @foreach (['custom_start', 'custom_end', 'custom_hours'] as $f)
                        @error($f)<div class="col-12 small text-danger">{{ $message }}</div>@enderror
                    @endforeach
                    <div class="col-12 small text-muted"><span data-shift-note>Working hours = shift time − break.</span> On-time punch-in: 5 min before start up to 15 min after start.</div>
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
            <div class="col-md-6">
                <label class="form-label">Daily Calls Target</label>
                <input type="number" name="target_calls" min="0" max="2000" value="{{ old('target_calls', $user ? ($user->targets()->where('period', 'daily')->where('metric', 'calls')->value('target_value') ?? config('portal.work_score.default_calls_target')) : config('portal.work_score.default_calls_target')) }}" class="form-control">
            </div>

            {{-- Location: mandatory = the portal / app does not open (and login is refused) without location --}}
            <div class="col-12">
                <label class="form-label mb-1">Location</label>
                <div class="seg-choice">
                    <label><input type="radio" name="location_required" value="0" @checked(! old('location_required', $user?->location_required ? '1' : '0'))><span><i class="bi bi-geo"></i> Optional</span></label>
                    <label><input type="radio" name="location_required" value="1" @checked(old('location_required', $user?->location_required ? '1' : '0') === '1')><span><i class="bi bi-geo-alt-fill"></i> Mandatory</span></label>
                </div>
                <div class="small text-muted mt-1"><b>Mandatory:</b> login is refused and the system / mobile app does not open until location access is allowed. <b>Optional:</b> a reminder only.</div>
            </div>

            {{-- Employee sections for this ID (managers / team leaders get only the ticked ones) --}}
            @php
                $featureList = \App\Models\User::FEATURES;
                $featureDefault = $user ? collect($featureList)->keys()->filter(fn ($f) => $user->hasFeature($f))->values()->all() : array_keys($featureList);
                $featureValues = old('features_sent') ? (array) old('features', []) : $featureDefault;
            @endphp
            <div class="col-12">
                <label class="form-label mb-1">Employee sections for this ID</label>
                <div class="small text-muted mb-2">Employees normally have all of them. Tick what a <b>Manager / Team Leader</b> should also get — e.g. punch in / out with login, active and inactive time.</div>
                <input type="hidden" name="features_sent" value="1">
                <div class="feature-checks">
                    @foreach ($featureList as $key => [$flabel, $ficon, $fhint])
                        <label class="feature-check">
                            <input type="checkbox" name="features[]" value="{{ $key }}" class="form-check-input m-0" @checked(in_array($key, $featureValues, true))>
                            <i class="bi bi-{{ $ficon }}"></i>
                            <span><b>{{ $flabel }}</b><small>{{ $fhint }}</small></span>
                        </label>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>

<div class="col-12">
    <div class="card-soft card-pad">
        <div class="section-head">
            <h3 class="section-title">Training & Joining</h3>
            @php $noTraining = old('no_training', $user && ! $emp?->training_from ? '1' : null); @endphp
            <label class="no-training-switch">
                <input type="checkbox" name="no_training" value="1" class="form-check-input m-0" @checked($noTraining) data-no-training>
                <span>No training</span>
            </label>
        </div>
        <div class="row g-3 align-items-end" data-training-box>
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
                <label class="form-label" for="joining_date">Joining Date <span class="small text-muted fw-normal" data-join-hint></span></label>
                <div class="input-icon">
                    <i class="bi bi-calendar-check"></i>
                    <input type="date" id="joining_date" name="joining_date" value="{{ old('joining_date', $date($emp?->joining_date) ?? ($user ? '' : today()->toDateString())) }}" class="form-control @error('joining_date') is-invalid @enderror">
                </div>
                @error('joining_date')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>
            <div class="col-12 small text-muted" data-training-note>With training: joining is set to the next working day after training ends (it moves forward if training days are missed). No training: work starts from the joining date.</div>
        </div>
    </div>
</div>
