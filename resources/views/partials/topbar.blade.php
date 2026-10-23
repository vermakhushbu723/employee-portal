{{-- Desktop: title on the left. Mobile: native-app header (back/menu · centred title · actions). --}}
<header class="app-topbar">
    <div class="topbar-start">
        @hasSection('back')
            <a href="@yield('back')" class="btn-icon d-lg-none" aria-label="Back"><i class="bi bi-arrow-left"></i></a>
        @else
            <button type="button" class="btn-icon d-lg-none" data-bs-toggle="offcanvas" data-bs-target="#appSidebar" aria-label="Open menu">
                <i class="bi bi-list"></i>
            </button>
        @endif
        <h1 class="topbar-title d-none d-lg-block">@yield('title', 'Dashboard')</h1>
    </div>

    <h1 class="topbar-title topbar-title-center d-lg-none">@yield('title', 'Dashboard')</h1>

    <div class="topbar-end">
        <button type="button" class="btn-icon refresh-btn" onclick="this.classList.add('spinning'); location.reload();" title="Refresh" aria-label="Refresh page">
            <i class="bi bi-arrow-clockwise"></i>
        </button>
        @if ($activeTimer)
            {{-- Three running timers: Active (working) · Inactive (no activity > 1 min) · Total (since punch-in) --}}
            <div class="time-chips d-none d-md-flex" id="activeTimer" data-active-timer='@json($activeTimer)'>
                <div class="time-chip tone-success" title="Active working time today — counts only while you use the portal">
                    <span class="dot"></span>
                    <div><span class="tc-label" data-active-state>Active</span><span class="tc-value" data-active-display>{{ \App\Support\Duration::hms($activeTimer['seconds']) }}</span></div>
                </div>
                <div class="time-chip tone-danger" title="Inactive time today — no activity for more than 1 minute, browser closed or device off">
                    <i class="bi bi-pause-circle-fill"></i>
                    <div><span class="tc-label">Inactive</span><span class="tc-value" data-idle-nav>00:00:00</span></div>
                </div>
                <div class="time-chip tone-primary" title="Total time since punch-in (active + inactive + break)">
                    <i class="bi bi-clock-fill"></i>
                    <div><span class="tc-label">Total</span><span class="tc-value" data-total-nav>00:00:00</span></div>
                </div>
            </div>
        @endif
        <a href="{{ route('notifications.index') }}" class="btn-icon position-relative" aria-label="Announcements" id="bellLink">
            <i class="bi bi-bell"></i>
            <span class="topbar-badge {{ $alertCount > 0 ? '' : 'd-none' }}" id="bellBadge">{{ $alertCount > 9 ? '9+' : $alertCount }}</span>
        </a>

        <div class="dropdown">
            <button class="btn d-flex align-items-center gap-2 px-1 py-1 border-0" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Account menu">
                <span class="avatar tone-primary" style="width:34px;height:34px;font-size:.75rem">{{ auth()->user()->initials }}</span>
                <span class="d-none d-lg-block text-start lh-sm">
                    <span class="d-block fw-600 small">{{ auth()->user()->name }}</span>
                    <span class="d-block text-muted small-xs">{{ auth()->user()->employee_code }} · {{ auth()->user()->role->label() }}</span>
                </span>
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 rounded-4 p-2">
                <li class="px-3 py-2 d-lg-none">
                    <div class="fw-600 small">{{ auth()->user()->name }}</div>
                    <div class="text-muted small-xs">{{ auth()->user()->employee_code }} · {{ auth()->user()->role->label() }}</div>
                </li>
                <li><a class="dropdown-item rounded-3" href="{{ route('profile.show') }}"><i class="bi bi-person me-2"></i>My Profile</a></li>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button class="dropdown-item rounded-3 text-danger"><i class="bi bi-box-arrow-right me-2"></i>Sign out</button>
                    </form>
                </li>
            </ul>
        </div>
    </div>
</header>
