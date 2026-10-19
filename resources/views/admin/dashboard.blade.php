@extends('layouts.app')

@section('title', 'Admin Dashboard')

@section('content')
<div class="row g-2 g-md-3 mb-3">
    <div class="col-6 col-lg-3"><x-stat-tile icon="people-fill" label="Active Employees" tone="primary" :value="$stats['employees']" :hint="$stats['managers'].' managers'" /></div>
    <div class="col-6 col-lg-3"><x-stat-tile icon="fingerprint" label="Checked In Today" tone="success" :value="$stats['present']" :hint="$stats['late'].' late'" /></div>
    <div class="col-6 col-lg-3"><x-stat-tile icon="calendar-x" label="On Leave Today" tone="danger" :value="$stats['on_leave']" /></div>
    <div class="col-6 col-lg-3"><x-stat-tile icon="hourglass-split" label="Pending Leaves" tone="warning" :value="$stats['pending_leaves']" /></div>
    <div class="col-6 col-lg-3"><x-stat-tile icon="person-plus-fill" label="Leads Today" tone="purple" :value="$stats['leads_today']" /></div>
    <div class="col-6 col-lg-3"><x-stat-tile icon="trophy-fill" label="Conversions (Month)" tone="info" :value="$stats['conversions_month']" /></div>
    <div class="col-12 col-lg-6">
        <div class="card-soft card-pad h-100 d-flex flex-wrap gap-2 align-items-center">
            <a href="{{ route('admin.employees.create') }}" class="btn btn-primary"><i class="bi bi-person-plus me-1"></i>Add Employee</a>
            <a href="{{ route('admin.leaves.index') }}" class="btn btn-outline-primary"><i class="bi bi-calendar2-check me-1"></i>Leave Approvals</a>
            <a href="{{ route('admin.employees.index') }}" class="btn btn-outline-primary"><i class="bi bi-person-badge me-1"></i>All Employees</a>
        </div>
    </div>
</div>

<div class="card-soft">
    <div class="card-pad pb-2"><h3 class="section-title">Recent Activity (Audit Log)</h3></div>
    <div class="table-responsive">
        <table class="table table-soft">
            <thead><tr><th>Time</th><th>User</th><th>Action</th><th>Description</th><th>IP</th></tr></thead>
            <tbody>
                @forelse ($logs as $log)
                    <tr>
                        <td class="text-nowrap small">{{ $log->created_at->format('d M, h:i A') }}</td>
                        <td class="text-nowrap">{{ $log->user?->name ?? 'System' }}</td>
                        <td><code class="small">{{ $log->action }}</code></td>
                        <td class="small">{{ $log->description }}</td>
                        <td class="small text-muted">{{ $log->ip_address }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5"><x-empty-state icon="journal" title="No activity yet" /></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
