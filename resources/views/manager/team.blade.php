@extends('layouts.app')

@section('title', 'Live Monitoring')

@section('content')
<div class="card-soft">
    <div class="card-pad pb-0 section-head">
        <div>
            <h3 class="section-title">My Team</h3>
            <div class="small text-muted">Live login time, working time, break and remaining time · {{ now()->format('d M Y, h:i A') }}</div>
        </div>
        <a href="{{ route('manager.team') }}" class="btn btn-outline-primary btn-sm"><i class="bi bi-arrow-clockwise me-1"></i>Refresh</a>
    </div>
    @include('manager.partials.team-table')
</div>
@endsection
