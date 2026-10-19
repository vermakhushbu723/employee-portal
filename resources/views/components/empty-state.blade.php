@props(['icon' => 'inbox', 'title', 'text' => null])

<div {{ $attributes->merge(['class' => 'empty-state']) }}>
    <i class="bi bi-{{ $icon }}"></i>
    <div class="fw-600 text-dark">{{ $title }}</div>
    @if ($text)
        <div class="small mt-1">{{ $text }}</div>
    @endif
    {{ $slot }}
</div>
