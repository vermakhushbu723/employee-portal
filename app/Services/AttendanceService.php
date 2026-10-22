<?php

namespace App\Services;

use App\Enums\AttendanceStatus;
use App\Enums\LeaveStatus;
use App\Enums\Role;
use App\Enums\TaskStatus;
use App\Models\Announcement;
use App\Models\AttendanceSession;
use App\Models\LateCheckinRequest;
use App\Models\User;
use App\Support\Duration;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Check-in, live timer and checkout rules. Every calculation uses server
 * timestamps:  worked = (now or checkout) - check-in - breaks.
 */
class AttendanceService
{
    public function __construct(private AuditLogger $audit) {}

    public function todaySession(User $user): ?AttendanceSession
    {
        return $user->attendanceSessions()->with('breaks')->whereDate('work_date', today())->first();
    }

    /** True when the employee has not punched in today and is not on approved leave. */
    public function mustPunchIn(User $user): bool
    {
        if ($this->todaySession($user)) {
            return false;
        }

        return ! $user->leaveRequests()
            ->where('status', LeaveStatus::Approved)
            ->where('leave_type', '!=', \App\Enums\LeaveType::WorkFromHome->value)
            ->whereDate('from_date', '<=', today())
            ->whereDate('to_date', '>=', today())
            ->exists();
    }

    /** Count active (non-idle) time from a browser heartbeat. Idle / on-break heartbeats add nothing. */
    public function heartbeat(User $user, bool $active): array
    {
        $session = $this->todaySession($user);

        if (! $session || ! $session->isOpen()) {
            return ['tracking' => false, 'active_seconds' => (int) $session?->active_seconds];
        }

        $now = now();
        $onBreak = $session->activeBreak() !== null;

        if ($active && ! $onBreak && $session->last_heartbeat_at) {
            // Heartbeats come every ~20 s. A longer gap means the page was closed or the
            // laptop slept, so that gap is not counted at all.
            $gap = $now->getTimestamp() - $session->last_heartbeat_at->getTimestamp();
            if ($gap > 0 && $gap <= 45) {
                $session->active_seconds += $gap;
            }
        }

        $session->last_heartbeat_at = $now;
        $session->saveQuietly();

        return ['tracking' => ! $onBreak, 'active_seconds' => $session->active_seconds, 'on_break' => $onBreak];
    }

    public function lateRequestToday(User $user): ?LateCheckinRequest
    {
        return $user->lateCheckinRequests()->whereDate('work_date', today())->first();
    }

    /**
     * Where the employee stands for today's check-in:
     *   checked_in | not_open (before 09:55) | open (09:55–10:15)
     *   late_request_needed | late_pending | late_rejected | late_approved
     */
    public function checkInState(User $user, ?AttendanceSession $session = null): array
    {
        $shift = $user->currentShift();
        $opensAt = $shift->checkInOpensAt(today());
        $lateAfter = $shift->lateAfter(today());
        $request = null;
        $now = now();

        if ($session ?? $this->todaySession($user)) {
            $state = 'checked_in';
        } elseif ($now->lt($opensAt)) {
            $state = 'not_open';
        } elseif ($now->lte($lateAfter)) {
            $state = 'open';
        } else {
            $request = $this->lateRequestToday($user);
            $state = match ($request?->status) {
                null => 'late_request_needed',
                LeaveStatus::Pending => 'late_pending',
                LeaveStatus::Rejected => 'late_rejected',
                LeaveStatus::Approved => 'late_approved',
            };
        }

        return [
            'state' => $state,
            'opens_at' => $opensAt->format('h:i A'),
            'late_after' => $lateAfter->format('h:i A'),
            'request' => $request,
        ];
    }

