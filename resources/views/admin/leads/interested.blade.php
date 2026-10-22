@extends('layouts.app')

@section('title', 'Interested Leads')

@section('content')
<div data-live-region="allleads">
    <div class="row g-3 mb-3">
        <div class="col-6 col-lg-3"><x-stat-tile icon="hand-thumbs-up" label="Total Interested" tone="success" :value="$total" /></div>
        <div class="col-6 col-lg-3"><x-stat-tile icon="calendar-check" label="Marked Today" tone="primary" :value="$today" /></div>
    </div>
</div>

<form method="GET" class="card-soft card-pad mb-3 d-flex flex-wrap gap-2">
    <div class="input-icon flex-grow-1">
        <i class="bi bi-search"></i>
        <input type="search" name="q" value="{{ request('q') }}" class="form-control" placeholder="Search customer, mobile or city">
    </div>
    <select name="employee" class="form-select w-auto">
        <option value="">All employees</option>
        @foreach ($employees as $emp)
            <option value="{{ $emp->id }}" @selected($employeeId === $emp->id)>{{ $emp->name }} ({{ $emp->employee_code }})</option>
        @endforeach
    </select>
    <button class="btn btn-outline-primary"><i class="bi bi-funnel"></i> Filter</button>
    <a href="{{ request()->fullUrlWithQuery(['export' => 1]) }}" class="btn btn-success"><i class="bi bi-file-earmark-excel"></i> Export</a>
</form>

<div class="card-soft" data-live-region="allleads">
    <div class="card-pad pb-2 section-head">
        <h3 class="section-title">Interested Leads <span class="badge-soft tone-success ms-1"><i class="bi bi-broadcast"></i> Live</span></h3>
        <span class="small text-muted">{{ $leads->total() }} lead(s)</span>
    </div>
    <div class="table-responsive">
        <table class="table table-soft">
            <thead><tr><th>Customer</th><th>Mobile</th><th>City</th><th>Interest</th><th>Employee</th><th>Last Remark</th><th>Interested On</th></tr></thead>
            <tbody>
                @forelse ($leads as $lead)
                    @php $owner = $lead->assignee ?? $lead->creator; @endphp
                    <tr>
                        <td class="fw-600">{{ $lead->name }}@if ($lead->batch_id)<div class="small-xs text-muted">From uploaded data</div>@endif</td>
                        <td class="text-nowrap"><a href="tel:+91{{ $lead->mobile }}">{{ $lead->mobile }}</a></td>
                        <td>{{ $lead->city ?? '—' }}</td>
                        <td class="small">{{ $lead->interest }}</td>
                        <td class="text-nowrap">{{ $owner?->name ?? '—' }}<div class="small-xs text-muted">{{ $owner?->employee_code }}</div></td>
                        <td class="small" style="max-width: 260px">{{ $lead->latestActivity?->note ?: '—' }}</td>
                        <td class="small text-nowrap">{{ $lead->updated_at->format('d M, h:i A') }}<div class="small-xs text-muted">{{ $lead->updated_at->diffForHumans() }}</div></td>
                    </tr>
                @empty
                    <tr><td colspan="7"><x-empty-state icon="hand-thumbs-up" title="No interested leads yet" text="Leads appear here as soon as an employee marks them Interested." /></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($leads->hasPages())
        <div class="card-pad pt-0">{{ $leads->links() }}</div>
    @endif
</div>
@endsection
