{{-- Training → Joining → On Job. $training from TrainingService::status() --}}
@php
    $tr = $training['training'];
    $trState = $tr['state'] ?? null;
    $trBadge = match ($trState) { 'completed' => ['Completed', 'success'], 'in_progress' => ['In Progress', 'warning'], 'upcoming' => ['Upcoming', 'secondary'], default => null };
    $joinBadge = $training['joined'] ? ['Joined', 'success'] : ['Pending', 'warning'];
    $jobBadge = $training['on_job'] ? ['Active', 'success'] : ['Not Started', 'secondary'];
@endphp
<div class="card-soft card-pad mb-3">
    <h3 class="section-title mb-3"><i class="bi bi-mortarboard me-1"></i>Training & Joining Status</h3>
    <div class="journey">
        @if ($tr)
            <div class="journey-step tone-primary">
                <span class="icon-box lg soft"><i class="bi bi-mortarboard-fill"></i></span>
                <div class="min-w-0">
                    <div class="fw-bold">Training</div>
                    <div class="small">{{ $tr['from']->format('d M Y') }} – {{ $tr['effective_to']->format('d M Y') }}</div>
                    <div class="small-xs text-muted">{{ $tr['done_days'] }} of {{ $tr['planned_days'] }} training day(s) done</div>
                    <x-badge :tone="$trBadge[1]" class="mt-1">{{ $trBadge[0] }}</x-badge>
                </div>
            </div>
            <span class="journey-link {{ $trState === 'completed' ? 'done' : '' }}"><i class="bi bi-check-lg"></i></span>
        @endif
        <div class="journey-step tone-warning">
            <span class="icon-box lg soft"><i class="bi bi-calendar-check-fill"></i></span>
            <div class="min-w-0">
                <div class="fw-bold">Joining Date</div>
                <div class="small">{{ $training['joining']?->format('d M Y') ?? '—' }}</div>
                @if ($training['moved'])<div class="small-xs text-danger">Moved from {{ $training['joining_planned']->format('d M') }}</div>@endif
                <x-badge :tone="$joinBadge[1]" class="mt-1">{{ $joinBadge[0] }}</x-badge>
            </div>
        </div>
        <span class="journey-link {{ $training['joined'] ? 'done' : '' }}"><i class="bi bi-check-lg"></i></span>
        <div class="journey-step tone-success">
            <span class="icon-box lg soft"><i class="bi bi-briefcase-fill"></i></span>
            <div class="min-w-0">
                <div class="fw-bold">On Job</div>
                <div class="small">{{ $training['on_job'] ? 'Working since '.$training['joining']->format('d M Y') : 'After joining date' }}</div>
                <x-badge :tone="$jobBadge[1]" class="mt-1">{{ $jobBadge[0] }}</x-badge>
            </div>
        </div>
    </div>
    <div class="info-box mt-3">
        <i class="bi bi-info-circle-fill"></i>
        <div>
            @if ($tr)
                Training period comes before the joining date. A training day counts only when you work that day —
                <b>leave or absence during training moves the joining date forward</b>.
                @if ($tr['missed_days'])
                    You missed {{ $tr['missed_days'] }} training day(s), so training now ends {{ $tr['effective_to']->format('d M') }} and joining is {{ $training['joining']->format('d M Y') }}.
                @endif
            @else
                No training period set — your work starts from the joining date.
            @endif
        </div>
    </div>
</div>
