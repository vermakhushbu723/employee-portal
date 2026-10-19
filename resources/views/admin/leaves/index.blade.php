@extends('layouts.app')

@section('title', 'Leave Approvals')

@section('content')
@if ($errors->has('rejection_reason'))
    <div class="alert alert-danger small">{{ $errors->first('rejection_reason') }}</div>
@endif

<nav class="seg-tabs mb-3">
    @foreach (\App\Enums\LeaveStatus::cases() as $s)
        <a href="{{ route('admin.leaves.index', ['status' => $s->value]) }}" @class(['active' => $status === $s])>{{ $s->label() }}</a>
    @endforeach
    <a href="{{ route('admin.leaves.index', ['status' => 'all']) }}" @class(['active' => ! $status])>All</a>
</nav>

<div class="card-soft card-pad">
    @forelse ($leaves as $leave)
        @include('partials.leave-approval-row', [
            'leave' => $leave,
            'approveUrl' => route('admin.leaves.approve', $leave),
            'rejectUrl' => route('admin.leaves.reject', $leave),
        ])
    @empty
        <x-empty-state icon="calendar-check" title="No leave requests" />
    @endforelse

    @if ($leaves->hasPages())
        <div class="mt-3">{{ $leaves->links() }}</div>
    @endif
</div>

@include('partials.reject-leave-modal')
@endsection
