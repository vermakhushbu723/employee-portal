<?php

namespace App\Http\Controllers\Manager;

use App\Enums\LeaveStatus;
use App\Http\Controllers\Controller;
use App\Models\AttendanceSession;
use App\Models\LeaveRequest;
use App\Models\TaskAssignment;
use App\Services\TeamMonitorService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request, TeamMonitorService $monitor)
    {
        $team = $monitor->rows($request->user());
        $teamIds = $team->pluck('user.id');

        $assignments = TaskAssignment::with('task')->whereIn('user_id', $teamIds)->whereDate('assigned_date', today())->get();
        $target = $assignments->sum(fn ($a) => $a->task->target_quantity);
        $completed = $assignments->sum(fn ($a) => min($a->completed_quantity, $a->task->target_quantity));

        return view('manager.dashboard', [
            'team' => $team,
            'stats' => [
                'members' => $team->count(),
                'working' => $team->where('state', 'working')->count(),
                'on_break' => $team->where('state', 'on_break')->count(),
                'not_in' => $team->where('state', 'not_checked_in')->count(),
            ],
            'work' => ['target' => $target, 'completed' => $completed],
            'pendingApprovals' => LeaveRequest::whereIn('user_id', $teamIds)->where('status', LeaveStatus::Pending)->count()
                + AttendanceSession::whereIn('user_id', $teamIds)->where('review_status', 'pending')->count(),
        ]);
    }
}
