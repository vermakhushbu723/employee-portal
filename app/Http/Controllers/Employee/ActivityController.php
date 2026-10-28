<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\Meeting;
use App\Services\AttendanceService;
use App\Services\LiveStatusService;
use Illuminate\Http\Request;

/** Browser heartbeat: active time for the navbar timer + device status (battery, location) for the live dashboards. */
class ActivityController extends Controller
{
    public function __invoke(Request $request, AttendanceService $attendance, LiveStatusService $live)
    {
        $device = $request->validate([
            'battery' => ['nullable', 'integer', 'between:0,100'],
            'charging' => ['nullable', 'boolean'],
            'device' => ['nullable', 'in:mobile,desktop'],
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
            'accuracy' => ['nullable', 'numeric', 'min:0'],
            'geo' => ['nullable', 'in:ok,denied,unsupported'],
            'net' => ['nullable', 'string', 'max:20', 'regex:/^[a-z0-9-]+$/'],
            'mbps' => ['nullable', 'numeric', 'min:0', 'max:10000'],
            'rtt' => ['nullable', 'integer', 'min:0', 'max:600000'],
        ]);

        // Time in a live customer meeting counts as work even though the meeting window is in front (max 3 h)
        $inMeeting = \App\Services\MeetingRoomService::inRoomUserIds([$request->user()->id]) !== []
            || Meeting::where('user_id', $request->user()->id)->where('status', 'live')->where('platform', '!=', 'portal')
                ->where('started_at', '>=', now()->subHours(3))->exists();
        $active = $request->boolean('active') || $inMeeting;

        // A background tab beats less often (about once a minute), so allow a longer gap during a meeting
        $result = $attendance->heartbeat($request->user(), $active, $inMeeting ? 150 : 45);
        $live->recordDevice($request->user(), $device, $active && ($result['tracking'] ?? false));

        return response()->json($result);
    }
}
