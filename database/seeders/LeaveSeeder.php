<?php

namespace Database\Seeders;

use App\Enums\LeaveStatus;
use App\Enums\LeaveType;
use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class LeaveSeeder extends Seeder
{
    public function run(): void
    {
        $manager = User::where('employee_code', 'MGR001')->first();
        $year = now()->year;

        foreach (User::where('role', Role::Employee)->get() as $user) {
            foreach ([['casual', 4, 2], ['sick', 6, 3], ['earned', 2, 0]] as [$type, $total, $used]) {
                $user->leaveBalances()->create(['year' => $year, 'leave_type' => $type, 'total' => $total, 'used' => $used]);
            }
        }

        $riya = User::where('employee_code', 'EMP001')->first();

        $sickFrom = $this->weekday(now()->subMonthNoOverflow()->startOfMonth()->addDays(19));
        $rejected = $this->weekday(now()->subDays(10));

        $leaves = [
            [LeaveType::Sick, $sickFrom, $this->weekday($sickFrom->copy()->addDays(2)), 'Fever and health issue', LeaveStatus::Approved, null],
            [LeaveType::Personal, $this->weekday(now()->subDays(6)), null, 'Family function', LeaveStatus::Approved, null],
            [LeaveType::Casual, $rejected, null, 'Personal work', LeaveStatus::Rejected, 'Workload / critical period'],
            [LeaveType::Emergency, $this->weekday(now()->subMonthsNoOverflow(2)->addDays(3)), null, 'Family emergency', LeaveStatus::Approved, null],
            [LeaveType::WorkFromHome, $this->weekday(now()->subMonthsNoOverflow(3)), null, 'Home connectivity issue', LeaveStatus::Approved, null],
            [LeaveType::Casual, $this->weekday(now()->subMonthsNoOverflow(4)), null, 'Personal reason', LeaveStatus::Rejected, 'Insufficient notice'],
            [LeaveType::Casual, $this->weekday(now()->addDays(4)), null, 'Sister\'s wedding', LeaveStatus::Pending, null],
        ];

        foreach ($leaves as [$type, $from, $to, $reason, $status, $rejection]) {
            $to ??= $from->copy();
            $days = collect(\Carbon\CarbonPeriod::create($from, $to))->reject(fn ($d) => $d->isSunday())->count();

            $riya->leaveRequests()->create([
                'leave_type' => $type,
                'from_date' => $from->toDateString(),
                'to_date' => $to->toDateString(),
                'days' => $days,
                'reason' => $reason,
                'status' => $status,
                'rejection_reason' => $rejection,
                'actioned_by' => $status === LeaveStatus::Pending ? null : $manager->id,
                'actioned_at' => $status === LeaveStatus::Pending ? null : $from->copy()->subDays(2),
            ]);
        }

        // Approved deductions (payroll records)
        $deductions = [
            [$rejected, 'Unpaid Leave (1 Day)', $rejected->format('d M Y').' (Rejected leave, absent)', 500],
            [now()->startOfYear()->addMonths(5)->addDays(9), 'Extra Leave (1 Day)', 'No balance leave', 500],
            [now()->startOfYear()->addMonth()->addDays(11), 'Unpaid Leave (2 Days)', 'Exceeded sick leave limit', 1000],
        ];

        foreach ($deductions as [$date, $title, $description, $amount]) {
            if ($date->isFuture()) {
                continue;
            }

            $riya->payrollRecords()->create([
                'record_date' => $date->toDateString(),
                'type' => 'deduction',
                'title' => $title,
                'description' => $description,
                'amount' => $amount,
            ]);
        }
    }

    /** Move a date back to Saturday if it lands on a Sunday (weekly off). */
    private function weekday(Carbon $date): Carbon
    {
        return $date->isSunday() ? $date->copy()->subDay()->startOfDay() : $date->copy()->startOfDay();
    }
}
