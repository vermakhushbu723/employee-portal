{{-- Browser-side filters for the live employee table (kept outside the live region so they survive refreshes). $rows, $exportUrl --}}
@php
    $teams = $rows->map(fn ($r) => $r['user']->employee?->department?->name ?? '—')->unique()->sort()->values();
    $count = fn ($s) => $rows->filter(fn ($r) => match ($s) {
        'offline' => in_array($r['state'], ['offline', 'out'], true),
        'idle' => in_array($r['state'], ['idle', 'break'], true),
        default => $r['state'] === $s,
    })->count();
@endphp
<div class="d-flex flex-wrap align-items-center gap-2 card-pad pb-2" data-live-filter>
    <nav class="seg-tabs seg-sm flex-wrap">
        <a href="#" class="active" data-filter-state="">All ({{ $rows->count() }})</a>
        <a href="#" data-filter-state="working">Working ({{ $count('working') }})</a>
        <a href="#" data-filter-state="meeting">In Meeting ({{ $count('meeting') }})</a>
        <a href="#" data-filter-state="idle">Idle ({{ $count('idle') }})</a>
        <a href="#" data-filter-state="offline">Offline ({{ $count('offline') }})</a>
    </nav>
    <div class="d-flex gap-2 ms-auto flex-wrap">
        @if ($teams->count() > 1)
            <select class="form-select form-select-sm w-auto" data-filter-team>
                <option value="">All Teams</option>
                @foreach ($teams as $t)<option>{{ $t }}</option>@endforeach
            </select>
        @endif
        <div class="input-icon">
            <i class="bi bi-search"></i>
            <input type="search" class="form-control form-control-sm" placeholder="Search employee…" data-filter-q>
        </div>
        @isset($exportUrl)
            <a href="{{ $exportUrl }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-download me-1"></i>Export</a>
        @endisset
    </div>
</div>