    public function checkIn(User $user, ?string $ip = null): AttendanceSession
    {
        $check = $this->checkInState($user);

        $error = match ($check['state']) {
            'checked_in' => 'You have already checked in today.',
            'not_open' => "Check-in opens at {$check['opens_at']}.",
            'late_request_needed' => "You are late (check-in closed at {$check['late_after']}). Send a late check-in request to admin.",
            'late_pending' => 'Your late check-in request is waiting for admin approval.',
            'late_rejected' => 'Your late check-in request was rejected by admin.',
            default => null,
        };

        if ($error) {
            throw ValidationException::withMessages(['action' => $error]);
        }

        $isLate = $check['state'] === 'late_approved';
        $now = now();

        $session = DB::transaction(function () use ($user, $ip, $isLate, $now, $check) {
            $session = $user->attendanceSessions()->create([
                'work_date' => today()->toDateString(),
                'check_in_at' => $now,
                'status' => $isLate ? AttendanceStatus::Late : AttendanceStatus::Present,
                'is_late' => $isLate,
                'ip_address' => $ip,
            ]);

            $check['request']?->update(['attendance_session_id' => $session->id]);

            return $session;
        });

        $this->audit->log('attendance.check_in', 'Checked in at '.$now->format('h:i A').($isLate ? ' (late, approved)' : ''), ['session_id' => $session->id], $user);

        return $session;
    }

    /** After the on-time window an employee asks admin for permission to check in. */
    public function requestLateCheckIn(User $user, string $reason): LateCheckinRequest
    {
        $check = $this->checkInState($user);

        if ($check['state'] !== 'late_request_needed') {
            throw ValidationException::withMessages(['action' => match ($check['state']) {
                'open' => 'You are on time — just check in.',
                'not_open' => "Check-in opens at {$check['opens_at']}.",
                'checked_in' => 'You have already checked in today.',
                default => 'A late check-in request for today already exists.',
            }]);
        }

        return DB::transaction(function () use ($user, $reason) {
            $request = $user->lateCheckinRequests()->create([
                'work_date' => today()->toDateString(),
                'reason' => $reason,
                'status' => LeaveStatus::Pending,
            ]);

            // Let every admin / HR know.
            User::whereIn('role', [Role::SuperAdmin->value, Role::Hr->value])->where('status', 'active')->pluck('id')
                ->each(fn ($adminId) => Announcement::create([
                    'user_id' => $adminId,
                    'created_by' => $user->id,
                    'type' => 'alert',
                    'title' => 'Late Check-in Request',
                    'body' => "{$user->name} ({$user->employee_code}) requested late check-in at ".now()->format('h:i A').": {$reason}",
                ]));

            $this->audit->log('attendance.late_request', 'Requested late check-in', ['request_id' => $request->id], $user);

            return $request;
        });
    }

    public function decideLateRequest(LateCheckinRequest $request, User $admin, bool $approve, ?string $reason = null): void
    {
        if ($request->status !== LeaveStatus::Pending) {
            throw ValidationException::withMessages(['action' => 'This request has already been processed.']);
        }

        DB::transaction(function () use ($request, $admin, $approve, $reason) {
            $request->update([
                'status' => $approve ? LeaveStatus::Approved : LeaveStatus::Rejected,
                'rejection_reason' => $approve ? null : $reason,
                'actioned_by' => $admin->id,
                'actioned_at' => now(),
            ]);

            Announcement::create([
                'user_id' => $request->user_id,
                'created_by' => $admin->id,
                'type' => $approve ? 'general' : 'alert',
                'title' => $approve ? 'Late Check-in Approved' : 'Late Check-in Rejected',
                'body' => $approve ? 'Admin approved your late check-in. You can check in now.' : 'Your late check-in request was rejected'.($reason ? ": {$reason}" : '.'),
            ]);

            $this->audit->log($approve ? 'attendance.late_approve' : 'attendance.late_reject', ($approve ? 'Approved' : 'Rejected')." late check-in of {$request->user->employee_code}", ['request_id' => $request->id], $admin);
        });
    }

