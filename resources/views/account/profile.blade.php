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
                <div class="opacity-75">{{ $user->roleLabel() }}</div>
                <div class="opacity-75 small">{{ $user->employee_code }} | {{ $user->employee?->designation ?? $user->roleLabel() }}</div>
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
        {{-- My E-mail IDs: connect up to 4, each confirmed with a code sent to it --}}
        <div class="card-soft card-pad mb-3" id="my-emails">
            <div class="section-head">
                <h3 class="section-title mb-0"><i class="bi bi-envelope-at me-1"></i>My E-mail IDs</h3>
                <span class="small text-muted">{{ $emails->count() }} / {{ \App\Models\UserEmail::MAX_PER_USER }}</span>
            </div>
            <div class="small text-muted mb-2">Connect your own e-mail IDs (up to {{ \App\Models\UserEmail::MAX_PER_USER }}). We send a 6-digit code to confirm each one. Your <b>primary</b> ID is used as <b>Reply-to</b> on e-mails you send to customers, so their answers come to you.</div>
            @if ($errors->has('email') || $errors->has('otp'))<div class="alert alert-danger small py-2 mb-2">{{ $errors->first('email') ?: $errors->first('otp') }}</div>@endif
            @foreach ($emails as $em)
                <div class="email-row">
                    <i class="bi bi-envelope{{ $em->isVerified() ? '-check-fill text-success' : ' text-muted' }}"></i>
                    <div class="flex-grow-1 min-w-0">
                        <div class="fw-600 text-truncate">{{ $em->email }}</div>
                        <div class="small-xs">
                            @if ($em->isVerified())<span class="text-success">Verified</span>@if ($em->is_primary) · <x-badge tone="primary">Primary</x-badge>@endif
                            @else<span class="text-warning">Waiting for code</span>@endif
                        </div>
                    </div>
                    @if ($em->isVerified() && ! $em->is_primary)
                        <form method="POST" action="{{ route('profile.emails.primary', $em) }}">@csrf<button class="btn btn-sm btn-light">Make primary</button></form>
                    @endif
                    <form method="POST" action="{{ route('profile.emails.destroy', $em) }}" data-confirm="Remove {{ $em->email }}?">@csrf @method('DELETE')<button class="btn btn-sm btn-light text-danger" aria-label="Remove {{ $em->email }}"><i class="bi bi-trash"></i></button></form>
                </div>
                @unless ($em->isVerified())
                    <div class="email-otp">
                        <form method="POST" action="{{ route('profile.emails.verify', $em) }}" class="d-flex gap-2 flex-grow-1">
                            @csrf
                            <input name="code" inputmode="numeric" maxlength="6" pattern="[0-9]{6}" class="form-control form-control-sm otp-input" placeholder="6-digit code" required @if (session('otp_for') === $em->id) autofocus @endif>
                            <button class="btn btn-sm btn-success">Verify</button>
                        </form>
                        <form method="POST" action="{{ route('profile.emails.resend', $em) }}">@csrf<button class="btn btn-sm btn-link px-1">Send new code</button></form>
                    </div>
                @endunless
            @endforeach
            @if ($emails->count() < \App\Models\UserEmail::MAX_PER_USER)
                <form method="POST" action="{{ route('profile.emails.store') }}" class="d-flex gap-2 mt-2">
                    @csrf
                    <input type="email" name="email" class="form-control" placeholder="yourname@gmail.com" required maxlength="150" value="{{ old('email') }}">
                    <button class="btn btn-primary text-nowrap"><i class="bi bi-send me-1"></i>Send code</button>
                </form>
            @endif
        </div>

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
            <a href="{{ route('install') }}" class="menu-link">
                <i class="bi bi-phone"></i> Install App on Phone <i class="bi bi-chevron-right"></i>
            </a>
            @if ($user->tracksAttendance())
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
