@extends('layouts.app')

@section('title', 'My Leave History')
@section('back', route('employee.dashboard'))
@if ($errors->any())
    @section('reopen-modal', '#applyLeaveModal')
@endif

@section('content')
<div class="d-flex flex-wrap gap-2 align-items-center mb-3">
    <nav class="seg-tabs flex-grow-1">
        <a href="{{ route('employee.leaves.index') }}" @class(['active' => ! $tab])>All ({{ $counts->sum() }})</a>
        @foreach (\App\Enums\LeaveStatus::cases() as $status)
            <a href="{{ route('employee.leaves.index', ['tab' => $status->value]) }}" @class(['active' => $tab === $status])>
                {{ $status->label() }} ({{ $counts[$status->value] ?? 0 }})
            </a>
        @endforeach
    </nav>
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#applyLeaveModal">
        <i class="bi bi-plus-lg me-1"></i>Apply Leave
    </button>
</div>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card-soft card-pad">
            @forelse ($leaves as $leave)
                <div class="list-row align-items-start">
                    <div class="date-chip tone-{{ $leave->status->tone() }} py-2" style="width:64px">
                        <b>{{ $leave->from_date->format('d M') }}</b>
                        <small>{{ $leave->from_date->format('Y') }}</small>
                        @if ($leave->isMultiDay())
                            <small class="d-block text-muted">To</small>
                            <b>{{ $leave->to_date->format('d M') }}</b>
                        @endif
                    </div>
                    <div class="flex-grow-1 min-w-0">
                        <div class="list-title">{{ $leave->leave_type->label() }}</div>
                        <div class="small text-muted">{{ $leave->reason }}</div>
                        @if ($leave->rejection_reason)
                            <div class="small mt-1"><span class="text-muted">Reason:</span> {{ $leave->rejection_reason }}</div>
                        @endif
                        @if ($leave->status === \App\Enums\LeaveStatus::Approved && ! $leave->is_paid)
                            <div class="small text-danger mt-1"><i class="bi bi-currency-rupee"></i>Unpaid — salary deduction applied</div>
                        @endif
                    </div>
                    <div class="text-end">
                        <x-badge :tone="$leave->status->tone()">{{ $leave->status->label() }}</x-badge>
                        <div class="small text-muted mt-2">{{ rtrim(rtrim(number_format($leave->days, 1), '0'), '.') }} {{ $leave->days == 1 ? 'Day' : 'Days' }}</div>
                    </div>
                </div>
            @empty
                <x-empty-state icon="calendar2-x" title="No leave applications" text="Your leave requests will appear here." />
            @endforelse

            @if ($leaves->hasPages())
                <div class="mt-3">{{ $leaves->links() }}</div>
            @endif
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card-soft card-pad">
            <div class="section-head">
                <h3 class="section-title">Leave Balance ({{ now()->year }})</h3>
                <a href="{{ route('employee.leaves.charges') }}" class="small fw-600">Summary</a>
            </div>
            @forelse ($balances as $balance)
                <div class="mb-3">
                    <div class="d-flex justify-content-between small mb-1">
                        <span>{{ $balance->leave_type->label() }}</span>
                        <span class="fw-600">{{ $balance->available() + 0 }} left of {{ $balance->total + 0 }}</span>
                    </div>
                    <x-progress :value="\App\Support\Duration::percent($balance->used, $balance->total)" tone="primary" />
                </div>
            @empty
                <div class="small text-muted">No leave balance set for this year.</div>
            @endforelse
        </div>
    </div>
</div>

<div class="modal fade" id="applyLeaveModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" action="{{ route('employee.leaves.store') }}" class="modal-content rounded-4 border-0" novalidate>
            @csrf
            <div class="modal-header border-0">
                <h5 class="modal-title fw-bold">Apply Leave</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body pt-0">
                <div class="mb-3">
                    <label class="form-label required">Leave Type</label>
                    <select name="leave_type" class="form-select @error('leave_type') is-invalid @enderror">
                        @foreach ($types as $type)
                            <option value="{{ $type->value }}" @selected(old('leave_type') === $type->value)>{{ $type->label() }}</option>
                        @endforeach
                    </select>
                    @error('leave_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="row g-3 mb-3">
                    <div class="col-6">
                        <label class="form-label required">From</label>
                        <input type="date" name="from_date" value="{{ old('from_date') }}" min="{{ today()->toDateString() }}" class="form-control @error('from_date') is-invalid @enderror">
                        @error('from_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-6">
                        <label class="form-label required">To</label>
                        <input type="date" name="to_date" value="{{ old('to_date') }}" min="{{ today()->toDateString() }}" class="form-control @error('to_date') is-invalid @enderror">
                        @error('to_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div>
                    <label class="form-label required">Reason</label>
                    <textarea name="reason" rows="3" class="form-control @error('reason') is-invalid @enderror" placeholder="Reason for leave">{{ old('reason') }}</textarea>
                    @error('reason')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="info-box mt-3">
                    <i class="bi bi-info-circle-fill"></i>
                    <div>Weekly offs are not counted. Leave beyond your balance is treated as unpaid.</div>
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-primary">Submit Request</button>
            </div>
        </form>
    </div>
</div>
@endsection