    /** Everything the timer screens need, in seconds. Safe to return as JSON. */
    public function timer(User $user, ?AttendanceSession $session = null): array
    {
        $session ??= $this->todaySession($user);
        $shift = $user->currentShift();
        $now = now();
        $shiftEnd = $shift->endsAt($session?->work_date ?? today());
        $required = $shift->required_minutes * 60;
        $breakAllowed = $shift->break_allowed_minutes * 60;

        $worked = 0;
        $breakUsed = 0;
        $activeBreak = null;

        if ($session) {
            $end = $session->check_out_at ?? $now;

            foreach ($session->breaks as $break) {
                $breakUsed += self::seconds($break->started_at, $break->ended_at ?? $end);
            }

            $activeBreak = $session->isOpen() ? $session->activeBreak() : null;
            $worked = max(0, self::seconds($session->check_in_at, $end) - $breakUsed);
        }

        return [
            'checked_in' => $session !== null,
            'checked_out' => $session?->check_out_at !== null,
            'on_break' => $activeBreak !== null,
            'check_in_at' => $session?->check_in_at->format('h:i A'),
            'check_out_at' => $session?->check_out_at?->format('h:i A'),
            'break_started_at' => $activeBreak?->started_at->format('h:i A'),
            'is_late' => (bool) $session?->is_late,
            'shift_label' => $shift->label(),
            'shift_end_at' => $shiftEnd->format('h:i A'),
            'shift_ends_in' => max(0, $shiftEnd->getTimestamp() - $now->getTimestamp()),
            'required' => $required,
            'worked' => $worked,
            'remaining' => max(0, $required - $worked),
            'progress' => Duration::percent($worked, $required),
            'break_allowed' => $breakAllowed,
            'break_used' => $breakUsed,
            'break_remaining' => max(0, $breakAllowed - $breakUsed),
        ];
    }

    /** Rules 4–6: normal checkout needs full hours, no open break and every task of today completed. */
    public function checkoutEligibility(User $user, ?AttendanceSession $session, ?array $timer = null): array
    {
        if (! $session) {
            return ['allowed' => false, 'reasons' => ['You have not checked in today.']];
        }

        if (! $session->isOpen()) {
            return ['allowed' => false, 'reasons' => ['You have already checked out today.']];
        }

        $timer ??= $this->timer($user, $session);
        $reasons = [];

        if ($timer['on_break']) {
            $reasons[] = 'End your current break before checking out.';
        }

        if ($timer['remaining'] > 0) {
            $reasons[] = 'Complete '.Duration::hm($timer['remaining']).' more of required work hours.';
        }

        if (($pending = $this->pendingTasks($user)) > 0) {
            $reasons[] = "{$pending} task(s) for today are not completed. Complete and submit every task to punch out.";
        }

        return ['allowed' => $reasons === [], 'reasons' => $reasons];
    }

    /** Today's tasks that are not yet completed (submitted or verified). Any of them blocks punch-out. */
    public function pendingTasks(User $user): int
    {
        return $user->taskAssignments()
            ->whereDate('assigned_date', today())
            ->whereNotIn('status', [TaskStatus::Submitted->value, TaskStatus::Verified->value])
            ->count();
    }

    /**
     * Normal checkout enforces the rules above. An exception checkout
     * (shift end / illness / emergency – Rule 7) is always allowed but
     * goes to the manager for review.
     */
    public function checkOut(User $user, bool $exception = false, ?string $reason = null): AttendanceSession
    {
        $session = $this->todaySession($user);

        if (! $exception) {
            $eligibility = $this->checkoutEligibility($user, $session);

            if (! $eligibility['allowed']) {
                throw ValidationException::withMessages(['action' => $eligibility['reasons']]);
            }
        } elseif (! $session || ! $session->isOpen()) {
            throw ValidationException::withMessages(['action' => 'There is no open attendance session to check out.']);
        }

        return DB::transaction(function () use ($user, $session, $exception, $reason) {
            $now = now();

            if ($active = $session->activeBreak()) {
                $active->update([
                    'ended_at' => $now,
                    'duration_minutes' => intdiv(self::seconds($active->started_at, $now), 60),
                ]);
            }

            $session->check_out_at = $now;
            $timer = $this->timer($user, $session);

            $session->fill([
                'status' => $timer['worked'] >= $timer['required'] ? $session->status : AttendanceStatus::HalfDay,
                'worked_minutes' => intdiv($timer['worked'], 60),
                'break_minutes' => intdiv($timer['break_used'], 60),
                'checkout_type' => $exception ? 'exception' : 'normal',
                'checkout_reason' => $reason,
                'review_status' => $exception ? 'pending' : null,
            ])->save();

            $this->audit->log(
                $exception ? 'attendance.exception_checkout' : 'attendance.check_out',
                'Checked out at '.$now->format('h:i A').' after '.Duration::hm($timer['worked']),
                ['session_id' => $session->id, 'reason' => $reason],
                $user,
            );

            return $session;
        });
    }

    private static function seconds(CarbonInterface $from, CarbonInterface $to): int
    {
        return max(0, $to->getTimestamp() - $from->getTimestamp());
    }
}
