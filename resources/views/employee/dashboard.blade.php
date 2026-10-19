@extends('layouts.app')

@use('App\Support\Duration')

@section('title', 'Dashboard')

@section('content')
<div data-live-timer='@json($timer)' data-status-url="{{ route('employee.work-timer.status') }}">

    {{-- Greeting --}}
    @php
        $hour = now()->hour;
        $greeting = $hour < 12 ? 'Good Morning' : ($hour < 17 ? 'Good Afternoon' : 'Good Evening');
    @endphp
    <div class="card-soft card-pad mb-3 hero-card">
        <div class="hero-greeting">
            <span class="avatar lg tone-primary">{{ $user->initials }}</span>
            <div class="min-w-0">
                <div class="small text-muted">{{ $greeting }},</div>
                <h2 class="h5 fw-bold mb-0">{{ $user->name }} 👋</h2>
                <div class="text-muted small">{{ $user->employee_code }} | {{ $user->employee?->designation ?? $user->role->label() }}</div>
            </div>
        </div>

        @php
            [$stateLabel, $stateTone, $stateIcon] = match (true) {
                ! $timer['checked_in'] => ['Not Checked In', 'secondary', 'clock'],
                $timer['checked_out'] => ['Checked Out at '.$timer['check_out_at'], 'primary', 'check2-all'],
                $timer['on_break'] => ['On Break', 'warning', 'cup-hot-fill'],
                default => ['Currently Working', 'success', 'circle-fill'],
            };
        @endphp
        <div class="status-banner tone-{{ $stateTone }} mt-3">
            <span class="dot"><i class="bi bi-{{ $stateIcon }} small"></i></span>
            <div>
                {{ $stateLabel }}
                <small>
                    @if ($timer['on_break']) Since {{ $timer['break_started_at'] }}
                    @elseif ($timer['checked_in']) Since {{ $timer['check_in_at'] }}
                    @else Shift {{ $timer['shift_label'] }} @endif
                </small>
            </div>
            @unless ($timer['checked_in'])
                <form method="POST" action="{{ route('employee.attendance.check-in') }}" class="ms-auto">
                    @csrf
                    <button class="btn btn-success btn-sm text-nowrap"><i class="bi bi-fingerprint me-1"></i>Check-in</button>
                </form>
            @endunless
        </div>
    </div>

    <div class="d-flex align-items-center gap-2 text-muted mb-3 small fw-600">
        <i class="bi bi-calendar3"></i> Today, {{ now()->format('d M Y') }}
    </div>

    <div class="row g-3">
        {{-- Time tiles --}}
        <div class="col-lg-7">
            <div class="row g-3">
                <div class="col-6">
                    <x-stat-tile icon="clock" label="Login Time" tone="primary" :value="$timer['check_in_at'] ?? '--:--'" />
                </div>
                <div class="col-6">
                    <x-stat-tile icon="play-circle" label="Work Time" tone="success"><span data-bind="worked:hm">{{ Duration::hm($timer['worked']) }}</span></x-stat-tile>
                </div>
                <div class="col-6">
                    <x-stat-tile icon="cup-hot" label="Break Time" tone="warning"><span data-bind="break_used:hm">{{ Duration::hm($timer['break_used']) }}</span></x-stat-tile>
                </div>
                <div class="col-6">
                    <x-stat-tile icon="hourglass-split" label="Remaining" tone="danger"><span data-bind="remaining:hm">{{ Duration::hm($timer['remaining']) }}</span></x-stat-tile>
                </div>
            </div>

            {{-- Task progress --}}
            <div class="card-soft card-pad mt-3">
                <div class="section-head">
                    <h3 class="section-title">Task Progress (Today)</h3>
                    <span class="fw-bold">{{ $work['progress'] }}%</span>
                </div>
                <x-progress :value="$work['progress']" tone="success" />
                <div class="row g-3 mt-1">
                    <div class="col-6"><x-stat-tile class="plain" icon="clipboard-check" label="Completed" tone="success" :value="$work['completed']" /></div>
                    <div class="col-6"><x-stat-tile class="plain" icon="hourglass" label="Pending" tone="warning" :value="$work['pending']" /></div>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            {{-- Today's targets --}}
            <div class="card-soft card-pad h-100">
                <div class="section-head">
                    <h3 class="section-title">Today's Targets</h3>
                    <a href="{{ route('employee.tasks.index') }}" class="small fw-600">View All</a>
                </div>

                @forelse ($assignments as $assignment)
                    @php
                        $meta = $assignment->task->categoryMeta();
                    @endphp
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
                @empty
                    <x-empty-state icon="clipboard" title="No work assigned today" text="New tasks from your manager will appear here." />
                @endforelse
            </div>
        </div>

        {{-- Quick actions + logout status --}}
        <div class="col-12">
            <div class="card-soft card-pad">
                <div class="section-head">
                    <h3 class="section-title">Quick Actions</h3>
                    <x-badge :tone="$eligibility['allowed'] ? 'success' : 'danger'">
                        <i class="bi bi-{{ $eligibility['allowed'] ? 'unlock' : 'lock' }}-fill"></i>
                        Checkout {{ $eligibility['allowed'] ? 'Allowed' : 'Locked' }}
                    </x-badge>
                </div>
                @php
                    $actions = [
                        ['employee.attendance.today', 'fingerprint', 'Attendance', 'success'],
                        ['employee.tasks.index', 'briefcase', 'View Work', 'primary'],
                        ['employee.breaks.index', 'cup-hot', 'Break', 'warning'],
                        ['employee.leads.create', 'person-plus', 'New Lead', 'purple'],
                        ['employee.performance', 'bar-chart-line', 'Performance', 'info'],
                        ['employee.attendance.history', 'calendar3', 'My Attendance', 'danger'],
                    ];
                @endphp
                <div class="row g-2">
                    @foreach ($actions as [$route, $icon, $label, $tone])
                        <div class="col-4 col-md-2">
                            <a href="{{ route($route) }}" class="d-flex flex-column align-items-center gap-2 p-2 rounded-3 text-center text-dark small fw-600">
                                <span class="icon-box soft tone-{{ $tone }}"><i class="bi bi-{{ $icon }}"></i></span>
                                {{ $label }}
                            </a>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
