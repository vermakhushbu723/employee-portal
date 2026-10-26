<?php

namespace App\Providers;

use App\Models\Announcement;
use App\Services\AttendanceService;
use App\Services\WorkScoreService;
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
                    $activeBreak = $session->activeBreak();
                    $closedBreaks = $session->breaks->whereNotNull('ended_at')
                        ->sum(fn ($b) => $b->ended_at->getTimestamp() - $b->started_at->getTimestamp());
                    $end = $session->check_out_at;

                    $activeTimer = [
                        'seconds' => (int) $session->active_seconds,
                        'tracking' => $session->isOpen() && ! $activeBreak,
                        'on_shift' => $session->isOpen(),
                        'url' => route('employee.activity.heartbeat'),
                        'idle_after' => (int) config('portal.work_score.idle_after_seconds', 60),
                        // for the live idle / break counters in the status bar (epoch seconds)
                        'check_in' => $session->check_in_at->getTimestamp(),
                        'ended' => $end?->getTimestamp(),
                        'break_closed' => (int) $closedBreaks,
                        'break_started' => $activeBreak?->started_at->getTimestamp(),
                        'break_allowed' => $user->currentShift()->break_allowed_minutes * 60,
                        'server_now' => now()->getTimestamp(),
                    ];
                }
            }

            $view->with([
                'alertCount' => $user ? Announcement::visibleTo($user)->where('published_at', '>=', now()->subDays(7))->count() : 0,
                'activeTimer' => $activeTimer,
                // Admin / manager: logout requests waiting for verification (blinking button)
                'logoutPending' => $user && in_array($user->role->portal(), ['admin', 'manager'], true)
                    ? app(\App\Services\LogoutReviewService::class)->pendingCount($user) : 0,
            ]);
        });

        // Employee status bar: date, login, working / idle / break time, leaves, online status.
        View::composer('partials.employee-status-bar', function ($view) {
            $user = auth()->user();
            $session = app(AttendanceService::class)->todaySession($user);
            $balances = $user->leaveBalances()->where('year', now()->year)->get();

            $view->with([
                'sbUser' => $user,
                'sbSession' => $session,
                'sbWork' => $session ? app(WorkScoreService::class)->forSession($user, $session) : null,
                'sbLeavesUsed' => $balances->sum('used'),
                'sbLeavesLeft' => $balances->sum(fn ($b) => $b->available()),
                'sbBreakAllowed' => $user->currentShift()->break_allowed_minutes * 60,
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

            // Employee: today's logout request (under review banner / decision popup)
            $logoutSession = null;
            if ($user && $user->role->portal() === 'employee') {
                $s = app(AttendanceService::class)->todaySession($user);
                if ($s && $s->checkout_type === 'exception' && $s->review_status
                    && ($s->logoutPending() || ! $s->decision_seen_at)) {
                    $logoutSession = $s->loadMissing('reviewer');
                }
            }

            $view->with(['punchGate' => $gate, 'logoutSession' => $logoutSession]);
        });
    }
}
