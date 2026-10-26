<?php

namespace App\Http\Controllers\Admin;

use App\Enums\LeaveStatus;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\LateCheckinRequest;
use App\Models\LeaveRequest;
use App\Models\Meeting;
use App\Models\User;
use App\Models\WorkCorrection;
use App\Services\LiveStatusService;
use App\Support\Duration;
use Illuminate\Http\Request;

/** Admin dashboard: live status of every employee (Working / In Meeting / Idle / Offline), today's performance. */
class DashboardController extends Controller
{
    public function __invoke(Request $request, LiveStatusService $live)
    {
        $staff = User::whereIn('role', [Role::Employee->value, Role::TeamLeader->value])
            ->where('status', 'active')
            ->with('employee.department', 'employee.manager')
            ->orderBy('name')
            ->get();

        $rows = $live->rows($staff);

        if ($request->boolean('export')) {
            return $this->export($rows);
        }

        return view('admin.dashboard', [
            'rows' => $rows,
            'summary' => $live->summary($rows),
            'departments' => self::byDepartment($rows),
            'top' => $rows->filter(fn ($r) => $r['session'])->sortByDesc('progress')->take(5)->values(),
            'meetingsToday' => Meeting::with('user')->whereDate('scheduled_at', today())->whereNotIn('status', ['declined', 'cancelled'])->orderBy('scheduled_at')->limit(6)->get(),
            'pending' => [
                'leaves' => LeaveRequest::where('status', LeaveStatus::Pending)->count(),
                'late' => LateCheckinRequest::where('status', LeaveStatus::Pending)->count(),
                'corrections' => WorkCorrection::where('status', 'pending')->count(),
            ],
            'logs' => AuditLog::with('user')->latest()->limit(8)->get(),
        ]);
    }

    /** Calls done / target per department (also used by the manager dashboard). */
    public static function byDepartment($rows)
    {
        return $rows->groupBy(fn ($r) => $r['user']->employee?->department?->name ?? 'No department')
            ->map(fn ($g, $name) => [
                'name' => $name,
                'calls' => $g->sum('calls'),
                'target' => $g->sum('calls_target'),
                'working' => $g->whereIn('state', ['working', 'meeting'])->count(),
                'count' => $g->count(),
            ])
            ->sortByDesc('calls')->values();
    }

    private function export($rows)
    {
        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Employee', 'ID', 'Department', 'Status', 'Login', 'Working Time', 'Calls Done', 'Calls Target', 'Interested', 'Leads Created', 'Meetings', 'Progress %', 'Battery %', 'Location', 'Last Active'], ',', '"', '');
            foreach ($rows as $r) {
                $u = $r['user'];
                fputcsv($out, [
                    $u->name, $u->employee_code, $u->employee?->department?->name, $r['label'],
                    $r['login']?->format('h:i A'), Duration::hms($r['active']), $r['calls'], $r['calls_target'],
                    $r['interested'], $r['created'], $r['meetings'], $r['progress'], $u->battery_level,
                    $u->latitude ? $u->latitude.','.$u->longitude : '', $r['last_seen']?->format('d M h:i A'),
                ], ',', '"', '');
            }
            fclose($out);
        }, 'live-status-'.now()->format('Y-m-d-Hi').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
