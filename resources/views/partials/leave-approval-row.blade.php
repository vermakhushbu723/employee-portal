{{-- One pending/processed leave request with approve / reject actions. --}}
<div class="list-row flex-wrap">
    <span class="avatar tone-{{ $leave->status->tone() }}">{{ $leave->user->initials }}</span>
    <div class="flex-grow-1 min-w-0">
        <div class="list-title">{{ $leave->user->name }} <span class="small text-muted">· {{ $leave->user->employee_code }}</span></div>
        <div class="small">
            <b>{{ $leave->leave_type->label() }}</b> ·
            {{ $leave->from_date->format('d M') }}@if ($leave->isMultiDay()) – {{ $leave->to_date->format('d M Y') }}@else {{ $leave->from_date->format('Y') }}@endif
            · {{ $leave->days + 0 }} day(s)
        </div>
        <div class="small text-muted">{{ $leave->reason }}</div>
    </div>
    @if ($leave->status === \App\Enums\LeaveStatus::Pending)
        <div class="d-flex gap-2">
            <form method="POST" action="{{ $approveUrl }}" data-confirm="Approve this leave?">
                @csrf
                <button class="btn btn-sm btn-success">Approve</button>
            </form>
            <button type="button" class="btn btn-sm btn-outline-danger" data-modal="#rejectLeaveModal" data-action="{{ $rejectUrl }}"
                    data-fill='@json(['employee' => $leave->user->name])'>Reject</button>
        </div>
    @else
        <div class="text-end">
            <x-badge :tone="$leave->status->tone()">{{ $leave->status->label() }}</x-badge>
            @if ($leave->approver)<div class="small-xs text-muted mt-1">by {{ $leave->approver->name }}</div>@endif
        </div>
    @endif
</div>
