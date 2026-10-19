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

<div class="card-soft card-pad">
    <h3 class="section-title mb-2">Exception Checkouts ({{ $checkouts->count() }})</h3>
    @forelse ($checkouts as $session)
        <div class="list-row flex-wrap">
            <span class="avatar tone-warning">{{ $session->user->initials }}</span>
            <div class="flex-grow-1 min-w-0">
                <div class="list-title">{{ $session->user->name }} <span class="small text-muted">· {{ $session->work_date->format('d M Y') }}</span></div>
                <div class="small text-muted">
                    {{ $session->check_in_at->format('h:i A') }} – {{ $session->check_out_at?->format('h:i A') }}
                    · Worked {{ Duration::minutes($session->worked_minutes) }}
                </div>
                <div class="small">{{ $session->checkout_reason }}</div>
            </div>
            <div class="d-flex gap-2">
                @foreach (['approved' => ['Approve', 'success'], 'rejected' => ['Reject', 'outline-danger']] as $decision => [$label, $style])
                    <form method="POST" action="{{ route('manager.approvals.checkouts.review', $session) }}">
                        @csrf
                        <input type="hidden" name="decision" value="{{ $decision }}">
                        <button class="btn btn-sm btn-{{ $style }}">{{ $label }}</button>
                    </form>
                @endforeach
            </div>
        </div>
    @empty
        <x-empty-state icon="box-arrow-right" title="No exception checkouts to review" />
    @endforelse
</div>

@include('partials.reject-leave-modal')
@endsection
