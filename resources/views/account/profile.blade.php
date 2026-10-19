@extends('layouts.app')

@use('App\Support\Duration')

@section('title', 'My Profile')
@if ($errors->password->any())
    @section('reopen-modal', '#passwordModal')
@endif

@section('content')
<div class="row g-3">
    <div class="col-lg-5">
        <div class="profile-hero mb-3">
            <span class="avatar xl">{{ $user->initials }}</span>
            <div class="min-w-0">
                <h2 class="h4 fw-bold mb-0">{{ $user->name }}</h2>
                <div class="opacity-75">{{ $user->role->label() }}</div>
                <div class="opacity-75 small">{{ $user->employee_code }} | {{ $user->employee?->designation ?? $user->role->label() }}</div>
                <span class="badge rounded-pill bg-success mt-2"><i class="bi bi-check-circle me-1"></i>{{ ucfirst($user->status) }}</span>
            </div>
        </div>

        <div class="card-soft card-pad mb-3">
            <h3 class="section-title mb-2">Personal & Job Details</h3>
            <div class="kv-row"><span class="k"><i class="bi bi-telephone"></i>Mobile</span><span class="v">{{ $user->mobile ?? '—' }}</span></div>
            <div class="kv-row"><span class="k"><i class="bi bi-envelope"></i>Email</span><span class="v text-break">{{ $user->email ?? '—' }}</span></div>
            <div class="kv-row"><span class="k"><i class="bi bi-diagram-3"></i>Department</span><span class="v">{{ $user->employee?->department?->name ?? '—' }}</span></div>
            <div class="kv-row"><span class="k"><i class="bi bi-person-badge"></i>Designation</span><span class="v">{{ $user->employee?->designation ?? '—' }}</span></div>
            <div class="kv-row"><span class="k"><i class="bi bi-person-check"></i>Reporting Manager</span><span class="v">{{ $user->employee?->manager?->name ?? '—' }}</span></div>
            @if ($user->employee?->training_from)
                <div class="kv-row">
                    <span class="k"><i class="bi bi-mortarboard"></i>Training Period</span>
                    <span class="v">{{ $user->employee->training_from->format('d M Y') }} – {{ $user->employee->training_to?->format('d M Y') ?? '—' }}</span>
                </div>
            @endif
            <div class="kv-row"><span class="k"><i class="bi bi-calendar-check"></i>Joining Date</span><span class="v">{{ $user->employee?->joining_date?->format('d M Y') ?? '—' }}</span></div>
            <div class="kv-row"><span class="k"><i class="bi bi-clock"></i>Shift</span><span class="v">{{ $shift->label() }} ({{ Duration::minutes($shift->required_minutes) }})</span></div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card-soft card-pad mb-3">
            <div class="section-head">
                <h3 class="section-title">Announcements & Alerts</h3>
                <a href="{{ route('notifications.index') }}" class="small fw-600">View All</a>
            </div>
            @forelse ($announcements as $announcement)
                @include('account.partials.announcement', ['announcement' => $announcement])
            @empty
                <x-empty-state icon="megaphone" title="No announcements" />
            @endforelse
        </div>

        <div class="card-soft card-pad">
            <a href="#" class="menu-link" data-bs-toggle="modal" data-bs-target="#passwordModal">
                <i class="bi bi-lock"></i> Change Password <i class="bi bi-chevron-right"></i>
            </a>
            <a href="{{ route('notifications.index') }}" class="menu-link">
                <i class="bi bi-bell"></i> Notifications <i class="bi bi-chevron-right"></i>
            </a>
            @if ($user->role->portal() === 'employee')
                <a href="{{ route('employee.attendance.history') }}" class="menu-link">
                    <i class="bi bi-calendar3"></i> My Attendance <i class="bi bi-chevron-right"></i>
                </a>
            @endif
            <form method="POST" action="{{ route('logout') }}" class="mt-3">
                @csrf
                <button class="btn btn-outline-danger w-100 btn-lg"><i class="bi bi-box-arrow-right me-2"></i>Logout</button>
            </form>
            <p class="small-xs text-muted text-center mt-2 mb-0">Logging out does not check you out of attendance.</p>
        </div>
    </div>
</div>

<div class="modal fade" id="passwordModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" action="{{ route('profile.password') }}" class="modal-content rounded-4 border-0">
            @csrf
            @method('PUT')
            <div class="modal-header border-0">
                <h5 class="modal-title fw-bold">Change Password</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body pt-0">
                <div class="mb-3">
                    <label class="form-label required">Current Password</label>
                    <input type="password" name="current_password" class="form-control @error('current_password', 'password') is-invalid @enderror" autocomplete="current-password">
                    @error('current_password', 'password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="mb-3">
                    <label class="form-label required">New Password</label>
                    <input type="password" name="password" class="form-control @error('password', 'password') is-invalid @enderror" autocomplete="new-password">
                    @error('password', 'password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div>
                    <label class="form-label required">Confirm New Password</label>
                    <input type="password" name="password_confirmation" class="form-control" autocomplete="new-password">
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-primary">Update Password</button>
            </div>
        </form>
    </div>
</div>
@endsection
