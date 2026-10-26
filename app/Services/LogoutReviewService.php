<?php

namespace App\Services;

use App\Enums\AttendanceStatus;
use App\Enums\Role;
use App\Models\Announcement;
use App\Models\AttendanceSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Logout requests (exception checkouts) stay under review until management verifies them.
 *   Admin:   1-step verification → approved.
 *   Manager: step 1 verify (checks + note) → step 2 confirm with own password → approved.
 *   Reject (either): the day is reopened and the employee continues working.
 * The employee portal then shows "Your Logout Request Accepted By Management" (or the rejection).
 */
class LogoutReviewService
{
    public function __construct(private AuditLogger $audit) {}

    /** Requests this user may review. */
    public function visible(User $user): Builder
    {
        $q = AttendanceSession::query()->where('checkout_type', 'exception')->whereNotNull('review_status');

        return $user->role->portal() === 'admin'
            ? $q
            : $q->whereIn('user_id', $user->teamMembers()->pluck('id'));
    }

    /** Count for the blinking top-bar button. */
    public function pendingCount(User $user): int
    {
        return $this->visible($user)->whereIn('review_status', ['pending', 'verified'])->count();
    }

    public function isAdmin(User $user): bool
    {
        return in_array($user->role, [Role::SuperAdmin, Role::Hr], true);
    }

    /** Admin: one step. */
    public function approveByAdmin(AttendanceSession $s, User $admin, ?string $note): void
    {
        $this->guard($s, $admin);
        $this->approve($s, $admin, $note, 'admin (1-step)');
    }

    /** Manager step 1: details checked. */
    public function verifyByManager(AttendanceSession $s, User $manager, string $note): void
    {
        $this->guard($s, $manager);
        if ($s->review_status !== 'pending') {
            throw ValidationException::withMessages(['review' => 'Step 1 is already done — confirm step 2.']);
        }
        $s->update(['review_status' => 'verified', 'logout_verified_by' => $manager->id, 'logout_verified_at' => now(), 'logout_note' => $note]);
        $this->audit->log('logout.verify', "Step 1 verified logout request of {$s->user->employee_code}", ['session_id' => $s->id], $manager);
    }

    /** Manager step 2: confirm with password. */
    public function confirmByManager(AttendanceSession $s, User $manager, string $password): void
    {
        $this->guard($s, $manager);
        if ($s->review_status !== 'verified' || $s->logout_verified_by !== $manager->id) {
            throw ValidationException::withMessages(['review' => 'Complete step 1 (verify the request) first.']);
        }
        if (! Hash::check($password, $manager->password)) {
            throw ValidationException::withMessages(['password' => 'Password is not correct.']);
        }
        $this->approve($s, $manager, $s->logout_note, 'manager (2-step)');
    }

    /** Reject: reopen the day so the employee continues working. */
    public function reject(AttendanceSession $s, User $by, string $note): void
    {
        $this->guard($s, $by);
        $s->update([
            'review_status' => 'rejected',
            'reviewed_by' => $by->id,
            'reviewed_at' => now(),
            'logout_note' => $note,
            'decision_seen_at' => null,
            // Back to work: the day is open again and scored live (only for today)
            ...($s->work_date->isToday() ? [
                'check_out_at' => null,
                'grade' => null,
                'work_score' => null,
                'status' => $s->is_late ? AttendanceStatus::Late : AttendanceStatus::Present,
                'last_heartbeat_at' => now(),
            ] : []),
        ]);
        Announcement::create([
            'user_id' => $s->user_id, 'created_by' => $by->id, 'type' => 'important',
            'title' => 'Logout Request Not Accepted',
            'body' => "Reason: {$note}\nPlease continue your work and punch out when the day is complete.",
        ]);
        $this->audit->log('logout.reject', "Rejected logout request of {$s->user->employee_code}: {$note}", ['session_id' => $s->id], $by);
    }

    private function approve(AttendanceSession $s, User $by, ?string $note, string $how): void
    {
        $s->update(['review_status' => 'approved', 'reviewed_by' => $by->id, 'reviewed_at' => now(), 'logout_note' => $note, 'decision_seen_at' => null]);
        Announcement::create([
            'user_id' => $s->user_id, 'created_by' => $by->id, 'type' => 'important',
            'title' => 'Your Logout Request Accepted By Management',
            'body' => "Approved by {$by->name}".($note ? " — {$note}" : '').'. You can sign out now.',
        ]);
        $this->audit->log('logout.approve', "Approved logout request of {$s->user->employee_code} by {$how}", ['session_id' => $s->id], $by);
    }

    private function guard(AttendanceSession $s, User $by): void
    {
        if (! $this->visible($by)->whereKey($s->id)->exists()) {
            abort(403);
        }
        if (! $s->logoutPending()) {
            throw ValidationException::withMessages(['review' => 'This logout request was already '.$s->review_status.'.']);
        }
    }
}
