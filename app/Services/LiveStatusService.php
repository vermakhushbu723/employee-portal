<?php

namespace App\Services;

use App\Models\AttendanceSession;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\LocationLog;
use App\Models\Meeting;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Live status of employees for the admin / manager dashboards and Live Tracking:
 * Working · In Meeting · Idle · On Break · Offline (+ calls, interested, meetings,
 * progress, battery, location and last activity).
 */
class LiveStatusService
{
    /** No heartbeat for this long = portal closed / device off. */
    public const OFFLINE_AFTER = 180;

    /** Portal open but no mouse / keyboard / touch for this long = idle. */
    public const IDLE_AFTER = 90;

    /** One location point per user every few minutes. */
    private const TRAIL_EVERY = 180;

    public const STATES = [
        'working' => ['Working', 'success'],
        'meeting' => ['In Meeting', 'info'],
        'idle' => ['Idle', 'warning'],
        'break' => ['On Break', 'purple'],
        'offline' => ['Offline', 'danger'],
        'out' => ['Punched Out', 'secondary'],
    ];

    public function __construct(private WorkScoreService $score) {}

    /** Save battery / location / activity sent with a heartbeat. */
    public function recordDevice(User $user, array $device, bool $active): void
    {
        $now = now();
        $fill = ['device_at' => $now];
        if ($active) {
            $fill['last_active_at'] = $now;
        }
        if (isset($device['battery'])) {
            $fill['battery_level'] = (int) $device['battery'];
            $fill['battery_charging'] = (bool) ($device['charging'] ?? false);
        }
        if (! empty($device['device'])) {
            $fill['device_type'] = $device['device'];
        }
        if (! empty($device['geo'])) {
            $fill['location_status'] = $device['geo'];
        }
        // Network range (type, speed, delay of the heartbeat)
        $fill['network_type'] = $device['net'] ?? null;
        $fill['network_mbps'] = isset($device['mbps']) ? round((float) $device['mbps'], 2) : null;
        if (isset($device['rtt'])) {
            $fill['network_rtt'] = (int) $device['rtt'];
        }
        if (isset($device['lat'], $device['lng'])) {
            $fill += [
                'latitude' => round((float) $device['lat'], 7),
                'longitude' => round((float) $device['lng'], 7),
                'location_accuracy' => isset($device['accuracy']) ? (int) round($device['accuracy']) : null,
                'location_at' => $now,
                'location_status' => 'ok',
            ];

            $last = LocationLog::where('user_id', $user->id)->latest('recorded_at')->value('recorded_at');
            if (! $last || $now->diffInSeconds($last, true) >= self::TRAIL_EVERY) {
                LocationLog::create([
                    'user_id' => $user->id,
                    'latitude' => $fill['latitude'],
                    'longitude' => $fill['longitude'],
                    'accuracy' => $fill['location_accuracy'],
                    'battery_level' => $fill['battery_level'] ?? $user->battery_level,
                    'recorded_at' => $now,
                ]);
            }
        }

        $user->forceFill($fill)->saveQuietly();
    }

    /** Network range for the live table: [bars 1–4, tone, label, details] or null when not reported today. */
    public static function network(User $user): ?array
    {
        if (! $user->device_at?->isToday() || ($user->network_rtt === null && ! $user->network_type)) {
            return null;
        }
        $rtt = $user->network_rtt;
        $type = $user->network_type;
        $bars = match (true) {
            in_array($type, ['slow-2g', '2g'], true) => 1,
            $rtt === null => $type === '3g' ? 2 : 3,
            $rtt < 400 => $type === '3g' ? 3 : 4,
            $rtt < 900 => 3,
            $rtt < 2000 => 2,
            default => 1,
        };
        $label = strtoupper(str_replace(['wifi', 'ethernet', 'cellular'], ['Wi-Fi', 'LAN', 'Mobile'], (string) ($type ?: 'Net')));
        $details = trim(($user->network_mbps ? rtrim(rtrim(number_format((float) $user->network_mbps, 1), '0'), '.').' Mbps · ' : '').($rtt !== null ? $rtt.' ms' : ''), ' ·');

        return [$bars, [1 => 'danger', 2 => 'warning', 3 => 'success', 4 => 'success'][$bars], $label === 'WI-FI' ? 'Wi-Fi' : $label, $details];
    }

