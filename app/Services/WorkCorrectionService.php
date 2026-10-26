<?php

namespace App\Services;

use App\Enums\Role;
use App\Mail\WorkCorrectionSubmitted;
use App\Models\AttendanceSession;
use App\Models\LeadActivity;
use App\Models\User;
use App\Models\WorkCorrection;
use App\Support\MailPool;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Employee work data correction & review:
 *   admin / manager corrects calls, interested, leads created or working time with a reason
 *   → Submit for Review → e-mail to the reviewer (only after a successful submit)
 *   → reviewer approves (corrected numbers are applied and the day's score + attendance are recalculated) or rejects.
 */
class WorkCorrectionService
{
    public function __construct(
        private WorkScoreService $score,
        private DailyReportService $reports,
        private AuditLogger $audit,
    ) {}

    /** Current numbers for a day (what the correction starts from). */
    public function original(AttendanceSession $session): array
    {
        $session->loadMissing('user', 'breaks');
        $work = $this->score->forSession($session->user, $session);

        $calls = $session->calls_made ?? LeadActivity::where('user_id', $session->user_id)
            ->whereDate('created_at', $session->work_date)
            ->whereIn('type', ['status_change', 'follow_up'])
            ->distinct()->count('lead_id');

        return [
            'calls' => (int) $calls,
            'interested' => (int) $work['interested'],
            'leads_created' => (int) $work['created'],
            'active_seconds' => (int) $work['active'],
            'score' => (int) $work['score'],
            'grade' => $work['grade'],
        ];
    }

    /** Who may review a correction submitted by $by for $employee (admins + the employee's manager, never the submitter). */
    public function reviewers(User $by, User $employee): Collection
    {
        return User::where('status', 'active')
            ->where(fn ($q) => $q->whereIn('role', [Role::SuperAdmin->value, Role::Hr->value])
                ->orWhere('id', $employee->employee?->manager_id))
            ->where('id', '!=', $by->id)
            ->orderByRaw('case when role = ? then 0 else 1 end', [Role::SuperAdmin->value])
            ->orderBy('name')
            ->get();
    }

    public function canCorrect(User $by, User $employee): bool
    {
        return $by->role->portal() === 'admin' || $employee->employee?->manager_id === $by->id;
    }

    public function canReview(User $reviewer, WorkCorrection $c): bool
    {
        return $c->status === 'pending'
            && $c->submitted_by !== $reviewer->id
            && ($c->reviewer_id === $reviewer->id || $reviewer->role === Role::SuperAdmin);
    }

    /** Create the correction and e-mail the reviewer. Returns [correction, mailed?]. */
    public function submit(User $by, AttendanceSession $session, array $corrected, string $reason, string $details, int $reviewerId): array
    {
        $session->loadMissing('user.employee.department');
        $employee = $session->user;

        if (! $this->canCorrect($by, $employee)) {
            throw ValidationException::withMessages(['session' => 'You can correct only your own team members.']);
        }
        if ($session->isOpen() && $session->work_date->isToday()) {
            throw ValidationException::withMessages(['session' => "{$employee->name} is still punched in. Punch out first, then correct the day."]);
        }
        if (WorkCorrection::where('session_id', $session->id)->where('status', 'pending')->exists()) {
            throw ValidationException::withMessages(['session' => 'A correction for this day is already waiting for review.']);
        }
        if (! $this->reviewers($by, $employee)->contains('id', $reviewerId)) {
            throw ValidationException::withMessages(['reviewer_id' => 'Choose a reviewer from the list.']);
        }

        $original = $this->original($session);
        $changed = collect(array_keys(WorkCorrection::FIELDS))->filter(fn ($f) => (int) $corrected[$f] !== (int) $original[$f]);
        if ($changed->isEmpty()) {
            throw ValidationException::withMessages(['corrected' => 'Nothing was changed — enter the correct numbers.']);
        }

        $correction = WorkCorrection::create([
            'session_id' => $session->id,
            'user_id' => $employee->id,
            'submitted_by' => $by->id,
            'reviewer_id' => $reviewerId,
            'original' => $original,
            'corrected' => collect(WorkCorrection::FIELDS)->keys()->mapWithKeys(fn ($f) => [$f => (int) $corrected[$f]])->all(),
            'reason' => $reason,
            'details' => $details,
            'status' => 'pending',
        ]);

        $this->audit->log('correction.submit', "Submitted work correction for {$employee->employee_code} on {$session->work_date->format('d M Y')}: {$reason}", ['correction_id' => $correction->id]);

        // E-mail only after the correction was saved successfully
        $correction->load('user.employee.department', 'submitter', 'reviewer', 'session');
        $mailed = false;
        if ($correction->reviewer?->email && MailPool::ready()) {
            $mailed = MailPool::send([$correction->reviewer->email], new WorkCorrectionSubmitted($correction), "Correction #{$correction->id} review mail");
            if ($mailed) {
                $correction->forceFill(['emailed_at' => now()])->saveQuietly();
            }
        }

        return [$correction, $mailed];
    }

    /** Apply the corrected numbers to the day and recalculate score + attendance. */
    public function approve(WorkCorrection $c, User $reviewer, ?string $note): void
    {
        $this->guardReview($c, $reviewer);

        DB::transaction(function () use ($c, $reviewer, $note) {
            $session = $c->session()->with('user', 'breaks')->lockForUpdate()->first();
            $user = $session->user;
            $new = $c->corrected;

            $targets = $session->target_leads !== null
                ? ['leads' => (int) $session->target_leads, 'interested' => (int) $session->target_interested]
                : array_intersect_key($this->score->targets($user), ['leads' => 1, 'interested' => 1]);
            $required = max(1, $user->currentShift()->required_minutes * 60);
            $parts = [
                min(1, $new['active_seconds'] / $required),
                $targets['leads'] > 0 ? min(1, $new['leads_created'] / $targets['leads']) : 1,
                $targets['interested'] > 0 ? min(1, $new['interested'] / $targets['interested']) : 1,
            ];
            $score = (int) floor(array_sum($parts) / 3 * 100);
            $grade = WorkScoreService::gradeFor($score);

            $session->update([
                'calls_made' => $new['calls'],
                'interested_count' => $new['interested'],
                'leads_created' => $new['leads_created'],
                'active_seconds' => $new['active_seconds'],
                'target_leads' => $targets['leads'],
                'target_interested' => $targets['interested'],
                'work_score' => $score,
                'grade' => $grade,
                'worked_minutes' => intdiv($new['active_seconds'], 60),
                'status' => WorkScoreService::statusForGrade($grade, (bool) $session->is_late),
                'corrected_by' => $c->submitted_by,
                'correction_note' => $c->reason,
                'corrected_at' => now(),
            ]);

            $c->update(['status' => 'approved', 'reviewed_by' => $reviewer->id, 'reviewed_at' => now(), 'review_note' => $note]);
            $c->setAttribute('applied', ['score' => $score, 'grade' => $grade]);
        });

        $this->audit->log('correction.approve', "Approved work correction #{$c->id} for {$c->user->employee_code} on {$c->session->work_date->format('d M Y')}", ['correction_id' => $c->id]);
        $this->reports->send($c->session->fresh(['user.employee.manager', 'breaks']));
    }

    public function reject(WorkCorrection $c, User $reviewer, string $note): void
    {
        $this->guardReview($c, $reviewer);
        $c->update(['status' => 'rejected', 'reviewed_by' => $reviewer->id, 'reviewed_at' => now(), 'review_note' => $note]);
        $this->audit->log('correction.reject', "Rejected work correction #{$c->id} for {$c->user->employee_code}: {$note}", ['correction_id' => $c->id]);
    }

    private function guardReview(WorkCorrection $c, User $reviewer): void
    {
        if ($c->status !== 'pending') {
            throw ValidationException::withMessages(['review' => 'This correction was already '.$c->status.'.']);
        }
        if (! $this->canReview($reviewer, $c)) {
            throw ValidationException::withMessages(['review' => $c->submitted_by === $reviewer->id
                ? 'You submitted this correction, so another person must review it.'
                : 'Only the selected reviewer (or a super admin) can review this correction.']);
        }
    }
}
