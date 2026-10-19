@props(['value' => 0, 'tone' => 'primary', 'bind' => null])

<div {{ $attributes->merge(['class' => "progress-soft tone-{$tone}"]) }} role="progressbar" aria-valuenow="{{ $value }}" aria-valuemin="0" aria-valuemax="100">
    <span style="width: {{ max(0, min(100, $value)) }}%" @if ($bind) data-bind-width="{{ $bind }}" @endif></span>
</div>
