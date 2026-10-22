<?php

namespace App\Providers;

use App\Models\Announcement;
use App\Services\AttendanceService;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Paginator::useBootstrapFive();

        // Bell badge + active-time timer in the top bar.
        View::composer('partials.topbar', function ($view) {
            $user = auth()->user();
            $activeTimer = null;

            if ($user && $user->role->portal() === 'employee') {
                $session = app(AttendanceService::class)->todaySession($user);
                if ($session) {
                    $activeTimer = [
                        'seconds' => (int) $session->active_seconds,
                        'tracking' => $session->isOpen() && ! $session->activeBreak(),
                        'url' => route('employee.activity.heartbeat'),
                    ];
                }
            }

            $view->with([
                'alertCount' => $user ? Announcement::visibleTo($user)->where('published_at', '>=', now()->subDays(7))->count() : 0,
                'activeTimer' => $activeTimer,
            ]);
        });

        // Employees who have not punched in yet get the punch-in popup on every page.
        View::composer('layouts.app', function ($view) {
            $user = auth()->user();
            $gate = null;

            if ($user && $user->role->portal() === 'employee') {
                $attendance = app(AttendanceService::class);
                if ($attendance->mustPunchIn($user)) {
                    $gate = $attendance->checkInState($user);
                }
            }

            $view->with('punchGate', $gate);
        });
    }
}
