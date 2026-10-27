<?php

namespace App\Services;

use App\Enums\AttendanceStatus;
use App\Enums\LeadStatus;
use App\Models\AttendanceSession;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\User;
use Carbon\CarbonInterface;

/**
 * Work-based attendance for one day.
 *
 *   time %       = active seconds / shift required seconds
 *   leads %      = leads created that day / daily lead target
 *   interested % = leads marked Interested or Converted that day / daily target
 *   score        = average of the three (each capped at 100%)
 *
 *   grade: full (>= 100) → Present · half (>= 50) → Half Day
 *          quarter (>= 25) → Quarter Day · none → Absent
 */
class WorkScoreService
{
    public function __construct(private WorkAssignmentService $assignments) {}

    /** Daily targets set by admin / manager (without task extras). */
    public function baseTargets(User $user): array
    {
        $defaults = config('portal.work_score.default_targets');
        $set = $user->targets()->where('period', 'daily')->pluck('target_value', 'metric');

        return [
            'leads' => (int) ($set['leads'] ?? $defaults['leads']),
            'interested' => (int) ($set['interested'] ?? $defaults['interested']),
        ];
    }

    /**
     * Today's targets = base daily targets + targets of open work tasks
     * (shared file's interested target, lead-creation task's lead target)
     * — the increase stays until that work is finished.
     */
    public function targets(User $user): array
    {
        $base = $this->baseTargets($user);
        $extra = $this->assignments->openTargets($user);

        return [
            'leads' => $base['leads'] + $extra['leads'],
            'interested' => $base['interested'] + $extra['interested'],
            'base' => $base,
            'extra' => $extra,
        ];
    }

    public function leadsCreated(User $user, CarbonInterface $date): int
    {
        return Lead::where('created_by', $user->id)->whereDate('created_at', $date)->count();
    }

    public function interestedCount(User $user, CarbonInterface $date): int
    {
        return LeadActivity::where('user_id', $user->id)
            ->whereDate('created_at', $date)
            ->whereIn('to_status', LeadStatus::positive())
            // A lead the manager / admin reviewed and moved away from Interested / Converted no longer counts
            ->whereNotIn('lead_id', Lead::whereNotNull('corrected_at')->whereNotIn('status', LeadStatus::positive())->select('id'))
            ->distinct()
            ->count('lead_id');
    }

    /** Re-score a closed day from its stored numbers (after a correction). */
    public function rescoreStored(AttendanceSession $session): AttendanceSession
    {
        $user = $session->user;
        $required = max(1, $user->currentShift()->required_minutes * 60);
        $tl = (int) ($session->target_leads ?? $this->targets($user)['leads']);
        $ti = (int) ($session->target_interested ?? $this->targets($user)['interested']);
        $parts = [
            min(1, (int) $session->active_seconds / $required),
            $tl > 0 ? min(1, (int) $session->leads_created / $tl) : 1,
            $ti > 0 ? min(1, (int) $session->interested_count / $ti) : 1,
        ];
        $score = (int) floor(array_sum($parts) / 3 * 100);
        $grade = self::gradeFor($score);
        $session->forceFill(['work_score' => $score, 'grade' => $grade, 'status' => self::statusForGrade($grade, (bool) $session->is_late)])->save();

        return $session;
    }

