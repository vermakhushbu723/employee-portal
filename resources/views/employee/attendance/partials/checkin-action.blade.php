{{--
    Check-in action for the current check-in state (see AttendanceService::checkInState).
    $checkIn = ['state', 'opens_at', 'late_after', 'request'];  $compact = true for the dashboard banner.
--}}
@php
    $compact ??= false;
    $state = $checkIn['state'];
    $request = $checkIn['request'];
@endphp

@switch($state)
    @case('not_open')
        <div class="{{ $compact ? 'small' : '' }}">
            <i class="bi bi-hourglass-split me-1"></i>Check-in opens at <b>{{ $checkIn['opens_at'] }}</b>
            <div class="small text-muted">On-time window: {{ $checkIn['opens_at'] }} – {{ $checkIn['late_after'] }}</div>
        </div>
        @break

    @case('open')
    @case('late_approved')
        <form method="POST" action="{{ route('employee.attendance.check-in') }}">
            @csrf
            <button class="btn btn-success {{ $compact ? 'btn-sm' : 'btn-lg px-5' }} text-nowrap">
                <i class="bi bi-fingerprint me-1"></i>{{ $state === 'late_approved' ? 'Check In (Late – Approved)' : 'Check In Now' }}
            </button>
        </form>
        @if (! $compact)
            <div class="small text-muted mt-2">
                @if ($state === 'open')
                    On time until <b>{{ $checkIn['late_after'] }}</b>. After that, admin approval is needed.
                @else
                    Our Management Team approved your late check-in. Today will be marked <b>Late</b>.
                @endif
            </div>
        @endif
        @break

    @case('late_request_needed')
        @if ($compact)
            <a href="{{ route('employee.attendance.today') }}" class="btn btn-sm btn-warning text-nowrap">
                <i class="bi bi-send me-1"></i>Request Late Check-in
            </a>
        @else
            <div class="alert alert-warning text-start small">
                <i class="bi bi-alarm-fill me-1"></i>
                You are <b>late</b>. Check-in was open {{ $checkIn['opens_at'] }} – {{ $checkIn['late_after'] }}.
                Send a request to admin — you can check in once it is approved.
            </div>
            <form method="POST" action="{{ route('employee.attendance.late-request') }}" class="text-start">
                @csrf
                <label class="form-label required">Reason for late check-in</label>
                <textarea name="reason" rows="2" maxlength="300" class="form-control @error('reason') is-invalid @enderror" placeholder="e.g. Power cut / internet issue">{{ old('reason') }}</textarea>
                @error('reason')<div class="invalid-feedback">{{ $message }}</div>@enderror
                <button class="btn btn-primary w-100 mt-2"><i class="bi bi-send me-1"></i>Send Request to Admin</button>
            </form>
        @endif
        @break

    @case('late_pending')
        <div class="{{ $compact ? 'small' : '' }}">
            <x-badge tone="warning"><i class="bi bi-hourglass-split"></i> Waiting for admin approval</x-badge>
            @unless ($compact)
                <div class="small text-muted mt-2">Requested at {{ $request->created_at->format('h:i A') }} · "{{ $request->reason }}"<br>This screen updates by itself as soon as admin approves.</div>
            @endunless
        </div>
        @break

    @case('late_rejected')
        <div class="{{ $compact ? 'small' : '' }}">
            <x-badge tone="danger"><i class="bi bi-x-circle"></i> Late check-in rejected</x-badge>
            @unless ($compact)
                <div class="small text-muted mt-2">{{ $request->rejection_reason ?: 'Please contact your admin.' }}</div>
            @endunless
        </div>
        @break
@endswitch
