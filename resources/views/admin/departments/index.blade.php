@extends('layouts.app')

@section('title', 'Department Data')

@php
    $deptIcon = fn ($name) => (new \App\Models\Department(['name' => $name]))->icon();
    $tones = ['primary', 'success', 'warning', 'purple', 'info', 'danger'];
@endphp
@if ($errors->has('name') || $errors->has('code'))
    @section('reopen-modal', '#deptModal')
@endif

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
    <div>
        <h2 class="h4 fw-bold mb-0">Department Wise Data Tracking</h2>
        <div class="small text-muted">View, manage and track uploaded and shared data for all departments. Click a department to see its full work.</div>
    </div>
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#deptModal"><i class="bi bi-plus-lg me-1"></i>Create Department</button>
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
                @if ($noDept['uploaded'] > 0)
                    @php $pct = $noDept['shared'] ? (int) round($noDept['called'] / $noDept['shared'] * 100) : 0; @endphp
                    <tr class="text-muted">
                        <td class="fw-600"><i class="bi bi-question-circle me-1"></i>No department <span class="small-xs d-block fw-normal">Uploaded before departments were added</span></td>
                        <td class="text-end">{{ number_format($noDept['uploaded']) }}</td>
                        <td class="text-end">{{ number_format($noDept['shared']) }}</td>
                        <td class="text-end text-warning">{{ number_format($noDept['unassigned']) }}</td>
                        <td class="text-end">{{ number_format($noDept['called']) }}</td>
                        <td class="text-end">{{ number_format($noDept['interested']) }}</td>
                        <td class="text-end text-success fw-600">{{ number_format($noDept['converted']) }}</td>
                        <td><div class="d-flex align-items-center gap-2"><x-progress class="flex-grow-1" :value="$pct" tone="secondary" /><span class="small fw-600">{{ $pct }}%</span></div></td>
                        <td></td>
                    </tr>
                @endif
            </tbody>
        </table>
    </div>
</div>

{{-- Create department --}}
<div class="modal fade" id="deptModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" action="{{ route('admin.departments.store') }}" class="modal-content rounded-4 border-0">
            @csrf
            <div class="modal-header border-0">
                <h5 class="modal-title fw-bold">Create Department</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body pt-0">
                <div class="mb-3">
                    <label class="form-label required">Department name</label>
                    <input name="name" value="{{ old('name') }}" maxlength="80" class="form-control @error('name') is-invalid @enderror" placeholder="e.g. Insurance" required>
                    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="mb-3">
                    <label class="form-label">Short code <span class="text-muted fw-normal">(optional)</span></label>
                    <input name="code" value="{{ old('code') }}" maxlength="20" class="form-control text-uppercase @error('code') is-invalid @enderror" placeholder="e.g. INS">
                    @error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div>
                    <label class="form-label">Description <span class="text-muted fw-normal">(optional)</span></label>
                    <input name="description" value="{{ old('description') }}" maxlength="255" class="form-control">
                </div>
                <div class="small text-muted mt-3"><i class="bi bi-info-circle"></i> The new department appears in the sidebar, in Data Upload (step 1) and here, with its own daily work tracking.</div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-primary">Create Department</button>
            </div>
        </form>
    </div>
</div>
@endsection
