@extends('layouts.app')

@section('title', 'My Leads')
@section('back', route('employee.dashboard'))
@section('content')
@if ($errors->has('status') || $errors->has('follow_up_date'))
    <div class="alert alert-danger small">
        <i class="bi bi-exclamation-octagon-fill me-1"></i>
        Status not updated: {{ $errors->first('status') ?: $errors->first('follow_up_date') }}
    </div>
@endif

<div class="d-flex flex-wrap gap-2 align-items-center mb-3">
    <nav class="seg-tabs flex-grow-1">
        <a href="{{ route('employee.leads.index') }}" @class(['active' => $tab === 'all'])>My Leads ({{ $total }})</a>
        <a href="{{ route('employee.leads.index', ['tab' => 'follow_up']) }}" @class(['active' => $tab === 'follow_up'])>Follow-up ({{ $counts['follow_up'] ?? 0 }})</a>
        <a href="{{ route('employee.leads.index', ['tab' => 'converted']) }}" @class(['active' => $tab === 'converted'])>Converted ({{ $counts['converted'] ?? 0 }})</a>
    </nav>
    <a href="{{ route('employee.leads.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>New Lead</a>
</div>

<div class="row g-2 g-md-3 mb-3">
    <div class="col-6 col-md-3"><x-stat-tile icon="calendar-plus" label="Today's Leads" tone="primary" :value="$today" /></div>
    <div class="col-6 col-md-3"><x-stat-tile icon="hand-thumbs-up" label="Interested" tone="success" :value="$counts['interested'] ?? 0" /></div>
    <div class="col-6 col-md-3"><x-stat-tile icon="telephone-forward" label="Follow-up" tone="warning" :value="$counts['follow_up'] ?? 0" /></div>
    <div class="col-6 col-md-3"><x-stat-tile icon="x-octagon" label="Invalid / Rejected" tone="danger" :value="($counts['invalid'] ?? 0) + ($counts['not_interested'] ?? 0)" /></div>
</div>

<form method="GET" class="card-soft card-pad mb-3 d-flex flex-wrap gap-2">
    <input type="hidden" name="tab" value="{{ $tab }}">
    <div class="input-icon flex-grow-1">
        <i class="bi bi-search"></i>
        <input type="search" name="q" value="{{ request('q') }}" class="form-control" placeholder="Search name, mobile or city">
    </div>
    <select name="status" class="form-select w-auto">
        <option value="">All Status</option>
        @foreach (\App\Enums\LeadStatus::cases() as $status)
            <option value="{{ $status->value }}" @selected(request('status') === $status->value)>{{ $status->label() }}</option>
        @endforeach
    </select>
    <button class="btn btn-outline-primary"><i class="bi bi-funnel"></i> Filter</button>
</form>

<div class="card-soft card-pad">
    @forelse ($leads as $lead)
        <div class="list-row">
            <a href="{{ route('employee.leads.show', $lead) }}" class="avatar tone-{{ $lead->status->tone() }}">{{ $lead->initials }}</a>
            <a href="{{ route('employee.leads.show', $lead) }}" class="flex-grow-1 min-w-0 list-row-link">
                <div class="list-title text-truncate">{{ $lead->name }}</div>
                <div class="small text-muted">+91 {{ $lead->mobile }}</div>
                <div class="small text-muted"><i class="bi bi-geo-alt"></i> {{ $lead->city }}</div>
            </a>
            <div class="text-end">
                <x-badge :tone="$lead->status->tone()">{{ $lead->status->label() }}</x-badge>
                <div class="small-xs text-muted mt-1">
                    @if ($lead->status === \App\Enums\LeadStatus::FollowUp && $lead->follow_up_date)
                        Follow-up<br><b>{{ $lead->follow_up_date->format('d M Y') }}</b>
                    @elseif ($lead->converted_at)
                        Converted On<br><b>{{ $lead->converted_at->format('d M Y') }}</b>
                    @else
                        Updated<br><b>{{ $lead->updated_at->format('d M Y') }}</b>
                    @endif
                </div>
            </div>
            <div class="dropdown">
                <button class="btn-icon" data-bs-toggle="dropdown" aria-label="Lead actions"><i class="bi bi-three-dots-vertical"></i></button>
                <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 rounded-4 p-2">
                    <li><a class="dropdown-item rounded-3" href="tel:+91{{ $lead->mobile }}"><i class="bi bi-telephone me-2"></i>Call</a></li>
                    <li><a class="dropdown-item rounded-3" href="{{ route('employee.leads.show', $lead) }}"><i class="bi bi-eye me-2"></i>View Details</a></li>
                    <li>
                        <a class="dropdown-item rounded-3" href="#" data-modal="#statusModal"
                           data-action="{{ route('employee.leads.status', $lead) }}"
                           data-fill='@json(['lead_name' => $lead->name, 'status' => $lead->status->value])'>
                            <i class="bi bi-arrow-repeat me-2"></i>Update Status
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    @empty
        <x-empty-state icon="people" title="No leads found" text="Create your first lead to see it here." />
    @endforelse

    @if ($leads->hasPages())
        <div class="mt-3">{{ $leads->links() }}</div>
    @endif
</div>

@include('employee.leads.partials.status-modal')
@endsection
