{{-- One lead on phones (cards). $lead --}}
@use('App\Enums\LeadStatus')
@php
    $hr = $lead->isHr();
    $next = $lead->meetings->first();
    $digits = preg_replace('/\D/', '', $lead->mobile);
@endphp
<div class="lead-card lead-row-t" data-lead="{{ $lead->id }}" data-name="{{ $lead->name }}" data-mobile="{{ $lead->mobile }}" data-email="{{ $lead->email }}">
    <a href="{{ route('employee.leads.show', $lead) }}" class="lead-card-main">
        <span class="avatar tone-{{ $lead->status->tone() }}">{{ $lead->initials }}</span>
        <span class="min-w-0 flex-grow-1">
            <b class="d-block text-truncate">{{ $lead->name }}</b>
            <span class="small text-muted d-block"><i class="bi bi-telephone"></i> {{ $lead->mobile }}</span>
            @if ($lead->city)<span class="small text-muted d-block"><i class="bi bi-geo-alt"></i> {{ $lead->city }}</span>@endif
            <span class="small d-block text-truncate">{{ $lead->purpose ?? $lead->interest }}</span>
        </span>
    </a>
    <div class="lead-card-side">
        <a class="act-btn tone-success" href="tel:+91{{ $digits }}" data-call="{{ route('employee.leads.call.start', $lead) }}" aria-label="Call"><i class="bi bi-telephone-fill"></i></a>
        <button type="button" class="act-btn tone-success border-0" data-wa-open-lead aria-label="WhatsApp"><i class="bi bi-whatsapp"></i></button>
        <button type="button" class="act-btn tone-primary border-0" data-email-open aria-label="Email"><i class="bi bi-envelope-fill"></i></button>
        <div class="dropdown d-inline">
            <button class="btn btn-sm btn-light px-1" data-bs-toggle="dropdown" aria-label="More"><i class="bi bi-three-dots-vertical"></i></button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item" href="{{ route('employee.leads.show', $lead) }}"><i class="bi bi-eye me-2"></i>Details</a></li>
                <li><a class="dropdown-item" href="{{ route('employee.meetings.index', ['lead' => $lead->id]) }}"><i class="bi bi-calendar-plus me-2"></i>Book meeting</a></li>
                <li><button type="button" class="dropdown-item" data-record><i class="bi bi-mic-fill me-2"></i>Voice note</button></li>
                <li><button type="button" class="dropdown-item" data-modal="#statusModal" data-action="{{ route('employee.leads.status', $lead) }}" data-fill='@json(['lead_name' => $lead->name, 'status' => $lead->status->value])'><i class="bi bi-arrow-repeat me-2"></i>Change status</button></li>
            </ul>
        </div>
    </div>
    <div class="lead-card-foot">
        <x-badge :tone="$lead->status->tone()">{{ $lead->status->label($hr) }}</x-badge>
        @if ($lead->corrected_at)<span class="corrected-tag"><i class="bi bi-patch-check-fill"></i> Corrected</span>@endif
        @if ($next)<span class="small text-muted ms-auto"><i class="bi bi-calendar-event"></i> {{ $next->scheduled_at->format('d M, h:i A') }}</span>
        @elseif ($lead->next_action_at)<span class="small text-muted ms-auto"><i class="bi bi-telephone"></i> {{ $lead->next_action_at->format('d M, h:i A') }}</span>@endif
    </div>
</div>
