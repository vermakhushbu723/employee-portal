{{-- One lead in the desktop table. $lead, $n --}}
@use('App\Enums\LeadStatus')
@php
    $hr = $lead->isHr();
    $next = $lead->meetings->first();
    $digits = preg_replace('/\D/', '', $lead->mobile);
@endphp
<tr class="lead-row-t" data-lead="{{ $lead->id }}" data-name="{{ $lead->name }}" data-mobile="{{ $lead->mobile }}" data-email="{{ $lead->email }}">
    <td class="text-muted small">{{ $n }}</td>
    <td>
        <div class="d-flex align-items-center gap-2">
            <span class="avatar tone-{{ $lead->status->tone() }}" style="width:34px;height:34px;font-size:.72rem">{{ $lead->initials }}</span>
            <div class="min-w-0">
                <button type="button" class="btn btn-link p-0 fw-600 text-dark text-decoration-none text-nowrap" data-open-panel>{{ $lead->name }}</button>
                <div class="small-xs text-muted">+91 {{ $lead->mobile }}</div>
                @if ($lead->assigned_at && $lead->status === LeadStatus::New)<x-badge tone="info"><i class="bi bi-stars"></i> New Data</x-badge>@endif
                @if ($lead->corrected_at)<span class="corrected-tag" title="{{ $lead->correction_note }}"><i class="bi bi-patch-check-fill"></i> Review & correction by {{ $lead->corrector?->role->portal() === 'admin' ? \App\Models\User::MANAGEMENT : 'Manager' }}</span>@endif
            </div>
        </div>
    </td>
    <td data-col="city" class="small">{{ $lead->city ?: '—' }}</td>
    <td data-col="age" class="small">{{ $lead->field('Age') ?? $lead->field('Age (Years)') ?? '—' }}</td>
    @foreach ($extraFields ?? [] as $f)<td class="small d-none" data-extra-col="{{ $f }}">{{ $lead->field($f) ?? '—' }}</td>@endforeach
    <td>
        <form class="lead-status" data-inline-status action="{{ route('employee.leads.status', $lead) }}">
            <select name="status" class="form-select form-select-sm status-select tone-{{ $lead->status->tone() }}" aria-label="Status of {{ $lead->name }}">
                @foreach (LeadStatus::cases() as $s)<option value="{{ $s->value }}" @selected($lead->status === $s)>{{ $s->label($hr) }}</option>@endforeach
            </select>
            <div class="follow-up-pick d-none">
                <input type="date" name="follow_up_date" min="{{ today()->toDateString() }}" value="{{ $lead->follow_up_date?->toDateString() }}" class="form-control form-control-sm" aria-label="Date">
                <button type="submit" class="btn btn-sm btn-primary">Save</button>
            </div>
        </form>
    </td>
    <td data-col="purpose"><input class="form-control form-control-sm lead-inline" data-inline="purpose" value="{{ $lead->purpose ?? $lead->interest }}" maxlength="150" placeholder="Purpose…"></td>
    <td data-col="remark"><input class="form-control form-control-sm lead-inline" data-inline="remarks" value="{{ $lead->remarks }}" maxlength="1000" placeholder="Remark…"></td>
    <td data-col="next" class="small text-nowrap">
        @if ($next)
            <i class="bi bi-calendar-event text-purple"></i> {{ $next->scheduled_at->format('d M, h:i A') }}
            @if ($next->isPortal() && in_array($next->status, ['upcoming', 'live'], true))
                <form method="POST" action="{{ route('employee.meetings.join', $next) }}" class="d-inline">@csrf<button class="btn btn-sm btn-primary py-0 px-2 ms-1">Join</button></form>
            @endif
        @elseif ($lead->next_action_at)
            <i class="bi bi-telephone text-warning"></i> {{ $lead->next_action_at->format('d M, h:i A') }}
        @else <span class="text-muted">—</span> @endif
    </td>
    <td data-col="talk" class="small text-nowrap"><i class="bi bi-clock text-muted"></i> <span data-talk>{{ $lead->talk_seconds ? gmdate('i:s', min(3599, (int) $lead->talk_seconds)) : '00:00' }}</span></td>
    <td class="text-nowrap">
        <a class="act-btn tone-success" href="tel:+91{{ $digits }}" data-call="{{ route('employee.leads.call.start', $lead) }}" title="Call"><i class="bi bi-telephone-fill"></i></a>
        <button type="button" class="act-btn tone-success border-0" data-wa-open-lead title="WhatsApp"><i class="bi bi-whatsapp"></i></button>
        <button type="button" class="act-btn tone-primary border-0" data-email-open title="Email"><i class="bi bi-envelope-fill"></i></button>
        <a class="act-btn tone-purple" href="{{ route('employee.meetings.index', ['lead' => $lead->id]) }}" title="Book meeting"><i class="bi bi-calendar-plus-fill"></i></a>
        <div class="dropdown d-inline">
            <button class="btn btn-sm btn-light" data-bs-toggle="dropdown" aria-label="More"><i class="bi bi-three-dots-vertical"></i></button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><button type="button" class="dropdown-item" data-open-panel><i class="bi bi-layout-sidebar-reverse me-2"></i>Lead details</button></li>
                <li><a class="dropdown-item" href="{{ route('employee.leads.show', $lead) }}"><i class="bi bi-eye me-2"></i>Full details & history</a></li>
                <li><button type="button" class="dropdown-item" data-record><i class="bi bi-mic-fill me-2"></i>Record voice note</button></li>
            </ul>
        </div>
    </td>
</tr>
