@extends('layouts.app')

@section('title', 'Manager Dashboard')

@section('content')
<div class="row g-2 g-md-3 mb-3">
    <div class="col-6 col-lg-3"><x-stat-tile icon="people-fill" label="Team Members" tone="primary" :value="$stats['members']" /></div>
    <div class="col-6 col-lg-3"><x-stat-tile icon="laptop" label="Working Now" tone="success" :value="$stats['working']" /></div>
    <div class="col-6 col-lg-3"><x-stat-tile icon="cup-hot-fill" label="On Break" tone="warning" :value="$stats['on_break']" /></div>
    <div class="col-6 col-lg-3"><x-stat-tile icon="person-x-fill" label="Not Checked In" tone="secondary" :value="$stats['not_in']" /></div>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-6">
        <div class="card-soft card-pad h-100">
            <div class="section-head">
                <h3 class="section-title">Team Work Progress (Today)</h3>
                <span class="fw-bold">{{ \App\Support\Duration::percent($work['completed'], $work['target']) }}%</span>
            </div>
            <x-progress :value="\App\Support\Duration::percent($work['completed'], $work['target'])" tone="success" />
            <div class="small text-muted mt-2">{{ $work['completed'] }} of {{ $work['target'] }} target units completed across the team.</div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card-soft card-pad h-100 d-flex align-items-center gap-3">
            <span class="icon-box lg tone-danger"><i class="bi bi-check2-square"></i></span>
            <div class="flex-grow-1">
                <div class="fw-bold fs-4">{{ $pendingApprovals }}</div>
                <div class="text-muted small">Pending approvals (leave + exception checkouts)</div>
            </div>
            <a href="{{ route('manager.approvals.index') }}" class="btn btn-outline-primary btn-sm">Review</a>
        </div>
    </div>
</div>

<div class="card-soft">
    <div class="card-pad pb-0 section-head">
        <h3 class="section-title">Live Employee Monitoring</h3>
        <a href="{{ route('manager.assign-work.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-clipboard-plus me-1"></i>Assign Work</a>
    </div>
    @include('manager.partials.team-table')
</div>
@endsection
