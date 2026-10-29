{{-- Lead details side panel (loaded into #leadPanel). $lead, $templates --}}
@php
    $hr = $lead->isHr();
    $info = array_filter(['Email' => $lead->email, 'City' => $lead->city, 'State' => $lead->state, 'Source' => $lead->source, 'Interest' => $lead->interest, 'Department' => $lead->department?->name]);
    foreach ($lead->extraFields() as $k => $v) {
        if ($v !== null && $v !== '' && ! in_array(strtolower($k), ['name', 'mobile', 'city', 'email'], true)) {
            $info[$k] = $v;
        }
    }
    $next = $lead->meetings->whereIn('status', ['pending', 'upcoming', 'live'])->sortBy('scheduled_at')->first();
    $copyAll = collect(['Name' => $lead->name, 'Mobile' => '+91 '.$lead->mobile, 'Purpose' => $lead->purpose ?? $lead->interest] + $info + ['Remark' => $lead->remarks])
        ->filter(fn ($v) => $v !== null && $v !== '')->map(fn ($v, $k) => "{$k}: {$v}")->implode("\n");
@endphp
<div class="lead-panel-head">
    <b>Lead Details</b>
    <button type="button" class="btn-close" data-close-panel aria-label="Close"></button>
</div>
<div class="lead-panel-body" data-lead="{{ $lead->id }}" data-name="{{ $lead->name }}" data-mobile="{{ $lead->mobile }}" data-email="{{ $lead->email }}">
  {{-- 1. Lead + details: scrolls on its own --}}
  <section class="lp-top">
    <div class="d-flex gap-2 align-items-start mb-3">
        <span class="avatar lg tone-{{ $lead->status->tone() }}">{{ $lead->initials }}</span>
        <div class="flex-grow-1 min-w-0">
            <div class="fw-bold">{{ $lead->name }}</div>
            <div class="small text-muted"><i class="bi bi-telephone"></i> +91 {{ $lead->mobile }} <button type="button" class="lp-copy" data-copy="{{ $lead->mobile }}" data-copy-quiet title="Copy mobile"><i class="bi bi-copy"></i></button></div>
            @if ($lead->city)<div class="small text-muted"><i class="bi bi-geo-alt"></i> {{ $lead->city }}</div>@endif
        </div>
        <x-badge :tone="$lead->status->tone()">{{ $lead->status->label($hr) }}</x-badge>
    </div>
    @if ($lead->corrected_at)
        <div class="corrected-box mb-3"><i class="bi bi-patch-check-fill"></i><div><b>Review & correction by {{ $lead->corrector?->publicName() }}</b><div class="small">{{ $lead->correction_note }}</div><div class="small-xs text-muted">{{ $lead->corrected_at->format('d M, h:i A') }}</div></div></div>
    @endif

    <div class="d-flex align-items-center mb-1">
        <div class="fw-bold small flex-grow-1">Additional Info</div>
        <button type="button" class="btn btn-sm btn-light py-0" data-copy="{{ $copyAll }}"><i class="bi bi-copy"></i> Copy all</button>
    </div>
    <dl class="lead-info">
        <dt>Purpose</dt><dd>{{ $lead->purpose ?? $lead->interest ?? '—' }}</dd>
        @foreach ($info as $k => $v)<dt>{{ $k }}</dt><dd><span>{{ $v }}</span> <button type="button" class="lp-copy" data-copy="{{ $v }}" data-copy-quiet title="Copy {{ $k }}"><i class="bi bi-copy"></i></button></dd>@endforeach
        @if ($lead->remarks)<dt>Remark</dt><dd>{{ $lead->remarks }}</dd>@endif
    </dl>
  </section>

  {{-- 2. Activity / WhatsApp / E-mail / Meeting: its own scroll --}}
  <section class="lp-bottom">

    <ul class="nav nav-tabs panel-tabs small mb-2" role="tablist">
        @foreach (['activity' => 'Activity', 'whatsapp' => 'WhatsApp', 'email' => 'Email', 'meeting' => $hr ? 'Interview' : 'Meeting'] as $k => $l)
            <li class="nav-item"><button class="nav-link {{ $loop->first ? 'active' : '' }}" data-bs-toggle="tab" data-bs-target="#pt-{{ $k }}-{{ $lead->id }}" type="button">{{ $l }}</button></li>
        @endforeach
    </ul>
    <div class="tab-content">
        <div class="tab-pane fade show active" id="pt-activity-{{ $lead->id }}">
            @php
                $timeline = $lead->communications->map(fn ($c) => ['t' => $c->created_at, 'c' => $c])
                    ->concat($lead->activities->take(15)->map(fn ($a) => ['t' => $a->created_at, 'a' => $a]))->sortByDesc('t')->take(20);
            @endphp
            @forelse ($timeline as $item)
                @if (isset($item['c']))
                    @php $c = $item['c']; [$cl, $ci, $ct] = $c->meta(); @endphp
                    <div class="tl-item"><span class="icon-box sm tone-{{ $c->status === 'failed' ? 'danger' : $ct }}"><i class="bi bi-{{ $ci }}"></i></span>
                        <div class="min-w-0 flex-grow-1"><div class="small fw-600">{{ $cl }} {{ $c->channel === 'call' ? 'connected' : ($c->status === 'failed' ? 'not sent' : 'sent') }}</div><div class="small-xs text-muted">{{ $c->created_at->format('d M Y, h:i A') }}</div>
                            @if ($c->channel === 'voice_note' && $c->file_path)<audio controls preload="none" src="{{ route('lead-files.show', $c) }}" class="w-100 mt-1" style="height:32px"></audio>@endif
                            @if ($c->body && $c->channel !== 'call')<div class="small-xs text-truncate">{{ $c->subject ? $c->subject.' — ' : '' }}{{ $c->body }}</div>@endif
                        </div>
                        @if ($c->duration_seconds)<span class="small-xs text-muted">{{ gmdate('i:s', min(3599, $c->duration_seconds)) }}</span>@endif
                    </div>
                @else
                    @php $a = $item['a']; @endphp
                    <div class="tl-item"><span class="icon-box sm tone-{{ $a->type === 'correction' ? 'warning' : 'secondary' }}"><i class="bi bi-{{ $a->type === 'correction' ? 'patch-check' : 'arrow-repeat' }}"></i></span>
                        <div class="min-w-0"><div class="small fw-600">{{ $a->type === 'correction' ? 'Reviewed & corrected' : 'Status: '.(\App\Enums\LeadStatus::tryFrom((string) $a->to_status)?->label($hr) ?? $a->to_status) }}</div><div class="small-xs text-muted">{{ $a->created_at->format('d M, h:i A') }} · {{ $a->user?->name }}</div>@if ($a->note)<div class="small-xs">{{ \Illuminate\Support\Str::limit($a->note, 120) }}</div>@endif</div>
                    </div>
                @endif
            @empty
                <div class="small text-muted py-2">No activity yet.</div>
            @endforelse
        </div>
        @foreach (['whatsapp', 'email'] as $ch)
            <div class="tab-pane fade" id="pt-{{ $ch }}-{{ $lead->id }}">
                @forelse ($lead->communications->where('channel', $ch) as $c)
                    <div class="tl-item"><span class="icon-box sm tone-{{ $c->status === 'failed' ? 'danger' : ($ch === 'email' ? 'primary' : 'success') }}"><i class="bi bi-{{ $ch === 'email' ? 'envelope-fill' : 'whatsapp' }}"></i></span>
                        <div class="min-w-0"><div class="small-xs text-muted">{{ $c->created_at->format('d M, h:i A') }}{{ $c->status === 'failed' ? ' · not sent' : '' }}</div>@if ($c->subject)<div class="small fw-600">{{ $c->subject }}</div>@endif<div class="small">{{ \Illuminate\Support\Str::limit($c->body, 160) }}</div></div></div>
                @empty
                    <div class="small text-muted py-2">Nothing sent yet.</div>
                @endforelse
            </div>
        @endforeach
        <div class="tab-pane fade" id="pt-meeting-{{ $lead->id }}">
            @forelse ($lead->meetings as $m)
                @php $om = $m->outcomeMeta(); [$ml, $mt] = $m->badge(); @endphp
                <div class="tl-item"><span class="icon-box sm tone-purple"><i class="bi bi-calendar-event-fill"></i></span>
                    <div class="flex-grow-1 min-w-0"><div class="small fw-600">{{ $m->title }}</div><div class="small-xs text-muted">{{ $m->scheduled_at->format('d M Y, h:i A') }}{{ $m->durationLabel() ? ' · '.$m->durationLabel() : '' }}</div></div>
                    @if ($om)<x-badge :tone="$om[1]">{{ $om[0] }}</x-badge>@else<x-badge :tone="$mt">{{ $ml }}</x-badge>@endif
                </div>
            @empty
                <div class="small text-muted py-2">No meeting yet.</div>
            @endforelse
        </div>
    </div>

    @if ($next)
        <div class="next-meet mt-3"><i class="bi bi-calendar-event-fill"></i><div class="flex-grow-1"><b>{{ $hr ? 'Interview' : 'Meeting' }} scheduled</b><div class="small">{{ $next->scheduled_at->format('d M Y, h:i A') }}</div></div>
            @if ($next->isPortal() && in_array($next->status, ['upcoming', 'live'], true))<form method="POST" action="{{ route('employee.meetings.join', $next) }}">@csrf<button class="btn btn-sm btn-primary">Join</button></form>@endif
        </div>
    @endif

  </section>
    <div class="lp-foot">
        <a href="{{ route('employee.leads.show', $lead) }}" class="btn btn-outline-primary btn-sm w-100">View More Details <i class="bi bi-chevron-right"></i></a>
    </div>
</div>
