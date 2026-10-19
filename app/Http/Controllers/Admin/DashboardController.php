<?php

namespace App\Http\Controllers\Admin;

use App\Enums\LeaveStatus;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\AttendanceSession;
use App\Models\AuditLog;
use App\Models\Lead;
use App\Models\LeaveRequest;
use App\Models\User;

class DashboardController extends Controller
{
    public function __invoke()
    {
        $staff = User::whereIn('role', [Role::Employee->value, Role::TeamLeader->value]);

        $today = AttendanceSession::whereDate('work_date', today());

        return view('admin.dashboard', [
            'stats' => [
                'employees' => (clone $staff)->where('status', 'active')->count(),
                'managers' => User::where('role', Role::Manager->value)->count(),
                'present' => (clone $today)->count(),
                'late' => (clone $today)->where('is_late', true)->count(),
                'on_leave' => LeaveRequest::where('status', LeaveStatus::Approved)
                    ->whereDate('from_date', '<=', today())->whereDate('to_date', '>=', today())->count(),
                'pending_leaves' => LeaveRequest::where('status', LeaveStatus::Pending)->count(),
                'pending_late' => \App\Models\LateCheckinRequest::where('status', LeaveStatus::Pending)->count(),
                'leads_today' => Lead::whereDate('created_at', today())->count(),
                'conversions_month' => Lead::where('converted_at', '>=', now()->startOfMonth())->count(),
            ],
            'logs' => AuditLog::with('user')->latest()->limit(12)->get(),
        ]);
    }
}
