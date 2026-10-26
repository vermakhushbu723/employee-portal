<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\LocationLog;
use App\Models\User;
use App\Services\LiveStatusService;
use Illuminate\Http\Request;

/**
 * Live Tracking: latest location + battery of every employee on a map, and the
 * selected employee's trail for today. Admin sees everyone, a manager their own team.
 */
class TrackingController extends Controller
{
    public function __invoke(Request $request, LiveStatusService $live)
    {
        $viewer = $request->user();
        $users = ($viewer->role->portal() === 'admin'
            ? User::whereIn('role', [Role::Employee->value, Role::TeamLeader->value])
            : $viewer->teamMembers())
            ->where('status', 'active')->with('employee.department')->orderBy('name')->get();

        $rows = $live->rows($users);
        $selected = $users->firstWhere('id', $request->integer('user'));

        $trail = $selected
            ? LocationLog::where('user_id', $selected->id)->whereDate('recorded_at', today())->orderBy('recorded_at')
                ->get(['latitude', 'longitude', 'battery_level', 'recorded_at'])
            : collect();

        $points = $rows->filter(fn ($r) => $r['user']->latitude && $r['user']->location_at?->isToday())
            ->map(fn ($r) => [
                'id' => $r['user']->id,
                'name' => $r['user']->name,
                'code' => $r['user']->employee_code,
                'lat' => $r['user']->latitude,
                'lng' => $r['user']->longitude,
                'state' => $r['label'],
                'tone' => $r['tone'],
                'battery' => $r['user']->battery_level,
                'at' => $r['user']->location_at->format('h:i A'),
            ])->values();

        return view('tracking.index', [
            'rows' => $rows,
            'points' => $points,
            'selected' => $selected,
            'trail' => $trail->map(fn ($p) => [$p->latitude, $p->longitude, $p->recorded_at->format('h:i A'), $p->battery_level])->values(),
            'portal' => $viewer->role->portal(),
        ]);
    }
}
