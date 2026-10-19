<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Services\AttendanceService;
use App\Support\Duration;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request, AttendanceService $attendance)
    {
        $user = $request->user()->load('employee.shift');
        $session = $attendance->todaySession($user);
        $timer = $attendance->timer($user, $session);

        $assignments = $user->taskAssignments()->with('task')
            ->whereDate('assigned_date', today())
            ->get();

        $target = $assignments->sum(fn ($a) => $a->task->target_quantity);
        $completed = $assignments->sum(fn ($a) => min($a->completed_quantity, $a->task->target_quantity));

        return view('employee.dashboard', [
            'user' => $user,
            'timer' => $timer,
            'assignments' => $assignments,
            'work' => [
                'target' => $target,
                'completed' => $completed,
                'pending' => max(0, $target - $completed),
                'progress' => Duration::percent($completed, $target),
            ],
            'eligibility' => $attendance->checkoutEligibility($user, $session, $timer),
        ]);
    }
}
