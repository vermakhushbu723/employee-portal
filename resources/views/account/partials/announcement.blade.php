@php
    [$icon, $tone] = $announcement->style();
@endphp

<div class="list-row align-items-start">
    <span class="icon-box tone-{{ $tone }}"><i class="bi bi-{{ $icon }}"></i></span>
    <div class="flex-grow-1 min-w-0">
        <div class="d-flex justify-content-between gap-2">
            <span class="list-title">{{ $announcement->title }}</span>
            <x-badge :tone="$tone">{{ ucfirst($announcement->type) }}</x-badge>
        </div>
        <div class="small-xs text-muted mb-1">
            {{ $announcement->published_at->isToday() ? 'Today' : ($announcement->published_at->isYesterday() ? 'Yesterday' : $announcement->published_at->format('d M Y')) }},
            {{ $announcement->published_at->format('h:i A') }}
        </div>
        <div class="small text-muted">{{ $announcement->body }}</div>
    </div>
</div>