    /** Full breakdown for a session (live for today, stored numbers once finalized). */
    public function forSession(User $user, AttendanceSession $session): array
    {
        $shift = $user->currentShift();
        $required = max(1, $shift->required_minutes * 60);
        $date = $session->work_date;

        $live = $session->grade === null;
        // Closed days keep the targets they had that day
        $targets = (! $live && $session->target_leads !== null)
            ? ['leads' => (int) $session->target_leads, 'interested' => (int) $session->target_interested]
            : $this->targets($user);
        $created = $live ? $this->leadsCreated($user, $date) : $session->leads_created;
        $interested = $live ? $this->interestedCount($user, $date) : $session->interested_count;
        $active = (int) $session->active_seconds;

        $parts = [
            'time' => min(1, $active / $required),
            'leads' => $targets['leads'] > 0 ? min(1, $created / $targets['leads']) : 1,
            'interested' => $targets['interested'] > 0 ? min(1, $interested / $targets['interested']) : 1,
        ];
        $score = (int) floor(array_sum($parts) / 3 * 100);

        // Idle = time since punch-in that was neither active work nor break
        $end = $session->check_out_at ?? ($live && $date->isToday() ? now() : ($session->last_heartbeat_at ?? $session->check_in_at));
        $breakSeconds = $session->breaks->sum(fn ($b) => max(0, ($b->ended_at ?? $end)->getTimestamp() - $b->started_at->getTimestamp()));
        $idle = max(0, $end->getTimestamp() - $session->check_in_at->getTimestamp() - $breakSeconds - $active);

        return [
            'required' => $required,
            'active' => $active,
            'idle' => $idle,
            'break' => $breakSeconds,
            'created' => $created,
            'interested' => $interested,
            'targets' => $targets,
            'parts' => array_map(fn ($p) => (int) floor($p * 100), $parts),
            'score' => $session->grade !== null && $session->work_score !== null ? $session->work_score : $score,
            'grade' => $session->grade ?? self::gradeFor($score),
            'complete' => $score >= config('portal.work_score.grades.full'),
        ];
    }

    public static function gradeFor(int $score): string
    {
        $g = config('portal.work_score.grades');

        return match (true) {
            $score >= $g['full'] => 'full',
            $score >= $g['half'] => 'half',
            $score >= $g['quarter'] => 'quarter',
            default => 'none',
        };
    }

    public static function statusForGrade(string $grade, bool $late): AttendanceStatus
    {
        return match ($grade) {
            'full' => $late ? AttendanceStatus::Late : AttendanceStatus::Present,
            'half' => AttendanceStatus::HalfDay,
            'quarter' => AttendanceStatus::QuarterDay,
            default => AttendanceStatus::Absent,
        };
    }

    /** Battery level (0–100) and label for a grade. */
    public static function battery(?string $grade): array
    {
        return match ($grade) {
            'full' => [100, 'Full Day', 'success'],
            'half' => [50, 'Half Day', 'warning'],
            'quarter' => [25, 'Quarter Day', 'purple'],
            'none' => [0, 'Absent', 'secondary'],
            default => [null, '', 'secondary'],
        };
    }

    /**
     * Lock in the day's numbers and set the attendance status from the grade.
     * Used at punch-out and for past days that were never closed (browser closed,
     * device shut down) — those are closed at the last activity heartbeat.
     */
    public function finalize(AttendanceSession $session): AttendanceSession
    {
        $session->loadMissing('user', 'breaks');

        if ($session->check_out_at === null) {
            $lastSeen = $session->last_heartbeat_at ?? $session->check_in_at;
            $session->check_out_at = $lastSeen;
            $session->checkout_type = 'auto';
            foreach ($session->breaks->whereNull('ended_at') as $break) {
                $break->update(['ended_at' => $lastSeen, 'duration_minutes' => intdiv(max(0, $lastSeen->getTimestamp() - $break->started_at->getTimestamp()), 60)]);
            }
        }

        $session->grade = null; // compute live numbers
        $data = $this->forSession($session->user, $session);

        $session->fill([
            'leads_created' => $data['created'],
            'interested_count' => $data['interested'],
            'target_leads' => $data['targets']['leads'],
            'target_interested' => $data['targets']['interested'],
            'work_score' => $data['score'],
            'grade' => $data['grade'],
            'status' => self::statusForGrade($data['grade'], (bool) $session->is_late),
        ])->save();

        return $session;
    }

    /** Close every open session from earlier days. Returns how many were closed. */
    public function finalizePastDays(?User $user = null): int
    {
        $sessions = AttendanceSession::query()
            ->whereNull('grade')
            ->whereDate('work_date', '<', today())
            ->when($user, fn ($q) => $q->where('user_id', $user->id))
            ->with('user', 'breaks')
            ->get();

        $sessions->each(fn ($s) => $this->finalize($s));

        return $sessions->count();
    }
}
