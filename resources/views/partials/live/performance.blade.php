{{-- Today's overall performance. $summary, $title --}}
@php
    $pct = fn ($a, $b) => \App\Support\Duration::percent($a, $b);
    $cards = [
        ['telephone-fill', 'Total Calls', $summary['calls'], $summary['calls_target'], 'success'],
        ['people-fill', 'Interested Leads', $summary['interested'], $summary['interested_target'], 'primary'],
        ['file-earmark-plus-fill', 'Leads Created', $summary['created'], null, 'purple'],
        ['calendar-event-fill', 'Meetings Today', $summary['meetings'], null, 'info'],
    ];
@endphp
<div class="card-soft card-pad mb-3">
    <h3 class="section-title mb-3">{{ $title ?? "Today's Overall Performance" }}</h3>
    <div class="row g-2 g-md-3">
        @foreach ($cards as [$icon, $label, $value, $target, $tone])
            <div class="col-6 col-lg-3">
                <div class="perf-card tone-{{ $tone }}">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <span class="icon-box sm"><i class="bi bi-{{ $icon }}"></i></span>
                        <span class="small fw-600">{{ $label }}</span>
                    </div>
                    <div class="fs-5 fw-bold lh-1">{{ number_format($value) }}@if ($target)<span class="small text-muted fw-500"> / {{ number_format($target) }}</span>@endif</div>
                    @if ($target)
                        <div class="d-flex align-items-center gap-2 mt-2">
                            <x-progress class="flex-grow-1" :value="$pct($value, $target)" :tone="$tone" />
                            <span class="small fw-bold">{{ $pct($value, $target) }}%</span>
                        </div>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
</div>
