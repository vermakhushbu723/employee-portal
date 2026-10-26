<?php

namespace App\Services;

use App\Enums\LeaveStatus;
use App\Enums\LeaveType;
use App\Models\User;
use Carbon\CarbonPeriod;
use Illuminate\Support\Carbon;

/**
 * Training → Joining → On Job, as set on the employee's profile.
 *
 * - A training day counts only when the employee actually worked that day
 *   (punched in and did the work given). Leave or absence during training
 *   does not count, so the training end and the joining date move forward
 *   by that many working days.
 * - No training dates but a joining date: the flow simply starts at joining.
 */
class TrainingService
{
    public function status(User $user): ?array
    {
        $emp = $user->employee;
        if (! $emp || (! $emp->training_from && ! $emp->joining_date)) {
            return null;
        }

        $today = today();
        $training = null;
        $missed = 0;
        $joinPlanned = $emp->joining_date?->copy();

        if ($emp->training_from && $emp->training_to) {
            $from = $emp->training_from->copy();
            $to = $emp->training_to->copy();
            $planned = collect(CarbonPeriod::create($from, $to))->filter(fn ($d) => $this->isWorkingDay($d))->count();

            // Working days of training that have passed without work (leave or absent)
            $leaveDays = $this->leaveDays($user, $from, $to);
            $workedDays = $user->attendanceSessions()->whereBetween('work_date', [$from->toDateString(), $to->toDateString()])
                ->pluck('work_date')->map(fn ($d) => $d->toDateString())->all();
            $done = 0;
            $absent = 0;
            if ($from->lt($today)) {
                foreach (CarbonPeriod::create($from, $to->copy()->min($today->copy()->subDay())) as $d) {
                    $key = $d->toDateString();
                    if (! $this->isWorkingDay($d) || isset($leaveDays[$key])) {
                        continue;
                    }
                    in_array($key, $workedDays, true) ? $done++ : $absent++;
                }
            }
            // Leave days (past or already approved for coming days) + absent days push the dates
            $missed = count($leaveDays) + $absent;

            $effectiveEnd = $this->addWorkingDays($to, $missed);
            $joinPlanned ??= $this->addWorkingDays($to, 1);

            $training = [
                'from' => $from,
                'to' => $to,
                'effective_to' => $effectiveEnd,
                'planned_days' => $planned,
                'done_days' => $done,
                'missed_days' => $missed,
                'state' => $today->lt($from) ? 'upcoming' : ($today->lte($effectiveEnd) ? 'in_progress' : 'completed'),
            ];
        }

        $joining = $missed > 0 && $joinPlanned ? $this->addWorkingDays($joinPlanned, $missed) : $joinPlanned;
        $joined = $joining && $today->gte($joining);

        return [
            'training' => $training,
            'joining' => $joining,
            'joining_planned' => $joinPlanned,
            'moved' => $joining && $joinPlanned && ! $joining->isSameDay($joinPlanned),
            'joined' => $joined,
            'on_job' => $joined,
        ];
    }

    /** date => true for approved leave days (not WFH) between two dates. */
    private function leaveDays(User $user, Carbon $from, Carbon $to): array
    {
        $days = [];
        $user->leaveRequests()
            ->where('status', LeaveStatus::Approved)
            ->where('leave_type', '!=', LeaveType::WorkFromHome->value)
            ->whereDate('from_date', '<=', $to)
            ->whereDate('to_date', '>=', $from)
            ->get()
            ->each(function ($leave) use (&$days, $from, $to) {
                foreach (CarbonPeriod::create($leave->from_date->max($from), $leave->to_date->min($to)) as $d) {
                    if ($this->isWorkingDay($d)) {
                        $days[$d->toDateString()] = true;
                    }
                }
            });

        return $days;
    }

    private function isWorkingDay(Carbon $date): bool
    {
        return ! in_array($date->dayOfWeek, config('portal.weekly_off', [0]), true);
    }

    private function addWorkingDays(Carbon $date, int $days): Carbon
    {
        $d = $date->copy();
        while ($days > 0) {
            $d->addDay();
            if ($this->isWorkingDay($d)) {
                $days--;
            }
        }

        return $d;
    }
}
