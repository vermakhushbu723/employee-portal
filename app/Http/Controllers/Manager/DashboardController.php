<?php

namespace App\Http\Controllers\Manager;

use App\Enums\LeaveStatus;
use App\Http\Controllers\Controller;
use App\Models\AttendanceSession;
use App\Models\LeaveRequest;
use App\Models\Meeting;
use App\Models\WorkCorrection;
use App\Services\LiveStatusService;
use Illuminate\Http\Request;

/** Team manager dashboard: live status of the manager's own team. */
class DashboardController extends Controller
{
    public function __invoke(Request $request, LiveStatusService $live)
    {
        $manager = $request->user();
        $team = $manager->teamMembers()->where('status', 'active')->with('employee.department')->orderBy('name')->get();
        $rows = $live->rows($team);
        $teamIds = $team->pluck('id');

        $meetings = Meeting::with('user')->whereIn('user_id', $teamIds)
            ->where('scheduled_at', '>=', now()->subHour())->whereIn('status', ['pending', 'upcoming', 'live'])
            ->orderBy('scheduled_at')->limit(5)->get();

        return view('manager.dashboard', [
            'rows' => $rows,
            'summary' => $live->summary($rows),
            'meetings' => $meetings,
            'alerts' => $this->alerts($rows, $meetings),
            'pendingApprovals' => LeaveRequest::whereIn('user_id', $teamIds)->where('status', LeaveStatus::Pending)->count()
                + AttendanceSession::whereIn('user_id', $teamIds)->where('review_status', 'pending')->count(),
            'pendingCorrections' => WorkCorrection::where('status', 'pending')->where('reviewer_id', $manager->id)->count(),
        ]);
    }

    /** Live notifications: meetings starting soon, idle / offline team members, low battery. */
    private function alerts($rows, $meetings): array
    {
        $alerts = [];
        foreach ($meetings as $m) {
            $mins = (int) now()->diffInMinutes($m->scheduled_at, false);
            if ($m->status === 'upcoming' && $mins >= 0 && $mins <= 30) {
                $alerts[] = ['calendar-event', 'info', "Meeting in {$mins} min", "{$m->scheduled_at->format('h:i A')} · {$m->customer_name} ({$m->user->name})"];
            }
        }
        foreach ($rows as $r) {
            $name = $r['user']->name;
            if ($r['state'] === 'idle' && $r['last_seen'] && $r['last_seen']->lt(now()->subMinutes(10))) {
                $alerts[] = ['hourglass-split', 'warning', "{$name} is idle", 'No activity for '.$r['last_seen']->diffForHumans(null, true)];
            } elseif ($r['state'] === 'offline' && $r['session']) {
                $alerts[] = ['wifi-off', 'danger', "{$name} went offline", 'Punched in, but the portal is closed or the device is off'];
            }
            $u = $r['user'];
            if ($r['session']?->isOpen() && $u->battery_level !== null && $u->battery_level <= 15 && ! $u->battery_charging && $u->device_at?->gt(now()->subMinutes(10))) {
                $alerts[] = ['battery', 'danger', "{$name}: battery {$u->battery_level}%", 'Device may switch off soon'];
            }
        }

        return array_slice($alerts, 0, 8);
    }
}
