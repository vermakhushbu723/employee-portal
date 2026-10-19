@props(['tone' => 'secondary'])

<span {{ $attributes->merge(['class' => "badge-soft tone-{$tone}"]) }}>{{ $slot }}</span>
