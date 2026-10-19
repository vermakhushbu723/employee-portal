{{-- App-style bottom navigation (phones/tablets). Items come from config('portal.bottom_nav'). --}}
@php
    $items = config('portal.bottom_nav.'.auth()->user()->role->portal(), []);
@endphp

<nav class="bottom-nav d-lg-none" aria-label="Quick navigation">
    @foreach ($items as $item)
        @php
            $active = request()->routeIs(...(array) ($item['active'] ?? $item['route']));
        @endphp
        @if (! empty($item['fab']))
            <a href="{{ route($item['route']) }}" class="fab" aria-label="{{ $item['label'] }}">
                <i class="bi bi-{{ $item['icon'] }}"></i>
            </a>
        @else
            <a href="{{ route($item['route']) }}" @class(['active' => $active])>
                <i class="bi bi-{{ $item['icon'] }}{{ $active ? '-fill' : '' }}"></i>{{ $item['label'] }}
            </a>
        @endif
    @endforeach
    <button type="button" data-bs-toggle="offcanvas" data-bs-target="#appSidebar">
        <i class="bi bi-grid"></i>More
    </button>
</nav>
