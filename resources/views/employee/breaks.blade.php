@extends('layouts.app')

@use('App\Support\Duration')

@section('title', 'Break Management')
@section('back', route('employee.dashboard'))

@section('content')
<div class="row justify-content-center" data-live-timer='@json($timer)' data-status-url="{{ route('employee.work-timer.status') }}">
    <div class="col-lg-8 col-xl-6">

        <div class="card-soft card-pad mb-3">
            <h3 class="section-title mb-3">Break Summary</h3>
            <div class="row text-center g-0">
                <div class="col-4 border-end">
                    <div class="text-muted small">Allowed</div>
                    <div class="fw-bold fs-5">{{ Duration::hms($timer['break_allowed']) }}</div>
                </div>
                <div class="col-4 border-end">
                    <div class="text-muted small">Used</div>
                    <div class="fw-bold fs-5" data-bind="break_used:hms">{{ Duration::hms($timer['break_used']) }}</div>
                </div>
                <div class="col-4">
                    <div class="text-muted small">Remaining</div>
                    <div class="fw-bold fs-5 text-success" data-bind="break_remaining:hms">{{ Duration::hms($timer['break_remaining']) }}</div>
                </div>
            </div>
        </div>

        <ul class="seg-tabs nav mb-3" role="tablist">
            <li class="nav-item flex-fill" role="presentation">
                <button class="active w-100" data-bs-toggle="tab" data-bs-target="#controls" type="button" role="tab">Break Controls</button>
            </li>
            <li class="nav-item flex-fill" role="presentation">
                <button class="w-100" data-bs-toggle="tab" data-bs-target="#history" type="button" role="tab">Break History</button>
            </li>
        </ul>

        <div class="tab-content">
            <div class="tab-pane fade show active" id="controls" role="tabpanel">
                <div class="card-soft mb-3">
                    @if ($timer['on_break'])
                        <div class="break-state tone-warning">
                            <div class="cup"><i class="bi bi-cup-hot-fill"></i></div>
                            <h4 class="h5 fw-bold">You are on Break</h4>
                            <div class="countdown text-warning my-2" data-bind="break_used:hms">{{ Duration::hms($timer['break_used']) }}</div>
                            <p class="text-muted small">Started at {{ $timer['break_started_at'] }}. Your work timer is paused.</p>
                        </div>
                        <div class="px-3 pb-3">
                            <form method="POST" action="{{ route('employee.breaks.end') }}">
                                @csrf
                                <button class="btn btn-danger btn-lg w-100"><i class="bi bi-stop-fill me-1"></i>End Break</button>
                            </form>
                        </div>
                    @else
                        <div class="break-state tone-primary">
                            <div class="cup"><i class="bi bi-cup-hot"></i></div>
                            <h4 class="h5 fw-bold">You are not on Break</h4>
                            <p class="text-muted small mb-0">Take a break when needed.<br>Please follow the allowed break time.</p>
                        </div>
                        <div class="px-3 pb-3">
                            @if ($timer['checked_in'] && ! $timer['checked_out'])
                                <form method="POST" action="{{ route('employee.breaks.start') }}"
                                      @if ($timer['break_remaining'] <= 0) data-confirm="Your break limit is already used. Extra break will reduce working hours. Continue?" @endif>
                                    @csrf
                                    <button class="btn btn-success btn-lg w-100"><i class="bi bi-play-fill me-1"></i>Start Break</button>
                                </form>
                            @else
                                <a href="{{ route('employee.attendance.today') }}" class="btn btn-outline-primary btn-lg w-100">
                                    {{ $timer['checked_out'] ? 'You have checked out for today' : 'Check in to take breaks' }}
                                </a>
                            @endif
                        </div>
                    @endif
                </div>

                @if ($timer['checked_in'] && $timer['break_remaining'] <= 0)
                    <div class="alert alert-warning small"><i class="bi bi-bell-fill me-1"></i>Your break limit for today is fully used.</div>
                @endif

                <div class="rule-box">
                    <h6><i class="bi bi-exclamation-circle-fill me-1"></i>Important</h6>
                    <ul class="small">
                        <li>Total break allowed: {{ Duration::hms($timer['break_allowed']) }}</li>
                        <li>Exceeding break time will reduce your working hours.</li>
                    </ul>
                </div>
            </div>

            <div class="tab-pane fade" id="history" role="tabpanel">
                <div class="card-soft card-pad">
                    @forelse ($history as $break)
                        <div class="list-row">
                            <span class="icon-box sm soft tone-warning"><i class="bi bi-cup-hot"></i></span>
                            <div class="flex-grow-1">
                                <div class="list-title">{{ $break->started_at->format('d M Y') }}</div>
                                <div class="small text-muted">{{ $break->started_at->format('h:i A') }} – {{ $break->ended_at?->format('h:i A') ?? 'ongoing' }}</div>
                            </div>
                            <x-badge :tone="$break->ended_at ? 'secondary' : 'warning'">
                                {{ $break->ended_at ? $break->duration_minutes.' min' : 'On break' }}
                            </x-badge>
                        </div>
                    @empty
                        <x-empty-state icon="cup" title="No breaks recorded yet" />
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
