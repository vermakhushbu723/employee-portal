@php
    $menu = config('portal.menus.'.auth()->user()->role->portal(), []);
@endphp

<aside class="offcanvas-lg offcanvas-start app-sidebar" tabindex="-1" id="appSidebar" aria-label="Main menu">
    <div class="offcanvas-header border-bottom d-lg-none">
        <div class="sidebar-brand p-0">
            <span class="brand-mark"><img src="{{ asset('images/logo-mark.png') }}" alt=""></span>
            <span>{{ config('portal.company') }}<small>Work Together. Grow Together.</small></span>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" data-bs-target="#appSidebar" aria-label="Close"></button>
    </div>

    <div class="offcanvas-body">
        <a href="{{ route('home') }}" class="sidebar-brand d-none d-lg-flex">
            <span class="brand-mark"><img src="{{ asset('images/logo-mark.png') }}" alt=""></span>
            <span>{{ config('portal.company') }}<small>Work Together. Grow Together.</small></span>
        </a>

        <nav class="nav flex-column sidebar-nav">
            @foreach ($menu as $item)
                @isset($item['heading'])
                    <div class="sidebar-heading">{{ $item['heading'] }}</div>
                @else
                    <a href="{{ route($item['route']) }}"
                       @class(['nav-link', 'active' => request()->routeIs(...(array) ($item['active'] ?? $item['route']))])>
                        <i class="bi bi-{{ $item['icon'] }}"></i>
                        <span>{{ $item['label'] }}</span>
                    </a>
                @endisset
            @endforeach
        </nav>

        <form method="POST" action="{{ route('logout') }}" class="mt-auto pt-4">
            @csrf
            <button type="submit" class="btn btn-outline-danger w-100">
                <i class="bi bi-box-arrow-right me-1"></i> Sign out
            </button>
        </form>
    </div>
</aside>
