@extends('layouts.app')

@use('App\Enums\LeadStatus')

@section('title', 'My Leads')
@section('back', route('employee.dashboard'))

@section('content')
@if ($errors->has('status') || $errors->has('follow_up_date'))
    <div class="alert alert-danger small"><i class="bi bi-exclamation-octagon-fill me-1"></i>Status not updated: {{ $errors->first('status') ?: $errors->first('follow_up_date') }}</div>
@endif

@php
    $tabList = [['all', 'All Leads', $total, 'primary', 'collection-fill'], ['new', 'New Data', (int) ($counts['new'] ?? 0), 'info', 'stars'], ['today', 'Today Task', $todayCount, 'danger', 'calendar-check-fill']];
    foreach ($statusTabs as $st) {
        $e = LeadStatus::from($st);
        $tabList[] = [$st, $e->label(), (int) ($counts[$st] ?? 0), $e->tone(), $e->icon()];
    }
    $keep = array_filter(['tab' => $tab, 'batch' => $batch?->id, 'city' => $filters['city'] ?: null, 'source' => $filters['source'] ?: null, 'q' => $filters['search'] ?: null]);
@endphp

<div class="d-flex flex-wrap align-items-center gap-2 mb-2">
    <div class="flex-grow-1">
        <h1 class="h5 fw-bold mb-0">My Leads</h1>
        <div class="small text-muted">Manage your leads, call, send messages, schedule meetings and update status</div>
    </div>
    <a href="{{ route('employee.leads.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>New Lead</a>
</div>

{{-- Status tabs — refresh live --}}
<div data-live-region="leads">
    <nav class="lead-tabs mb-3" aria-label="Lead status">
        @foreach ($tabList as [$key, $label, $count, $tone, $icon])
            <a href="{{ route('employee.leads.index', array_merge($keep, ['tab' => $key])) }}" @class(['lead-tab', "tone-{$tone}", 'active' => $tab === $key])>
                <i class="bi bi-{{ $icon }}"></i><span>{{ $label }}</span><b>{{ $count }}</b>
            </a>
        @endforeach
    </nav>
</div>

<form method="GET" class="lead-filters mb-3">
    <input type="hidden" name="tab" value="{{ $tab }}">
    @if ($batch)<input type="hidden" name="batch" value="{{ $batch->id }}">@endif
    <div class="input-icon flex-grow-1">
        <i class="bi bi-search"></i>
        <input type="search" name="q" value="{{ $filters['search'] }}" class="form-control form-control-sm" placeholder="Search name or mobile…">
    </div>
    <select name="city" class="form-select form-select-sm w-auto" onchange="this.form.submit()"><option value="">All Cities</option>@foreach ($cities as $c)<option @selected($filters['city'] === $c)>{{ $c }}</option>@endforeach</select>
    <select name="source" class="form-select form-select-sm w-auto" onchange="this.form.submit()"><option value="">All Sources</option>@foreach ($sources as $s)<option @selected($filters['source'] === $s)>{{ $s }}</option>@endforeach</select>
    <select class="form-select form-select-sm w-auto" onchange="location.href=this.value">
        <option value="{{ route('employee.leads.index', array_merge($keep, ['tab' => 'all'])) }}">All Status</option>
        @foreach (LeadStatus::cases() as $s)<option value="{{ route('employee.leads.index', array_merge($keep, ['tab' => $s->value])) }}" @selected($tab === $s->value)>{{ $s->label() }}</option>@endforeach
    </select>
    <div class="dropdown">
        <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="dropdown" data-bs-auto-close="outside"><i class="bi bi-plus-lg me-1"></i>Add Field</button>
        <div class="dropdown-menu p-2 field-menu">
            <div class="input-icon mb-2"><i class="bi bi-search"></i><input type="search" class="form-control form-control-sm" placeholder="Search field…" data-field-search></div>
            @forelse ($extraFields as $f)
                <label class="dropdown-item small" data-field-item><input type="checkbox" class="form-check-input me-2" data-extra-field="{{ $f }}"> {{ $f }}</label>
            @empty
                <div class="small text-muted px-2">Extra columns from uploaded files (Age, Experience, …) appear here.</div>
            @endforelse
        </div>
    </div>
    <a href="{{ route('employee.leads.index', ['tab' => $tab]) }}" class="btn btn-sm btn-light"><i class="bi bi-arrow-counterclockwise me-1"></i>Reset</a>
    <div class="dropdown">
        <button type="button" class="btn btn-sm btn-light" data-bs-toggle="dropdown" data-bs-auto-close="outside"><i class="bi bi-layout-three-columns me-1"></i>Columns</button>
        <div class="dropdown-menu p-2">
            @foreach (['city' => 'City', 'age' => 'Age', 'purpose' => 'Purpose of Connect', 'remark' => 'Remark', 'next' => 'Next Meeting', 'talk' => 'Talk Time'] as $col => $cl)
                <label class="dropdown-item small"><input type="checkbox" class="form-check-input me-2" data-col-toggle="{{ $col }}" checked> {{ $cl }}</label>
            @endforeach
        </div>
    </div>
