@extends('layouts.app')

@section('title', 'My Assigned Work')
@section('back', route('employee.dashboard'))

@section('content')
<div data-live-region="leads">
    @include('employee.partials.work-cards', ['cards' => $workCards])
</div>

<h3 class="section-title mb-2">Other Tasks</h3>
<nav class="seg-tabs mb-3">
    @foreach (['today' => 'Today', 'week' => 'This Week', 'all' => 'All Tasks'] as $key => $label)
        <a href="{{ route('employee.tasks.index', ['tab' => $key]) }}" @class(['active' => $tab === $key])>
            {{ $label }} ({{ $tabCounts[$key] }})
        </a>
    @endforeach
</nav>

<div class="row g-2 g-md-3 mb-3">
    <div class="col-3"><x-stat-tile class="flex-column text-center" icon="clipboard-check" label="Total Tasks" tone="success" :value="$summary['total']" /></div>
    <div class="col-3"><x-stat-tile class="flex-column text-center" icon="play-fill" label="In Progress" tone="primary" :value="$summary['in_progress']" /></div>
    <div class="col-3"><x-stat-tile class="flex-column text-center" icon="clock" label="Pending" tone="warning" :value="$summary['pending']" /></div>
    <div class="col-3"><x-stat-tile class="flex-column text-center" icon="exclamation" label="Overdue" tone="danger" :value="$summary['overdue']" /></div>
</div>

<div class="card-soft overflow-hidden">
    @forelse ($assignments as $assignment)
        @php
            $task = $assignment->task;
            $meta = $task->categoryMeta();
            [$badgeLabel, $badgeTone] = $assignment->badge();
        @endphp
        <a href="{{ route('employee.tasks.show', $assignment) }}" class="task-card">
            <span class="icon-box lg tone-{{ $meta['tone'] }}"><i class="bi bi-{{ $meta['icon'] }}"></i></span>
            <div class="flex-grow-1 min-w-0">
                <div class="d-flex justify-content-between align-items-start gap-2">
                    <div class="min-w-0">
                        <div class="list-title text-truncate">{{ $task->title }}</div>
                        <div class="small text-muted text-truncate">{{ $task->description }}</div>
                    </div>
                    <x-badge :tone="$badgeTone">{{ $badgeLabel }}</x-badge>
                </div>
                <div class="d-flex align-items-center gap-3 mt-2">
                    <x-progress class="flex-grow-1" :value="$assignment->progress()" :tone="$meta['tone']" />
                    <span class="small fw-bold text-nowrap">{{ $assignment->completed_quantity }} / {{ $task->target_quantity }}</span>
                </div>
                <div @class(['small mt-2', 'text-danger' => $assignment->isOverdue(), 'text-muted' => ! $assignment->isOverdue()])>
                    <i class="bi bi-clock me-1"></i>
                    {{ $task->deadline_at ? ($task->deadline_at->isToday() ? 'Today, '.$task->deadline_at->format('h:i A') : $task->deadline_at->format('d M, h:i A')) : 'No deadline' }}
                    @if ($task->is_mandatory) <span class="ms-2 text-danger fw-600"><i class="bi bi-asterisk small"></i> Mandatory</span> @endif
                </div>
            </div>
            <i class="bi bi-chevron-right text-muted align-self-center"></i>
        </a>
    @empty
        <x-empty-state icon="clipboard" title="No tasks here" text="Work assigned by your manager will appear in this list." />
    @endforelse
</div>
@endsection
