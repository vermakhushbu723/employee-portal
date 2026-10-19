<?php

namespace Database\Seeders;

use App\Enums\AttendanceStatus;
use App\Enums\LeaveStatus;
use App\Enums\LeaveType;
use App\Enums\Role;
use App\Models\User;
use Carbon\CarbonPeriod;
use Illuminate\Database\Seeder;

/**
 * About two months of attendance history plus today's live sessions:
 * EMP001 checked in at 10:00, EMP002 at 10:12, EMP003 not yet in.
 * Days with leave (approved or rejected) are skipped.
 */
class AttendanceSeeder extends Seeder
{
    public function run(): void
    {
        $employees = User::where('role', Role::Employee)->with('leaveRequests')->orderBy('employee_code')->get();

        foreach ($employees as $index => $user) {
            $skip = $this->leaveDates($user);
            $start = now()->subMonthsNoOverflow(2)->startOfMonth();

            foreach (CarbonPeriod::create($start, today()->subDay()) as $date) {
                $n = $date->day + $index * 3;

                if ($date->isSunday() || isset($skip[$date->toDateString()]) || $n % 17 === 0) {
                    continue; // weekly off, leave, or absent
                }

                $halfDay = $n % 13 === 0;
                $late = $n % 7 === 3;
                $checkIn = $date->copy()->setTime($late ? 10 : 9, $late ? 20 + $n % 10 : 55 + $n % 5);
                $workedMinutes = $halfDay ? 255 : 480 + $n % 12;
                $breakMinutes = 30;

                $session = $user->attendanceSessions()->create([
                    'work_date' => $date->toDateString(),
                    'check_in_at' => $checkIn,
                    'check_out_at' => $checkIn->copy()->addMinutes($workedMinutes + $breakMinutes),
                    'status' => $halfDay ? AttendanceStatus::HalfDay : ($late ? AttendanceStatus::Late : AttendanceStatus::Present),
                    'is_late' => $late,
                    'worked_minutes' => $workedMinutes,
                    'break_minutes' => $breakMinutes,
                    'checkout_type' => 'normal',
                ]);

                $breakStart = $date->copy()->setTime(14, 0);
                $session->breaks()->create([
                    'user_id' => $user->id,
                    'started_at' => $breakStart,
                    'ended_at' => $breakStart->copy()->addMinutes($breakMinutes),
                    'duration_minutes' => $breakMinutes,
                ]);
            }

            $this->today($user, $index);
        }
    }

    private function today(User $user, int $index): void
    {
        $checkIn = match ($index) {
            0 => today()->setTime(10, 0),
            1 => today()->setTime(10, 12),
            default => null,
        };

        if (! $checkIn || now()->lt($checkIn) || today()->isSunday()) {
            return;
        }

        $session = $user->attendanceSessions()->create([
            'work_date' => today()->toDateString(),
            'check_in_at' => $checkIn,
            'status' => AttendanceStatus::Present,
            'is_late' => false,
        ]);

        $breakStart = today()->setTime(12, 15);
        if (now()->gte($breakStart->copy()->addMinutes(30))) {
            $session->breaks()->create([
                'user_id' => $user->id,
                'started_at' => $breakStart,
                'ended_at' => $breakStart->copy()->addMinutes(30),
                'duration_minutes' => 30,
            ]);
        }
    }

    /** Dates covered by approved (non-WFH) or rejected leave. */
    private function leaveDates(User $user): array
    {
        $dates = [];

        foreach ($user->leaveRequests as $leave) {
            $counts = ($leave->status === LeaveStatus::Approved && $leave->leave_type !== LeaveType::WorkFromHome)
                || $leave->status === LeaveStatus::Rejected;

            if ($counts) {
                foreach (CarbonPeriod::create($leave->from_date, $leave->to_date) as $d) {
                    $dates[$d->toDateString()] = true;
                }
            }
        }

        return $dates;
    }
}
