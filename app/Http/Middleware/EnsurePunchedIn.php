<?php

namespace App\Http\Middleware;

use App\Services\AttendanceService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Employees must punch in before using the portal. Until then only the
 * dashboard (which shows the punch-in popup) and attendance actions work.
 * Employees on approved leave today are not blocked.
 */
class EnsurePunchedIn
{
    private const ALLOWED = ['employee.dashboard', 'employee.attendance.*', 'employee.work-timer.status', 'employee.activity.*'];

    public function __construct(private AttendanceService $attendance) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user->role->portal() !== 'employee' || $request->routeIs(...self::ALLOWED) || ! $this->attendance->mustPunchIn($user)) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Please punch in first.'], 423);
        }

        return redirect()->route('employee.dashboard')->withErrors(['action' => 'Please punch in first to start working.']);
    }
}
