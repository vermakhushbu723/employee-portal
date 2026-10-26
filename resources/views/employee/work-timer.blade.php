@extends('layouts.app')

@use('App\Support\Duration')

@section('title', 'Live Work Timer')
@section('back', route('employee.dashboard'))

@section('content')
<div class="row g-3" data-live-timer='@json($timer)' data-status-url="{{ route('employee.work-timer.status') }}">
    <div class="col-lg-6">

        <div class="card-soft card-pad text-center mb-3" style="background: var(--brand-soft)">
            @if ($timer['shift_over'])
                <div class="text-muted small fw-600">Shift Time</div>
                <div class="countdown my-1 text-danger"><i class="bi bi-clock-history me-2 fs-3"></i>Shift Over</div>
                <div class="text-muted small">Shift ended at {{ $timer['shift_end_at'] }} — complete your work to punch out</div>
            @else
                <div class="text-muted small fw-600">Shift Ends In</div>
                <div class="countdown my-1"><i class="bi bi-clock me-2 fs-3"></i><span data-bind="shift_ends_in:hms">{{ Duration::hms($timer['shift_ends_in']) }}</span></div>
                <div class="text-muted small">(Till {{ $timer['shift_end_at'] }})</div>
            @endif
        </div>

        @if ($timer['checked_in'])
            <div class="row g-2 mb-3 text-center">
                <div class="col-4"><div class="stat-tile tone-success flex-column justify-content-center"><div class="stat-label">Active (Working)</div><div class="stat-value" data-bind="worked:hms">{{ Duration::hms($timer['worked']) }}</div></div></div>
                <div class="col-4"><div class="stat-tile tone-danger flex-column justify-content-center"><div class="stat-label">Inactive</div><div class="stat-value" data-bind="idle:hms">{{ Duration::hms($timer['idle']) }}</div></div></div>
                <div class="col-4"><div class="stat-tile tone-primary flex-column justify-content-center"><div class="stat-label">Total since punch-in</div><div class="stat-value" data-bind="total:hms">{{ Duration::hms($timer['total']) }}</div></div></div>
            </div>
        @endif

        @unless ($timer['checked_in'])
            <div class="info-box mb-3">
                <i class="bi bi-info-circle-fill"></i>
                <div>You have not checked in yet. <a href="{{ route('employee.attendance.today') }}" class="fw-bold">Check in</a> to start the work timer.</div>
            </div>
        @endunless

        <div class="card-soft card-pad mb-3">
            <div class="timer-ring my-2">
                <svg viewBox="0 0 120 120" aria-hidden="true">
                    <circle class="track" cx="60" cy="60" r="52" fill="none" stroke-width="9"/>
                    <circle class="bar" data-ring cx="60" cy="60" r="52" fill="none" stroke-width="9"/>
                </svg>
                <div class="center">
                    <div class="text-muted small fw-600">
                        {{ $timer['on_break'] ? 'On Break — Paused' : ($timer['checked_out'] ? 'Checked Out' : 'Active Work Time') }}
                    </div>
                    <div class="countdown" data-bind="worked:hms">{{ Duration::hms($timer['worked']) }}</div>
                    <div class="text-muted small">of {{ Duration::hms($timer['required']) }}<br>(Required)</div>
                </div>
            </div>

            <div class="row g-3 mt-2">
                <div class="col-6">
                    <div class="stat-tile tone-danger flex-column text-center justify-content-center">
                        <div class="stat-label">Remaining Work Time</div>
                        <div class="stat-value fs-4" data-bind="remaining:hms">{{ Duration::hms($timer['remaining']) }}</div>
                    </div>
                </div>
                <div class="col-6">
                    <div class="stat-tile tone-purple flex-column text-center justify-content-center">
                        <div class="stat-label">Break Time Used</div>
                        <div class="stat-value fs-4" data-bind="break_used:hms">{{ Duration::hms($timer['break_used']) }}</div>
                        <div class="stat-hint">(of {{ Duration::hms($timer['break_allowed']) }})</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card-soft card-pad mb-3">
            <div class="section-head">
                <h3 class="section-title">Work Hour Progress</h3>
                <span class="fw-bold" data-bind="worked:pct" data-of="required">{{ $timer['progress'] }}%</span>
            </div>
            <x-progress :value="$timer['progress']" tone="success" bind="worked:required" />

            <div class="info-box mt-3">
                <i class="bi bi-info-circle-fill"></i>
                <div>You need to complete <b data-bind="remaining:hm">{{ Duration::hm($timer['remaining']) }}</b> more to meet today's required work hours.</div>
            </div>
        </div>

        <div class="d-flex gap-2">
            <a href="{{ route('employee.breaks.index') }}" class="btn btn-outline-primary flex-fill"><i class="bi bi-cup-hot me-1"></i>Breaks</a>
            <a href="{{ route('employee.attendance.today') }}" class="btn btn-primary flex-fill"><i class="bi bi-fingerprint me-1"></i>Attendance</a>
        </div>
    </div>
</div>
@endsection
