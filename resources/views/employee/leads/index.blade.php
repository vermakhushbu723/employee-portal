@extends('layouts.app')

@use('App\Enums\LeadStatus')

@section('title', 'My Leads')
@section('back', route('employee.dashboard'))

@section('content')
@if ($errors->has('status') || $errors->has('follow_up_date'))
    <div class="alert alert-danger small">
        <i class="bi bi-exclamation-octagon-fill me-1"></i>
        Status not updated: {{ $errors->first('status') ?: $errors->first('follow_up_date') }}
    </div>
@endif

@php
    $tabIcons = [
        'all' => 'collection', 'today' => 'calendar-check',
        'new' => 'stars', 'interested' => 'hand-thumbs-up', 'follow_up' => 'telephone-forward',
        'not_interested' => 'hand-thumbs-down', 'converted' => 'trophy', 'invalid' => 'x-octagon',
    ];
    $tabList = [
        ['all', 'All Leads', $total, 'primary'],
        ['today', 'Today Task', $todayCount, 'danger'],
    ];
    foreach (LeadStatus::cases() as $s) {
        $tabList[] = [$s->value, $s->label(), (int) ($counts[$s->value] ?? 0), $s->tone()];
    }
@endphp

{{-- Status tabs (6 per row) — refresh live --}}
<div data-live-region="leads">
    <nav class="status-tabs mb-3" aria-label="Lead status">
        @foreach ($tabList as [$key, $label, $count, $tone])
            <a href="{{ route('employee.leads.index', ['tab' => $key]) }}" @class(['status-tab', "tone-{$tone}", 'active' => $tab === $key])>
                <i class="bi bi-{{ $tabIcons[$key] ?? 'circle' }}"></i>
                <span class="label">{{ $label }}</span>
                <span class="count">{{ $count }}</span>
            </a>
        @endforeach
    </nav>
</div>

<form method="GET" class="card-soft card-pad mb-3 d-flex flex-wrap gap-2">
    <input type="hidden" name="tab" value="{{ $tab }}">
    <div class="input-icon flex-grow-1">
        <i class="bi bi-search"></i>
        <input type="search" name="q" value="{{ request('q') }}" class="form-control" placeholder="Search name, mobile or city">
    </div>
    <button class="btn btn-outline-primary"><i class="bi bi-search"></i> Search</button>
    <a href="{{ route('employee.leads.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>New Lead</a>
</form>

<div class="card-soft card-pad" data-live-region="leads">
    @if ($tab === 'today')
        <div class="info-box mb-2"><i class="bi bi-info-circle-fill"></i><div>Today's work: new data not called yet + follow-ups due today or earlier.</div></div>
    @endif

    @forelse ($leads as $lead)
        @php $freshData = $lead->assigned_at && $lead->status === LeadStatus::New; @endphp
        <div class="lead-row">
            <a href="{{ route('employee.leads.show', $lead) }}" class="avatar tone-{{ $lead->status->tone() }}">{{ $lead->initials }}</a>

            <a href="{{ route('employee.leads.show', $lead) }}" class="lead-main list-row-link min-w-0">
                <div class="list-title text-truncate">
                    {{ $lead->name }}
                    @if ($freshData)<x-badge tone="info" class="ms-1"><i class="bi bi-stars"></i> New Data</x-badge>@endif
                </div>
                <div class="small text-muted">+91 {{ $lead->mobile }}</div>
                <div class="small text-muted text-truncate"><i class="bi bi-geo-alt"></i> {{ $lead->city ?? '—' }} · {{ $lead->interest }}</div>
            </a>

            <a href="tel:+91{{ $lead->mobile }}" class="btn btn-sm btn-success lead-call" aria-label="Call {{ $lead->name }}"><i class="bi bi-telephone-fill"></i></a>

            {{-- Inline status change (no popup) --}}
            <form class="lead-status" data-inline-status action="{{ route('employee.leads.status', $lead) }}">
                <select name="status" class="form-select form-select-sm status-select tone-{{ $lead->status->tone() }}" aria-label="Status of {{ $lead->name }}">
                    @foreach (LeadStatus::cases() as $s)
                        <option value="{{ $s->value }}" @selected($lead->status === $s)>{{ $s->label() }}</option>
                    @endforeach
                </select>
                <div class="follow-up-pick d-none">
                    <input type="date" name="follow_up_date" min="{{ today()->toDateString() }}" value="{{ $lead->follow_up_date?->toDateString() }}" class="form-control form-control-sm" aria-label="Follow-up date">
                    <button type="submit" class="btn btn-sm btn-primary">Save</button>
                </div>
            </form>

            <div class="lead-date small-xs text-muted text-end">
                @if ($lead->status === LeadStatus::FollowUp && $lead->follow_up_date)
                    Follow-up<br><b @class(['text-danger' => $lead->follow_up_date->lte(today())])>{{ $lead->follow_up_date->format('d M Y') }}</b>
                @elseif ($lead->converted_at)
                    Converted<br><b>{{ $lead->converted_at->format('d M Y') }}</b>
                @elseif ($freshData)
                    Shared<br><b>{{ $lead->assigned_at->format('d M, h:i A') }}</b>
                @else
                    Updated<br><b>{{ $lead->updated_at->format('d M Y') }}</b>
                @endif
            </div>

            <div class="dropdown">
                <button class="btn-icon" data-bs-toggle="dropdown" aria-label="More actions"><i class="bi bi-three-dots-vertical"></i></button>
                <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 rounded-4 p-2">
                    <li><a class="dropdown-item rounded-3" href="{{ route('employee.leads.show', $lead) }}"><i class="bi bi-eye me-2"></i>View Details</a></li>
                    <li>
                        <a class="dropdown-item rounded-3" href="#" data-modal="#statusModal"
                           data-action="{{ route('employee.leads.status', $lead) }}"
                           data-fill='@json(['lead_name' => $lead->name, 'status' => $lead->status->value])'>
                            <i class="bi bi-chat-left-text me-2"></i>Add Call Remarks
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    @empty
        <x-empty-state icon="people" title="No leads in this tab" text="Leads move here automatically when their status changes." />
    @endforelse

    @if ($leads->hasPages())
        <div class="mt-3">{{ $leads->links() }}</div>
    @endif
</div>

@include('employee.leads.partials.status-modal')
@endsection
