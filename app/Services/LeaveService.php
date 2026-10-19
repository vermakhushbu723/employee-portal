<?php

namespace App\Services;

use App\Enums\LeaveStatus;
use App\Enums\LeaveType;
use App\Models\Announcement;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\PayrollRecord;
use App\Models\User;
use Carbon\CarbonPeriod;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Apply / approve / reject leave. On approval the balance is charged;
 * when the balance is not enough the leave becomes unpaid and a
 * deduction is written to payroll_records.
 */
class LeaveService
{
    public function __construct(private AuditLogger $audit) {}

    public function apply(User $user, array $data): LeaveRequest
    {
        $from = Carbon::parse($data['from_date']);
        $to = Carbon::parse($data['to_date']);
        $weeklyOff = config('portal.weekly_off', [0]);

        $days = collect(CarbonPeriod::create($from, $to))
            ->reject(fn ($d) => in_array($d->dayOfWeek, $weeklyOff, true))
            ->count();

        if ($days === 0) {
            throw ValidationException::withMessages(['from_date' => 'The selected dates fall on weekly offs only.']);
        }

        $overlaps = $user->leaveRequests()
            ->whereIn('status', [LeaveStatus::Pending->value, LeaveStatus::Approved->value])
            ->whereDate('from_date', '<=', $to)
            ->whereDate('to_date', '>=', $from)
            ->exists();

        if ($overlaps) {
            throw ValidationException::withMessages(['from_date' => 'You already have a leave request for these dates.']);
        }

        $leave = $user->leaveRequests()->create([
            'leave_type' => $data['leave_type'],
            'from_date' => $from->toDateString(),
            'to_date' => $to->toDateString(),
            'days' => $days,
            'reason' => $data['reason'],
            'status' => LeaveStatus::Pending,
        ]);

        $this->audit->log('leave.apply', "Applied {$leave->leave_type->label()} for {$days} day(s)", ['leave_id' => $leave->id], $user);

        return $leave;
    }

    public function approve(LeaveRequest $leave, User $approver): void
    {
        $this->ensurePending($leave);

        DB::transaction(function () use ($leave, $approver) {
            $paid = $leave->leave_type === LeaveType::WorkFromHome;

            if ($type = $leave->leave_type->balanceType()) {
                $balance = LeaveBalance::firstOrCreate(
                    ['user_id' => $leave->user_id, 'year' => $leave->from_date->year, 'leave_type' => $type->value],
                    ['total' => 0, 'used' => 0],
                );

                if ($balance->available() >= $leave->days) {
                    $balance->increment('used', $leave->days);
                    $paid = true;
                }
            }

            $leave->update([
                'status' => LeaveStatus::Approved,
                'is_paid' => $paid,
                'actioned_by' => $approver->id,
                'actioned_at' => now(),
            ]);

            if (! $paid) {
                PayrollRecord::create([
                    'user_id' => $leave->user_id,
                    'leave_request_id' => $leave->id,
                    'record_date' => $leave->from_date->toDateString(),
                    'type' => 'deduction',
                    'title' => "Unpaid Leave ({$this->dayLabel($leave->days)})",
                    'description' => 'No balance available for '.$leave->leave_type->label(),
                    'amount' => $leave->days * config('portal.leave_deduction_per_day'),
                ]);
            }

            $this->notify($leave, $approver, 'Leave Approved', $leave->leave_type->label().' for '.$this->dayLabel($leave->days).' approved'.($paid ? '.' : ' as unpaid leave.'));
            $this->audit->log('leave.approve', "Approved leave #{$leave->id}", ['leave_id' => $leave->id], $approver);
        });
    }

    public function reject(LeaveRequest $leave, User $approver, string $reason): void
    {
        $this->ensurePending($leave);

        $leave->update([
            'status' => LeaveStatus::Rejected,
            'rejection_reason' => $reason,
            'actioned_by' => $approver->id,
            'actioned_at' => now(),
        ]);

        $this->notify($leave, $approver, 'Leave Rejected', $leave->leave_type->label()." rejected: {$reason}");
        $this->audit->log('leave.reject', "Rejected leave #{$leave->id}", ['leave_id' => $leave->id], $approver);
    }

    private function ensurePending(LeaveRequest $leave): void
    {
        if ($leave->status !== LeaveStatus::Pending) {
            throw ValidationException::withMessages(['action' => 'This leave request has already been processed.']);
        }
    }

    private function notify(LeaveRequest $leave, User $approver, string $title, string $body): void
    {
        Announcement::create([
            'user_id' => $leave->user_id,
            'created_by' => $approver->id,
            'type' => $leave->status === LeaveStatus::Rejected ? 'alert' : 'general',
            'title' => $title,
            'body' => $body,
        ]);
    }

    private function dayLabel(float $days): string
    {
        $n = rtrim(rtrim(number_format($days, 1), '0'), '.');

        return $n.' '.($days == 1 ? 'Day' : 'Days');
    }
}
