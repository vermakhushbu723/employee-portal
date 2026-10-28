@extends('layouts.app')

@section('title', 'Task Details')
@section('back', route('employee.tasks.index'))

@php
    $task = $assignment->task;
    $meta = $task->categoryMeta();
    [$badgeLabel, $badgeTone] = $assignment->badge();
    $editable = ! $assignment->status->isDone();
@endphp

@section('content')
<div class="row g-3">
    <div class="col-lg-7">
        <div class="card-soft card-pad mb-3" style="background: var(--warning-soft)">
            <div class="d-flex gap-3">
                <span class="icon-box lg tone-{{ $meta['tone'] }}"><i class="bi bi-{{ $meta['icon'] }}"></i></span>
                <div class="flex-grow-1">
                    <div class="d-flex justify-content-between gap-2">
                        <h2 class="h5 fw-bold mb-1">{{ $task->title }}</h2>
                        <x-badge :tone="$badgeTone">{{ $badgeLabel }}</x-badge>
                    </div>
                    <p class="text-muted small mb-0">{{ $task->description }}</p>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-6"><x-stat-tile class="plain" icon="bullseye" label="Target" tone="purple" :value="$task->target_quantity.' '.$task->unit" :hint="$assignment->assigned_date->isToday() ? 'Today' : $assignment->assigned_date->format('d M')" /></div>
            <div class="col-6"><x-stat-tile class="plain" icon="check-lg" label="Completed" tone="success" :value="$assignment->completed_quantity.' '.$task->unit" :hint="'('.$assignment->progress().'%)'" /></div>
            <div class="col-6"><x-stat-tile class="plain" icon="hourglass-split" label="Remaining" tone="danger" :value="$assignment->remaining().' '.$task->unit" /></div>
            <div class="col-6"><x-stat-tile class="plain" icon="calendar-event" label="Deadline" tone="primary" :value="$task->deadline_at ? ($task->deadline_at->isToday() ? 'Today' : $task->deadline_at->format('d M')) : '—'" :hint="$task->deadline_at?->format('h:i A')" /></div>
        </div>

        <div class="card-soft card-pad mb-3">
            <div class="kv-row"><span class="k"><i class="bi bi-flag"></i>Priority</span>
                <span class="v {{ $task->priority === 'high' ? 'text-danger' : '' }}"><i class="bi bi-flag-fill me-1"></i>{{ ucfirst($task->priority) }}</span></div>
            <div class="kv-row"><span class="k"><i class="bi bi-person"></i>Assigned By</span><span class="v">{{ $task->assigner?->publicName() ?? \App\Models\User::MANAGEMENT }}</span></div>
            <div class="kv-row"><span class="k"><i class="bi bi-calendar"></i>Assigned On</span><span class="v">{{ $assignment->created_at->format('d M Y, h:i A') }}</span></div>
            <div class="kv-row"><span class="k"><i class="bi bi-stack"></i>Task Type</span><span class="v text-primary">{{ $meta['label'] }}</span></div>
            @if ($task->related_list)
                <div class="kv-row"><span class="k"><i class="bi bi-list-ul"></i>Related List</span><span class="v text-primary">{{ $task->related_list }}</span></div>
            @endif
        </div>

        @if ($task->rules)
            <div class="rule-box mb-3">
                <h6><i class="bi bi-exclamation-circle-fill me-1"></i>Mandatory Rules</h6>
                <ul class="small">
                    @foreach ($task->rules as $rule)
                        <li>{{ $rule }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>

    <div class="col-lg-5">
        <div class="card-soft card-pad mb-3">
            <h3 class="section-title mb-3">Work</h3>

            @if ($assignment->status === \App\Enums\TaskStatus::Assigned)
                <form method="POST" action="{{ route('employee.tasks.start', $assignment) }}" class="mb-3">
                    @csrf
                    <button class="btn btn-primary btn-lg w-100"><i class="bi bi-{{ $task->category === 'telecalling' ? 'telephone-fill' : 'play-fill' }} me-2"></i>{{ $task->category === 'telecalling' ? 'Start Calling' : 'Start Work' }}</button>
                </form>
            @endif

            @if ($editable)
                <form method="POST" action="{{ route('employee.tasks.progress', $assignment) }}" class="mb-3">
                    @csrf
                    <label class="form-label">Log Progress ({{ $task->unit }} completed)</label>
                    <div class="input-group mb-2">
                        <input type="number" name="quantity" min="1" max="1000" value="{{ old('quantity', 1) }}" class="form-control @error('quantity') is-invalid @enderror">
                        <button class="btn btn-success"><i class="bi bi-plus-lg"></i> Add</button>
                    </div>
                    @error('quantity')<div class="text-danger small mb-2">{{ $message }}</div>@enderror
                    <input type="text" name="note" value="{{ old('note') }}" maxlength="500" class="form-control" placeholder="Remarks / call status (optional)">
                </form>

                <form method="POST" action="{{ route('employee.tasks.submit', $assignment) }}" data-confirm="Submit this work for verification?">
                    @csrf
                    <button class="btn btn-outline-primary w-100" @disabled($assignment->remaining() > 0)><i class="bi bi-send me-1"></i>Submit Work</button>
                </form>
                <div class="small text-muted mt-2">
                    @if ($assignment->remaining() > 0)
                        <i class="bi bi-lock-fill"></i> Complete the target ({{ $assignment->remaining() }} {{ $task->unit }} left) to submit. Every task must be completed before you can punch out.
                    @else
                        <i class="bi bi-check-circle-fill text-success"></i> Target met — submit to complete this task.
                    @endif
                </div>
            @else
                <div class="info-box"><i class="bi bi-check-circle-fill"></i><div>Submitted on {{ $assignment->submitted_at?->format('d M Y, h:i A') }}. Waiting for verification.</div></div>
            @endif
        </div>

        <div class="card-soft card-pad">
            <h3 class="section-title mb-2">Work History</h3>
            @forelse ($assignment->activities as $activity)
                <div class="list-row">
                    <span class="icon-box sm soft tone-{{ ['started' => 'primary', 'progress' => 'success', 'submitted' => 'info'][$activity->action] ?? 'secondary' }}">
                        <i class="bi bi-{{ ['started' => 'play-fill', 'progress' => 'plus-lg', 'submitted' => 'send'][$activity->action] ?? 'dot' }}"></i>
                    </span>
                    <div class="flex-grow-1 min-w-0">
                        <div class="fw-600 small">
                            {{ ucfirst($activity->action) }}@if ($activity->quantity) · +{{ $activity->quantity }} {{ $task->unit }}@endif
                        </div>
                        @if ($activity->note)<div class="small text-muted text-truncate">{{ $activity->note }}</div>@endif
                    </div>
                    <span class="small-xs text-muted text-nowrap">{{ $activity->created_at->format('d M, h:i A') }}</span>
                </div>
            @empty
                <x-empty-state icon="clock-history" title="No activity yet" />
            @endforelse
        </div>
    </div>
</div>
@endsection
