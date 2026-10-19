@extends('layouts.app')

@section('title', 'Add Employee')
@section('back', route('admin.employees.index'))

@section('content')
<form method="POST" action="{{ route('admin.employees.store') }}" class="row g-3" novalidate>
    @csrf
    <div class="col-lg-6">
        <div class="card-soft card-pad h-100">
            <h3 class="section-title mb-3">Login Account</h3>
            <div class="row g-3">
                <div class="col-md-5">
                    <label class="form-label required">Employee ID</label>
                    <input name="employee_code" value="{{ old('employee_code') }}" class="form-control text-uppercase @error('employee_code') is-invalid @enderror" placeholder="EMP004">
                    @error('employee_code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-7">
                    <label class="form-label required">Full Name</label>
                    <input name="name" value="{{ old('name') }}" class="form-control @error('name') is-invalid @enderror">
                    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" value="{{ old('email') }}" class="form-control @error('email') is-invalid @enderror">
                    @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">Mobile</label>
                    <input name="mobile" value="{{ old('mobile') }}" maxlength="10" class="form-control @error('mobile') is-invalid @enderror">
                    @error('mobile')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label required">Role</label>
                    <select name="role" class="form-select @error('role') is-invalid @enderror">
                        @foreach ($roles as $role)
                            <option value="{{ $role->value }}" @selected(old('role', 'employee') === $role->value)>{{ $role->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label required">Temporary Password</label>
                    <input type="password" name="password" class="form-control @error('password') is-invalid @enderror" autocomplete="new-password">
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
                            <option value="{{ $department->id }}" @selected(old('department_id') == $department->id)>{{ $department->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Designation</label>
                    <input name="designation" value="{{ old('designation') }}" class="form-control" placeholder="Telecaller">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Reporting Manager</label>
                    <select name="manager_id" class="form-select">
                        <option value="">—</option>
                        @foreach ($managers as $manager)
                            <option value="{{ $manager->id }}" @selected(old('manager_id') == $manager->id)>{{ $manager->name }} ({{ $manager->role->label() }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Shift</label>
                    <select name="shift_id" class="form-select">
                        @foreach ($shifts as $shift)
                            <option value="{{ $shift->id }}" @selected(old('shift_id', $shift->is_default ? $shift->id : null) == $shift->id)>{{ $shift->name }} ({{ $shift->label() }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label required">Work Type</label>
                    <select name="work_type" class="form-select">
                        @foreach (['work_from_home' => 'Work From Home', 'office' => 'Office', 'field' => 'Field Work'] as $value => $label)
                            <option value="{{ $value }}" @selected(old('work_type', 'work_from_home') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Monthly Salary</label>
                    <input type="number" name="monthly_salary" value="{{ old('monthly_salary') }}" min="0" class="form-control">
                </div>
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
                        <input type="date" id="training_from" name="training_from" value="{{ old('training_from') }}" class="form-control @error('training_from') is-invalid @enderror">
                    </div>
                    @error('training_from')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="training_to">Training Date — To</label>
                    <div class="input-icon">
                        <i class="bi bi-mortarboard-fill"></i>
                        <input type="date" id="training_to" name="training_to" value="{{ old('training_to') }}" class="form-control @error('training_to') is-invalid @enderror">
                    </div>
                    @error('training_to')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="joining_date">Joining Date</label>
                    <div class="input-icon">
                        <i class="bi bi-calendar-check"></i>
                        <input type="date" id="joining_date" name="joining_date" value="{{ old('joining_date', today()->toDateString()) }}" class="form-control @error('joining_date') is-invalid @enderror">
                    </div>
                    @error('joining_date')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>
    </div>

    <div class="col-12">
        <button class="btn btn-primary btn-lg"><i class="bi bi-check-lg me-1"></i>Create Account</button>
    </div>
</form>
@endsection