</form>

@if ($batch)
    <div class="info-box mb-2"><i class="bi bi-file-earmark-spreadsheet-fill"></i><div class="flex-grow-1">Showing leads from file <b>{{ $batch->file_name ?: $batch->name ?: 'Shared data' }}</b>.</div><a href="{{ route('employee.leads.index', ['tab' => $tab]) }}" class="fw-600 text-nowrap">Show all</a></div>
@endif
@if ($tab === 'today')
    <div class="info-box mb-2"><i class="bi bi-info-circle-fill"></i><div>Today's work: new data not called yet + follow-ups, callbacks and meetings due today or earlier.</div></div>
@endif

<div class="leads-layout">
    <div class="card-soft leads-main" data-live-region="leads" id="leadsList" data-panel-url="{{ route('employee.leads.panel', 0) }}">
        {{-- Desktop: table --}}
        <div class="table-responsive d-none d-lg-block">
            <table class="table table-soft align-middle leads-table mb-0">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Name & Mobile</th>
                        <th data-col="city">City</th>
                        <th data-col="age">Age</th>
                        @foreach ($extraFields as $f)<th class="d-none" data-extra-col="{{ $f }}">{{ $f }}</th>@endforeach
                        <th style="min-width:150px">Status</th>
                        <th data-col="purpose" style="min-width:150px">Purpose of Connect</th>
                        <th data-col="remark" style="min-width:160px">Remark</th>
                        <th data-col="next">Next Meeting</th>
                        <th data-col="talk">Talk Time</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($leads as $i => $lead)
                        @include('employee.leads.partials.row', ['lead' => $lead, 'n' => $leads->firstItem() + $i])
                    @empty
                        <tr><td colspan="12"><x-empty-state icon="people" title="No leads in this tab" text="Leads move here automatically when their status changes." /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Phones: cards --}}
        <div class="d-lg-none lead-cards">
            @forelse ($leads as $lead)
                @include('employee.leads.partials.card', ['lead' => $lead])
            @empty
                <x-empty-state icon="people" title="No leads in this tab" />
            @endforelse
        </div>

        <div class="d-flex align-items-center card-pad py-2 small">
            <span class="text-muted">Showing {{ $leads->firstItem() ?? 0 }} – {{ $leads->lastItem() ?? 0 }} of {{ $leads->total() }}</span>
            <div class="ms-auto">{{ $leads->onEachSide(1)->links() }}</div>
        </div>
    </div>

    {{-- Lead details side panel (desktop) --}}
    <aside class="card-soft lead-panel d-none" id="leadPanel" aria-label="Lead details"></aside>
</div>

@include('employee.leads.partials.status-modal')
@include('employee.leads.partials.comm-modals')
@endsection

@push('scripts')
<script src="{{ asset('js/leads.js') }}?v={{ filemtime(public_path('js/leads.js')) }}"></script>
@endpush
