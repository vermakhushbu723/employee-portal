@extends('layouts.app')

@section('title', 'Department Data')

@php
    $deptIcon = fn ($name) => match (true) {
        str_contains(strtolower($name), 'real') => 'house-door-fill',
        str_contains(strtolower($name), 'loan') => 'cash-coin',
        str_contains(strtolower($name), 'credit') => 'credit-card-2-front-fill',
        str_contains(strtolower($name), 'hr') || str_contains(strtolower($name), 'human') => 'people-fill',
        default => 'building',
    };
    $tones = ['primary', 'success', 'warning', 'purple', 'info', 'danger'];
@endphp

@section('content')
<div class="mb-3">
    <h2 class="h4 fw-bold mb-0">Department Wise Data Tracking</h2>
    <div class="small text-muted">View, manage and track uploaded and shared data for all departments. Click a department to see its full work.</div>
</div>

<div class="row g-3 mb-3">
    @foreach ($departments as $i => $row)
        <div class="col-6 col-md-4 col-xl-3">
            <a href="{{ route('admin.departments.show', ['department' => $row['dept'], 'from' => $from->toDateString(), 'to' => $to->toDateString()]) }}" class="dept-card tone-{{ $tones[$i % count($tones)] }}">
                <span class="icon-box"><i class="bi bi-{{ $deptIcon($row['dept']->name) }}"></i></span>
                <div class="min-w-0">
                    <div class="fw-bold text-truncate">{{ $row['dept']->name }}</div>
                    <div class="small text-muted">{{ number_format($row['records']) }} Records</div>
                </div>
                <i class="bi bi-chevron-right ms-auto text-muted"></i>
            </a>
        </div>
    @endforeach
</div>

@include('admin.departments.partials.filters', ['action' => route('admin.departments.index')])
@include('admin.departments.partials.kpis')

<div class="card-soft">
    <div class="card-pad pb-2"><h3 class="section-title">All Departments ({{ $from->format('d M') }} – {{ $to->format('d M Y') }})</h3></div>
    <div class="table-responsive">
        <table class="table table-soft">
            <thead><tr><th>Department</th><th class="text-end">Uploaded</th><th class="text-end">Shared</th><th class="text-end">Unassigned</th><th class="text-end">Called</th><th class="text-end">Interested</th><th class="text-end">Converted</th><th style="min-width:150px">Work done</th><th></th></tr></thead>
            <tbody>
                @foreach ($departments as $row)
                    @php
                        $s = $row['stats'];
                        $pct = $s['shared'] ? (int) round($s['called'] / $s['shared'] * 100) : 0;
                    @endphp
                    <tr>
                        <td class="fw-600"><i class="bi bi-{{ $deptIcon($row['dept']->name) }} me-1 text-muted"></i>{{ $row['dept']->name }}</td>
                        <td class="text-end">{{ number_format($s['uploaded']) }}</td>
                        <td class="text-end">{{ number_format($s['shared']) }}</td>
                        <td class="text-end text-warning">{{ number_format($s['unassigned']) }}</td>
                        <td class="text-end">{{ number_format($s['called']) }}</td>
                        <td class="text-end">{{ number_format($s['interested']) }}</td>
                        <td class="text-end text-success fw-600">{{ number_format($s['converted']) }}</td>
                        <td><div class="d-flex align-items-center gap-2"><x-progress class="flex-grow-1" :value="$pct" tone="success" /><span class="small fw-600">{{ $pct }}%</span></div></td>
                        <td class="text-end"><a href="{{ route('admin.departments.show', ['department' => $row['dept'], 'from' => $from->toDateString(), 'to' => $to->toDateString()]) }}" class="btn btn-sm btn-outline-primary">View</a></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
