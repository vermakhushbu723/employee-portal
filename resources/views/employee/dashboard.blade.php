@extends('layouts.app')

@use('App\Support\Duration')

@section('title', 'Dashboard')

@section('content')
<div data-live-timer='@json($timer)' data-status-url="{{ route('employee.work-timer.status') }}">

    {{-- Greeting + current status --}}
    @php
        $hour = now()->hour;
        $greeting = $hour < 12 ? 'Good Morning' : ($hour < 17 ? 'Good Afternoon' : 'Good Evening');
        [$stateLabel, $stateTone, $stateIcon] = match (true) {
            ! $timer['checked_in'] => ['Not Checked In', 'secondary', 'clock'],
            $timer['checked_out'] => ['Checked Out at '.$timer['check_out_at'], 'primary', 'check2-all'],
            $timer['on_break'] => ['On Break', 'warning', 'cup-hot-fill'],
            default => ['Currently Working', 'success', 'circle-fill'],
        };
    @endphp
    <div class="card-soft card-pad mb-3 hero-card">
        <div class="d-flex flex-wrap align-items-center gap-3">
            <div class="hero-greeting flex-grow-1">
                <span class="avatar lg tone-primary">{{ $user->initials }}</span>
                <div class="min-w-0">
                    <div class="small text-muted">{{ $greeting }},</div>
                    <h2 class="h5 fw-bold mb-0">{{ $user->name }} 👋</h2>
                    <div class="text-muted small">{{ $user->employee_code }} | {{ $user->employee?->designation ?? $user->roleLabel() }}</div>
                </div>
            </div>
            <div class="status-banner tone-{{ $stateTone }}">
                <span class="dot"><i class="bi bi-{{ $stateIcon }} small"></i></span>
                <div>
                    {{ $stateLabel }}
                    <small>
                        @if ($timer['on_break']) Since {{ $timer['break_started_at'] }}
                        @elseif ($timer['checked_in'] && ! $timer['checked_out']) Since {{ $timer['check_in_at'] }}
                        @else Shift {{ $timer['shift_label'] }} @endif
                    </small>
                </div>
                @unless ($timer['checked_in'])
                    <div class="ms-2">
                        @include('employee.attendance.partials.checkin-action', ['checkIn' => $checkIn, 'compact' => true])
                    </div>
                @endunless
            </div>
        </div>

        {{-- Settings from the employee profile (set by admin) --}}
        <div class="setting-chips mt-3">
            <span class="setting-chip"><i class="bi bi-person-badge"></i>Role <b>{{ $user->roleLabel() }}</b></span>
            <span class="setting-chip"><i class="bi bi-clock"></i>Shift <b>{{ $shift->label() }}</b> · {{ Duration::hm($shift->required_minutes * 60) }}</span>
            <span class="setting-chip"><i class="bi bi-cup-hot"></i>Break <b>{{ $shift->break_allowed_minutes }} min</b></span>
            <span class="setting-chip">
                <i class="bi bi-bullseye"></i>Today's Target
                <b>{{ $targets['leads'] }} leads</b>@if ($targets['extra']['leads'])<span class="extra">(+{{ $targets['extra']['leads'] }} task)</span>@endif
                · <b>{{ $targets['interested'] }} interested</b>@if ($targets['extra']['interested'])<span class="extra">(+{{ $targets['extra']['interested'] }} file)</span>@endif
            </span>
        </div>
    </div>

    @if ($training)
        @include('employee.partials.training-status', ['training' => $training])
    @endif

    <div data-live-region="leads">
        @if ($score)
            @php
                [$lvl, $gradeLabel, $gradeTone] = \App\Services\WorkScoreService::battery($score['grade']);
                $scoreParts = [
                    ['Active time', Duration::hm($score['active']).' / '.Duration::hm($score['required']), $score['parts']['time'], 'success'],
                    ['Leads created', $score['created'].' / '.$score['targets']['leads'], $score['parts']['leads'], 'primary'],
                    ['Interested / Mature', $score['interested'].' / '.$score['targets']['interested'], $score['parts']['interested'], 'warning'],
                ];
            @endphp
            <div class="card-soft card-pad mb-3">
                <div class="d-flex flex-wrap align-items-center gap-3">
                    <div class="d-flex align-items-center gap-3">
                        <span class="battery lg tone-{{ $gradeTone }}"><span style="width: {{ max(4, min(100, $score['score'])) }}%"></span></span>
                        <div>
                            <div class="small text-muted">Today's Work Score</div>
                            <div class="fs-4 fw-bold lh-1">{{ $score['score'] }}% <span class="small fw-600" style="color: var(--{{ $gradeTone === 'secondary' ? 'muted' : $gradeTone }})">{{ $gradeLabel }}{{ $timer['checked_out'] ? '' : ' so far' }}</span></div>
                        </div>
                    </div>
                    <div class="flex-grow-1 row g-2" style="min-width: 260px">
                        @foreach ($scoreParts as [$label, $value, $pct, $tone])
                            <div class="col-md-4">
                                <div class="d-flex justify-content-between small"><span class="text-muted">{{ $label }}</span><b>{{ $value }}</b></div>
                                <x-progress class="mt-1" :value="$pct" :tone="$tone" />
                            </div>
                        @endforeach
                    </div>
                </div>
                @unless ($score['complete'])
                    <div class="small-xs text-muted mt-2"><i class="bi bi-lock-fill"></i> Punch-out unlocks at 100% (full shift active time + lead targets). Idle time is not counted.</div>
                @endunless
            </div>
        @endif

        <div class="section-head">
            <h3 class="section-title">My Assigned Work</h3>
            <a href="{{ route('employee.tasks.index') }}" class="small fw-600">View All</a>
        </div>
        @if ($workCards->isEmpty() && $newData > 0)
            <a href="{{ route('employee.leads.index', ['tab' => 'new']) }}" class="status-banner tone-info mb-3 text-decoration-none">
                <span class="dot"><i class="bi bi-stars small"></i></span>
                <div>{{ $newData }} new lead(s) to call <small>Data shared with you — open My Leads → New Data</small></div>
                <span class="ms-auto btn btn-sm btn-primary text-nowrap">Start Calling</span>
            </a>
        @else
            @include('employee.partials.work-cards', ['cards' => $workCards])
        @endif

        @if ($assignments->isNotEmpty())
            <div class="card-soft card-pad mb-3">
                <div class="section-head">
                    <h3 class="section-title">Other Tasks Today</h3>
                    <span class="small fw-bold">{{ $work['completed'] }} / {{ $work['target'] }} · {{ $work['progress'] }}%</span>
                </div>
                @foreach ($assignments as $assignment)
                    @php $meta = $assignment->task->categoryMeta(); @endphp
                    <a href="{{ route('employee.tasks.show', $assignment) }}" class="list-row list-row-link">
                        <span class="icon-box sm soft tone-{{ $meta['tone'] }}"><i class="bi bi-{{ $meta['icon'] }}"></i></span>
                        <div class="flex-grow-1 min-w-0">
                            <div class="d-flex justify-content-between">
                                <span class="list-title text-truncate">{{ $assignment->task->title }}</span>
                                <span class="small fw-600">{{ $assignment->completed_quantity }} / {{ $assignment->task->target_quantity }}</span>
                            </div>
                            <x-progress class="mt-2" :value="$assignment->progress()" :tone="$meta['tone']" />
                        </div>
                    </a>
                @endforeach
            </div>
        @endif
    </div>

    <div class="d-flex justify-content-end">
        <x-badge :tone="$eligibility['allowed'] ? 'success' : 'danger'">
            <i class="bi bi-{{ $eligibility['allowed'] ? 'unlock' : 'lock' }}-fill"></i>
            Punch-out {{ $eligibility['allowed'] ? 'Allowed' : 'Locked' }}
        </x-badge>
    </div>
</div>
@endsection
