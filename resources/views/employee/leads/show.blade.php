@extends('layouts.app')

@section('title', 'Lead Details')
@section('back', route('employee.leads.index'))
@if ($errors->has('status') || $errors->has('follow_up_date'))
    @section('reopen-modal', '#statusModal')
@endif

@section('content')
<div class="row g-3">
    <div class="col-lg-5">
        <div class="card-soft card-pad text-center mb-3">
            <span class="avatar xl mx-auto mb-2 tone-{{ $lead->status->tone() }}">{{ $lead->initials }}</span>
            <h2 class="h5 fw-bold mb-1">{{ $lead->name }}</h2>
            <x-badge :tone="$lead->status->tone()">{{ $lead->status->label() }}</x-badge>
            <div class="d-flex gap-2 mt-3">
                <a href="tel:+91{{ $lead->mobile }}" class="btn btn-success flex-fill"><i class="bi bi-telephone-fill me-1"></i>Call</a>
                <button type="button" class="btn btn-outline-primary flex-fill" data-bs-toggle="modal" data-bs-target="#statusModal">
                    <i class="bi bi-arrow-repeat me-1"></i>Update Status
                </button>
            </div>
        </div>

        <div class="card-soft card-pad">
            <div class="kv-row"><span class="k"><i class="bi bi-telephone"></i>Mobile</span><span class="v">+91 {{ $lead->mobile }}</span></div>
            @if ($lead->alternate_mobile)
                <div class="kv-row"><span class="k"><i class="bi bi-telephone-plus"></i>Alternate</span><span class="v">+91 {{ $lead->alternate_mobile }}</span></div>
            @endif
            <div class="kv-row"><span class="k"><i class="bi bi-envelope"></i>Email</span><span class="v">{{ $lead->email ?? '—' }}</span></div>
            <div class="kv-row"><span class="k"><i class="bi bi-geo-alt"></i>City</span><span class="v">{{ $lead->city }}{{ $lead->state ? ', '.$lead->state : '' }}</span></div>
            <div class="kv-row"><span class="k"><i class="bi bi-megaphone"></i>Source</span><span class="v">{{ $lead->source }}</span></div>
            <div class="kv-row"><span class="k"><i class="bi bi-bag"></i>Interested In</span><span class="v">{{ $lead->interest }}</span></div>
            <div class="kv-row"><span class="k"><i class="bi bi-calendar-event"></i>Follow-up</span><span class="v">{{ $lead->follow_up_date?->format('d M Y') ?? '—' }}</span></div>
            <div class="kv-row"><span class="k"><i class="bi bi-clock"></i>Created</span><span class="v">{{ $lead->created_at->format('d M Y, h:i A') }}</span></div>
        </div>
    </div>

    <div class="col-lg-7">
        @if ($lead->extra)
            <div class="card-soft card-pad mb-3">
                <h3 class="section-title mb-2"><i class="bi bi-file-earmark-spreadsheet me-1"></i>Customer Details (from data file)</h3>
                @foreach ($lead->extra as $field => $value)
                    <div class="kv-row"><span class="k">{{ $field }}</span><span class="v text-break">{{ $value }}</span></div>
                @endforeach
            </div>
        @endif

        @if ($lead->remarks)
            <div class="card-soft card-pad mb-3">
                <h3 class="section-title mb-2">Remarks</h3>
                <p class="mb-0 text-muted">{{ $lead->remarks }}</p>
            </div>
        @endif

        <div class="card-soft card-pad">
            <h3 class="section-title mb-2">Activity History</h3>
            @forelse ($lead->activities as $activity)
                <div class="list-row">
                    <span class="icon-box sm soft tone-{{ $activity->type === 'follow_up' ? 'warning' : ($activity->type === 'created' ? 'primary' : 'success') }}">
                        <i class="bi bi-{{ $activity->type === 'follow_up' ? 'telephone-forward' : ($activity->type === 'created' ? 'plus-lg' : 'arrow-repeat') }}"></i>
                    </span>
                    <div class="flex-grow-1 min-w-0">
                        <div class="fw-600 small">
                            @if ($activity->type === 'created')
                                Lead created
                            @else
                                {{ \App\Enums\LeadStatus::tryFrom((string) $activity->from_status)?->label() }} → {{ \App\Enums\LeadStatus::tryFrom((string) $activity->to_status)?->label() }}
                            @endif
                        </div>
                        @if ($activity->note)<div class="small text-muted">{{ $activity->note }}</div>@endif
                    </div>
                    <span class="small-xs text-muted text-nowrap">{{ $activity->created_at->format('d M, h:i A') }}</span>
                </div>
            @empty
                <x-empty-state icon="clock-history" title="No activity yet" />
            @endforelse
        </div>
    </div>
</div>

@include('employee.leads.partials.status-modal', [
    'action' => route('employee.leads.status', $lead),
    'leadName' => $lead->name,
    'current' => $lead->status->value,
])
@endsection
