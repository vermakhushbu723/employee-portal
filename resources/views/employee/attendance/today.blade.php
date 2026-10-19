@extends('layouts.app')

@use('App\Support\Duration')

@section('title', 'Attendance')
@section('back', route('employee.dashboard'))
@if ($errors->has('reason') || $errors->has('type'))
    @section('reopen-modal', '#exceptionModal')
@endif

@section('content')
<div class="row g-3 justify-content-center">
    <div class="col-lg-6">
        <div class="card-soft card-pad d-flex align-items-center justify-content-between mb-3">
            <span class="fw-600"><i class="bi bi-calendar-event me-2 text-muted"></i>Today, {{ now()->format('d M Y') }}</span>
            <a href="{{ route('employee.attendance.history') }}" class="btn-icon" aria-label="Attendance calendar"><i class="bi bi-calendar3"></i></a>
        </div>

        @if ($session)
            <div class="checkin-card tone-{{ $session->check_out_at ? 'primary' : 'success' }} mb-3">
                <div class="big-icon"><i class="bi bi-check-lg"></i></div>
                <div class="h5 fw-bold" style="color: var(--tone)">
                    {{ $session->check_out_at ? 'You have Checked Out' : 'You are Checked In' }}
                </div>
                <div class="text-muted small">Login Time</div>
                <div class="time">{{ $session->check_in_at->format('h:i A') }}</div>
                @if ($session->check_out_at)
                    <div class="text-muted small mt-1">
                        Logout {{ $session->check_out_at->format('h:i A') }} · Worked {{ Duration::minutes($session->worked_minutes) }}
                        @if ($session->review_status === 'pending') <br><x-badge tone="warning" class="mt-2">Exception checkout · pending review</x-badge> @endif
                    </div>
                @endif
            </div>
        @else
            <div class="checkin-card tone-primary mb-3">
                <div class="big-icon"><i class="bi bi-fingerprint"></i></div>
                <div class="h5 fw-bold">You have not checked in yet</div>
                <p class="text-muted small mb-3">Shift starts at {{ \Illuminate\Support\Carbon::parse($shift->start_time)->format('h:i A') }}. Your working timer starts when you check in.</p>
                <form method="POST" action="{{ route('employee.attendance.check-in') }}">
                    @csrf
                    <button class="btn btn-success btn-lg px-5"><i class="bi bi-box-arrow-in-right me-2"></i>Check In Now</button>
                </form>
            </div>
        @endif

        <div class="card-soft card-pad mb-3">
            <div class="kv-row"><span class="k"><i class="bi bi-briefcase"></i>Shift Time</span><span class="v">{{ $shift->label() }}</span></div>
            <div class="kv-row"><span class="k"><i class="bi bi-clock"></i>Required Work Hours</span><span class="v">{{ Duration::minutes($shift->required_minutes) }}</span></div>
            <div class="kv-row"><span class="k"><i class="bi bi-cup-hot"></i>Break Allowed</span><span class="v">{{ Duration::minutes($shift->break_allowed_minutes) }}</span></div>
            <div class="kv-row">
                <span class="k"><i class="bi bi-geo-alt"></i>Late Login</span>
                <span class="v">
                    @if ($session)
                        <x-badge :tone="$session->is_late ? 'danger' : 'success'">{{ $session->is_late ? 'Yes' : 'No' }}</x-badge>
                    @else
                        <span class="text-muted">—</span>
                    @endif
                </span>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card-soft card-pad mb-3">
            <h3 class="section-title mb-3">Today's Status</h3>
            @if ($timeline)
                <ul class="timeline">
                    @foreach ($timeline as $item)
                        <li class="tone-{{ $item['tone'] }}">
                            <span class="tl-dot"></span>
                            <span class="tl-time">{{ $item['time']?->format('h:i A') ?? '-' }}</span>
                            <span class="tl-label">{{ $item['label'] }}</span>
                        </li>
                    @endforeach
                </ul>
            @else
                <x-empty-state icon="clock-history" title="No activity yet" text="Check in to start today's attendance." />
            @endif
        </div>

        @if ($session && $session->isOpen())
            <form method="POST" action="{{ route('employee.attendance.check-out') }}" data-confirm="Check out for today?">
                @csrf
                <button class="btn btn-lg w-100 {{ $eligibility['allowed'] ? 'btn-danger' : 'btn-secondary' }}" @disabled(! $eligibility['allowed'])>
                    <i class="bi bi-{{ $eligibility['allowed'] ? 'box-arrow-right' : 'lock-fill' }} me-2"></i>Check Out
                </button>
            </form>

            @unless ($eligibility['allowed'])
                <div class="text-center text-muted small mt-2">
                    You can check out after completing required work hours and tasks.
                </div>
                <ul class="small text-danger mt-2 mb-2">
                    @foreach ($eligibility['reasons'] as $reason)
                        <li>{{ $reason }}</li>
                    @endforeach
                </ul>
                <button type="button" class="btn btn-link w-100 small" data-bs-toggle="modal" data-bs-target="#exceptionModal">
                    Shift ended / emergency? Request exception checkout
                </button>
            @endunless
        @endif
    </div>
</div>

{{-- Rule 7: exception checkout --}}
<div class="modal fade" id="exceptionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" action="{{ route('employee.attendance.exception-checkout') }}" class="modal-content rounded-4 border-0">
            @csrf
            <div class="modal-header border-0">
                <h5 class="modal-title fw-bold">Exception Checkout</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body pt-0">
                <p class="small text-muted">Your checkout will be recorded now and sent to your manager for review as per company policy.</p>
                <div class="mb-3">
                    <label class="form-label required">Reason Type</label>
                    <select name="type" class="form-select @error('type') is-invalid @enderror">
                        <option value="shift_end" @selected(old('type') === 'shift_end')>Shift ended</option>
                        <option value="illness" @selected(old('type') === 'illness')>Illness</option>
                        <option value="emergency" @selected(old('type') === 'emergency')>Emergency</option>
                    </select>
                    @error('type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div>
                    <label class="form-label required">Details</label>
                    <textarea name="reason" rows="3" class="form-control @error('reason') is-invalid @enderror" placeholder="Explain briefly (min 10 characters)">{{ old('reason') }}</textarea>
                    @error('reason')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-primary">Submit &amp; Check Out</button>
            </div>
        </form>
    </div>
</div>
@endsection
