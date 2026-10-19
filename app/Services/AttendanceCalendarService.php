<?php

namespace App\Services;

use App\Enums\AttendanceStatus;
use App\Enums\LeaveStatus;
use App\Models\User;
use Carbon\CarbonPeriod;
use Illuminate\Support\Carbon;

/**
 * Builds a month of attendance for the calendar screen by combining
 * attendance sessions, approved leave and weekly offs. A past working
 * day with neither a session nor leave counts as absent.
 */
class AttendanceCalendarService
{
    public function build(User $user, Carbon $month): array
    {
        $start = $month->copy()->startOfMonth();
        $end = $month->copy()->endOfMonth();

        $sessions = $user->attendanceSessions()
            ->whereBetween('work_date', [$start->toDateString(), $end->toDateString()])
            ->get()
            ->keyBy(fn ($s) => $s->work_date->toDateString());

        $leaveByDate = [];
        $user->leaveRequests()
            ->where('status', LeaveStatus::Approved)
            ->whereDate('from_date', '<=', $end)
            ->whereDate('to_date', '>=', $start)
            ->get()
            ->filter(fn ($leave) => $leave->leave_type->countsAsAbsence())
            ->each(function ($leave) use (&$leaveByDate) {
                foreach (CarbonPeriod::create($leave->from_date, $leave->to_date) as $date) {
                    $leaveByDate[$date->toDateString()] = $leave;
                }
            });

        $joined = ($user->employee?->joining_date ?? $user->created_at)->copy()->startOfDay();
        $weeklyOff = config('portal.weekly_off', [0]);
        $counts = ['present' => 0, 'leave' => 0, 'half_day' => 0, 'absent' => 0, 'weekly_off' => 0];
        $days = [];

        foreach (CarbonPeriod::create($start, $end) as $date) {
            $key = $date->toDateString();
            $session = $sessions->get($key);
            $leave = $leaveByDate[$key] ?? null;

            $status = match (true) {
                $session !== null => $session->status,
                $leave !== null => AttendanceStatus::Leave,
                in_array($date->dayOfWeek, $weeklyOff, true) => AttendanceStatus::WeeklyOff,
                $date->lt(today()) && $date->gte($joined) => AttendanceStatus::Absent,
                default => null,
            };

            $bucket = match ($status) {
                AttendanceStatus::Present, AttendanceStatus::Late => 'present',
                null => null,
                default => $status->value,
            };

            if ($bucket !== null && isset($counts[$bucket])) {
                $counts[$bucket]++;
            }

            $days[] = [
                'date' => $date->copy(),
                'status' => $status,
                'session' => $session,
                'leave' => $leave,
            ];
        }

        return [
            'month' => $start,
            'leading_blanks' => $start->dayOfWeek,
            'days' => $days,
            'counts' => $counts,
            'worked_minutes' => (int) $sessions->sum('worked_minutes'),
        ];
    }
}
