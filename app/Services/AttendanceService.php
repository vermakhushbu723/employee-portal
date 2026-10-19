<?php

namespace App\Services;

use App\Enums\AttendanceStatus;
use App\Enums\TaskStatus;
use App\Models\AttendanceSession;
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

    public function checkIn(User $user, ?string $ip = null): AttendanceSession
    {
        if ($this->todaySession($user)) {
            throw ValidationException::withMessages(['action' => 'You have already checked in today.']);
        }

        $shift = $user->currentShift();
        $now = now();
        $isLate = $now->gt($shift->startsAt(today())->addMinutes($shift->grace_minutes));

        $session = $user->attendanceSessions()->create([
            'work_date' => today()->toDateString(),
            'check_in_at' => $now,
            'status' => $isLate ? AttendanceStatus::Late : AttendanceStatus::Present,
            'is_late' => $isLate,
            'ip_address' => $ip,
        ]);

        $this->audit->log('attendance.check_in', 'Checked in at '.$now->format('h:i A'), ['session_id' => $session->id], $user);

        return $session;
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

    /** Rules 4–6: normal checkout needs full hours, no open break and no pending mandatory work. */
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

        if (($pending = $this->pendingMandatoryTasks($user)) > 0) {
            $reasons[] = "{$pending} mandatory task(s) are still pending.";
        }

        return ['allowed' => $reasons === [], 'reasons' => $reasons];
    }

    public function pendingMandatoryTasks(User $user): int
    {
        return $user->taskAssignments()
            ->whereDate('assigned_date', today())
            ->whereHas('task', fn ($q) => $q->where('is_mandatory', true))
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
