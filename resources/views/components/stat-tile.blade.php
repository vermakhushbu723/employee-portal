@props(['icon', 'label', 'value' => '', 'tone' => 'primary', 'hint' => null])

<div {{ $attributes->merge(['class' => "stat-tile tone-{$tone}"]) }}>
    <span class="stat-icon"><i class="bi bi-{{ $icon }}"></i></span>
    <div class="min-w-0">
        <div class="stat-label">{{ $label }}</div>
        <div class="stat-value">{{ $slot->isEmpty() ? $value : $slot }}</div>
        @if ($hint)
            <div class="stat-hint">{{ $hint }}</div>
        @endif
    </div>
</div>
