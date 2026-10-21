@extends('layouts.app')

@section('title', 'Late Check-in Requests')

@section('content')
<nav class="seg-tabs mb-3">
    @foreach (\App\Enums\LeaveStatus::cases() as $s)
        <a href="{{ route('admin.late-requests.index', ['status' => $s->value]) }}" @class(['active' => $status === $s])>
            {{ $s->label() }}@if ($s === \App\Enums\LeaveStatus::Pending) ({{ $pendingCount }})@endif
        </a>
    @endforeach
    <a href="{{ route('admin.late-requests.index', ['status' => 'all']) }}" @class(['active' => ! $status])>All</a>
</nav>

<div class="info-box mb-3">
    <i class="bi bi-info-circle-fill"></i>
    <div>Employees can check in on time between the shift's check-in window (e.g. 09:55 – 10:15 AM). After that they must send a request here; once approved they can check in and the day is marked <b>Late</b>.</div>
</div>

<div class="card-soft card-pad" data-live-region="late">
    @forelse ($requests as $req)
        <div class="list-row flex-wrap">
            <span class="avatar tone-{{ $req->status->tone() }}">{{ $req->user->initials }}</span>
            <div class="flex-grow-1 min-w-0">
                <div class="list-title">{{ $req->user->name }} <span class="small text-muted">· {{ $req->user->employee_code }}</span></div>
                <div class="small">
                    {{ $req->work_date->isToday() ? 'Today' : $req->work_date->format('d M Y') }}, requested at <b>{{ $req->created_at->format('h:i A') }}</b>
                    @if ($req->user->employee?->shift) · Shift {{ $req->user->employee->shift->label() }} @endif
                </div>
                <div class="small text-muted">"{{ $req->reason }}"</div>
                @if ($req->rejection_reason)<div class="small text-danger">Rejected: {{ $req->rejection_reason }}</div>@endif
            </div>
            @if ($req->status === \App\Enums\LeaveStatus::Pending)
                <div class="d-flex gap-2">
                    <form method="POST" action="{{ route('admin.late-requests.approve', $req) }}">
                        @csrf
                        <button class="btn btn-sm btn-success"><i class="bi bi-check-lg"></i> Approve</button>
                    </form>
                    <button type="button" class="btn btn-sm btn-outline-danger" data-modal="#rejectLateModal"
                            data-action="{{ route('admin.late-requests.reject', $req) }}"
                            data-fill='@json(['employee' => $req->user->name])'>Reject</button>
                </div>
            @else
                <div class="text-end">
                    <x-badge :tone="$req->status->tone()">{{ $req->status->label() }}</x-badge>
                    @if ($req->approver)<div class="small-xs text-muted mt-1">by {{ $req->approver->name }}, {{ $req->actioned_at?->format('h:i A') }}</div>@endif
                </div>
            @endif
        </div>
    @empty
        <x-empty-state icon="alarm" title="No late check-in requests" />
    @endforelse

    @if ($requests->hasPages())
        <div class="mt-3">{{ $requests->links() }}</div>
    @endif
</div>

<div class="modal fade" id="rejectLateModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" action="" class="modal-content rounded-4 border-0">
            @csrf
            <div class="modal-header border-0">
                <div>
                    <h5 class="modal-title fw-bold mb-0">Reject Late Check-in</h5>
                    <div class="small text-muted" data-field="employee"></div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body pt-0">
                <label class="form-label">Reason (optional)</label>
                <textarea name="rejection_reason" rows="2" maxlength="300" class="form-control" placeholder="e.g. Too late, marked absent"></textarea>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-danger">Reject</button>
            </div>
        </form>
    </div>
</div>
@endsection
