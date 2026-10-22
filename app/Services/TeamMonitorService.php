<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Collection;

/** Live status row (timer + state) for every member of a manager's team. */
class TeamMonitorService
{
    public function __construct(private AttendanceService $attendance) {}

    public function rows(User $manager): Collection
    {
        return $manager->teamMembers()
            ->with(['employee.shift', 'attendanceSessions' => fn ($q) => $q->whereDate('work_date', today())->with('breaks')])
            ->orderBy('name')
            ->get()
            ->map(function (User $member) {
                $timer = $this->attendance->timer($member, $member->attendanceSessions->first());
                $timer['active'] = (int) $member->attendanceSessions->first()?->active_seconds;

                $state = match (true) {
                    ! $timer['checked_in'] => 'not_checked_in',
                    $timer['checked_out'] => 'checked_out',
                    $timer['on_break'] => 'on_break',
                    default => 'working',
                };

                return ['user' => $member, 'timer' => $timer, 'state' => $state];
            });
    }

    /** [label, tone] for a state. */
    public static function badge(string $state): array
    {
        return match ($state) {
            'working' => ['Working', 'success'],
            'on_break' => ['On Break', 'warning'],
            'checked_out' => ['Checked Out', 'primary'],
            default => ['Not Checked In', 'secondary'],
        };
    }
}
