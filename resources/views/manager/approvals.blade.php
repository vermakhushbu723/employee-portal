@extends('layouts.app')

@use('App\Support\Duration')

@section('title', 'Approvals')

@section('content')
@if ($errors->has('rejection_reason'))
    <div class="alert alert-danger small">{{ $errors->first('rejection_reason') }}</div>
@endif

<div class="card-soft card-pad mb-3">
    <h3 class="section-title mb-2">Leave Requests ({{ $leaves->count() }})</h3>
    @forelse ($leaves as $leave)
        @include('partials.leave-approval-row', [
            'leave' => $leave,
            'approveUrl' => route('manager.approvals.leaves.approve', $leave),
            'rejectUrl' => route('manager.approvals.leaves.reject', $leave),
        ])
    @empty
        <x-empty-state icon="calendar-check" title="No pending leave requests" />
    @endforelse
</div>

<a href="{{ route('manager.logout-requests.index') }}" class="card-soft card-pad d-flex align-items-center gap-3 text-decoration-none text-dark mb-3">
    <span class="icon-box lg tone-warning"><i class="bi bi-box-arrow-right"></i></span>
    <div class="flex-grow-1">
        <div class="fw-bold">Logout Requests ({{ $checkouts->count() }} under review)</div>
        <div class="small text-muted">Early logout / exception checkouts are verified in 2 steps on the Logout Requests page.</div>
    </div>
    <span class="btn btn-sm btn-primary">Open</span>
</a>

@include('partials.reject-leave-modal')
@endsection