    /** One live row per user (today). */
    public function rows(Collection $users): Collection
    {
        app(AttendanceService::class)->closeDueSessions();   // auto punch-out of shifts that are over
        $ids = $users->pluck('id');
        $sessions = AttendanceSession::with('breaks')->whereIn('user_id', $ids)->whereDate('work_date', today())->get()->keyBy('user_id');

        $calls = LeadActivity::whereIn('user_id', $ids)->whereDate('created_at', today())
            ->whereIn('type', ['status_change', 'follow_up'])
            ->selectRaw('user_id, count(distinct lead_id) as n')->groupBy('user_id')->pluck('n', 'user_id');
        $pendingNew = Lead::whereIn('assigned_to', $ids)->where('status', 'new')
            ->selectRaw('assigned_to, count(*) as n')->groupBy('assigned_to')->pluck('n', 'assigned_to');

        $meetings = Meeting::whereIn('user_id', $ids)->whereDate('scheduled_at', today())
            ->whereNotIn('status', ['declined', 'cancelled'])->get()->groupBy('user_id');
        $inRoom = array_flip(MeetingRoomService::inRoomUserIds($ids));

        return $users->map(function (User $user) use ($sessions, $calls, $pendingNew, $meetings, $inRoom) {
            $session = $sessions->get($user->id);
            $work = $session ? $this->score->forSession($user, $session) : null;
            $userMeetings = $meetings->get($user->id, collect());
            $state = $this->state($user, $session, $userMeetings, isset($inRoom[$user->id]));
            $called = (int) ($session?->calls_made ?? $calls[$user->id] ?? 0);

            return [
                'user' => $user,
                'session' => $session,
                'state' => $state,
                'label' => self::STATES[$state][0],
                'tone' => self::STATES[$state][1],
                'login' => $session?->check_in_at,
                'active' => (int) ($session?->active_seconds ?? 0),
                'calls' => $called,
                'calls_target' => $called + (int) ($pendingNew[$user->id] ?? 0),
                'interested' => $work['interested'] ?? 0,
                'interested_target' => $work['targets']['interested'] ?? $this->score->targets($user)['interested'],
                'created' => $work['created'] ?? 0,
                'meetings' => $userMeetings->count(),
                'progress' => $work['score'] ?? 0,
                'last_seen' => $this->lastSeen($user, $session),
            ];
        })->values();
    }

    /** Counts per state + totals for the KPI tiles. */
    public function summary(Collection $rows): array
    {
        $total = max(1, $rows->count());
        $count = fn ($state) => $rows->where('state', $state)->count();
        $states = collect(array_keys(self::STATES))->mapWithKeys(fn ($s) => [$s => $count($s)])->all();
        // Not logged in / punched out both count as offline on the tiles
        $states['offline_all'] = $states['offline'] + $states['out'];

        return [
            'total' => $rows->count(),
            'states' => $states,
            'pct' => array_map(fn ($n) => (int) round($n / $total * 100), $states),
            'calls' => $rows->sum('calls'),
            'calls_target' => $rows->sum('calls_target'),
            'interested' => $rows->sum('interested'),
            'interested_target' => $rows->sum('interested_target'),
            'created' => $rows->sum('created'),
            'meetings' => $rows->sum('meetings'),
        ];
    }

    private function state(User $user, ?AttendanceSession $session, Collection $meetings, bool $inRoom = false): string
    {
        if (! $session) {
            return 'offline';
        }
        if (! $session->isOpen()) {
            return 'out';
        }
        // In the built-in room now, or in an outside (Google Meet / Zoom) meeting started in the last 3 hours
        if ($inRoom || $meetings->contains(fn ($m) => $m->status === 'live' && ! $m->isPortal() && $m->started_at?->gt(now()->subHours(3)))) {
            return 'meeting';
        }
        if ($session->breaks->contains(fn ($b) => $b->ended_at === null)) {
            return 'break';
        }

        $beat = $session->last_heartbeat_at;
        if (! $beat || $beat->lt(now()->subSeconds(self::OFFLINE_AFTER))) {
            return 'offline';
        }

        return $user->last_active_at && $user->last_active_at->gte(now()->subSeconds(self::IDLE_AFTER)) ? 'working' : 'idle';
    }

    private function lastSeen(User $user, ?AttendanceSession $session)
    {
        return collect([$user->last_active_at, $session?->last_heartbeat_at, $session?->check_out_at])->filter()->max();
    }

    /** [icon, tone] for a battery level. */
    public static function batteryIcon(?int $level, ?bool $charging): array
    {
        if ($level === null) {
            return ['battery', 'secondary'];
        }
        if ($charging) {
            return ['battery-charging', 'success'];
        }

        return match (true) {
            $level >= 60 => ['battery-full', 'success'],
            $level >= 25 => ['battery-half', 'warning'],
            default => ['battery', 'danger'],
        };
    }
}
