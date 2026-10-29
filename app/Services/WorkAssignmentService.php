<?php

namespace App\Services;

use App\Enums\LeadStatus;
use App\Models\Announcement;
use App\Models\Lead;
use App\Models\LeadBatch;
use App\Models\User;
use App\Models\WorkAssignment;
use Illuminate\Support\Collection;

/**
 * Work given with a shared file (calling task) or as a lead-creation task.
 *
 *   calling:     total = records shared with the employee from that file
 *                called = those records whose status is no longer "new"
 *                interested = those records marked Interested / Follow-up / Converted
 *                done when everything is called AND the interested target is reached
 *   lead_create: created = leads the employee created since the task was given
 *                done when created >= lead target
 *
 * While a task is open its targets are added to the employee's daily targets
 * (WorkScoreService), so "today's target" goes up until the work is finished.
 */
class WorkAssignmentService
{
    public function __construct(private AuditLogger $audit) {}

    /** One calling task per employee after data is shared. Notification shows file name + work message. */
    public function createCalling(User $by, LeadBatch $batch, array $given, ?string $message, int $interestedTarget, ?int $shareId = null): void
    {
        foreach ($given as $userId => $count) {
            // Never more interested than the records given (19 records → at most 19 interested)
            $target = min($interestedTarget, $count);
            WorkAssignment::create([
                'user_id' => $userId,
                'assigned_by' => $by->id,
                'batch_id' => $batch->id,
                'share_id' => $shareId,
                'type' => 'calling',
                'title' => $batch->file_name ?: $batch->name,
                'message' => $message,
                'total_records' => $count,
                'interested_target' => $target,
                'assigned_at' => now(),
            ]);

            Announcement::create([
                'user_id' => $userId,
                'share_id' => $shareId,
                'created_by' => $by->id,
                'type' => 'important',
                'title' => 'New Calling Data Assigned',
                'body' => "File: {$batch->file_name} · {$count} lead(s) to call"
                    .($target ? " · Target: {$target} interested" : '')
                    .($message ? "\nWork: {$message}" : '')
                    ."\nOpen My Assigned Work → Start Calling.",
            ]);
        }
    }

    /** Lead-creation task for one or more employees. */
    public function createLeadTask(User $by, array $userIds, string $title, int $target, ?string $message): int
    {
        foreach ($userIds as $userId) {
            WorkAssignment::create([
                'user_id' => $userId,
                'assigned_by' => $by->id,
                'type' => 'lead_create',
                'title' => $title,
                'message' => $message,
                'lead_target' => $target,
                'assigned_at' => now(),
            ]);

            Announcement::create([
                'user_id' => $userId,
                'created_by' => $by->id,
                'type' => 'important',
                'title' => 'New Lead Creation Task',
                'body' => "{$title} · Target: {$target} new lead(s)".($message ? "\nWork: {$message}" : '')."\nOpen My Assigned Work → Create Lead.",
            ]);
        }

        $this->audit->log('work.lead_task', "Lead creation task \"{$title}\" ({$target}) for ".count($userIds).' employee(s)', [], $by);

        return count($userIds);
    }

    /** Progress numbers for one task (also marks it complete when done). */
    public function progress(WorkAssignment $a): array
    {
        if ($a->type === 'calling') {
            // Leads of this share (older tasks without a share: the whole file for this employee)
            $leads = Lead::where('assigned_to', $a->user_id)
                ->when($a->share_id, fn ($q) => $q->where('share_id', $a->share_id), fn ($q) => $q->where('batch_id', $a->batch_id));
            $total = (clone $leads)->count();
            $called = (clone $leads)->where('status', '!=', LeadStatus::New->value)->count();
            $interested = (clone $leads)->whereIn('status', ['interested', 'follow_up', 'converted'])->count();
            $target = self::target($a, $total);

            $p = [
                'target' => $target,
                'total' => $total,
                'called' => $called,
                'interested' => $interested,
                'pending_call' => max(0, $total - $called),
                'pending_interested' => max(0, $target - $interested),
                'called_pct' => $total ? (int) floor($called / $total * 100) : 0,
                'interested_pct' => $target ? (int) min(100, floor($interested / $target * 100)) : 100,
            ];
            $done = $total > 0 && $p['pending_call'] === 0 && $p['pending_interested'] === 0;
        } else {
            $created = Lead::where('created_by', $a->user_id)->where('created_at', '>=', $a->assigned_at)->count();
            $p = [
                'created' => $created,
                'pending_create' => max(0, $a->lead_target - $created),
                'created_pct' => $a->lead_target ? (int) min(100, floor($created / $a->lead_target * 100)) : 100,
            ];
            $done = $p['pending_create'] === 0;
        }

        if ($done && ! $a->completed_at) {
            $a->forceFill(['completed_at' => now()])->saveQuietly();
        } elseif (! $done && $a->completed_at) {
            $a->forceFill(['completed_at' => null])->saveQuietly(); // e.g. a lead moved back
        }

        return $p + ['done' => $done];
    }

    /** Interested target of a calling task, never above the number of records in it. */
    public static function target(WorkAssignment $a, ?int $records = null): int
    {
        return (int) min($a->interested_target, $records ?? $a->total_records ?? $a->interested_target);
    }

    /** Open tasks plus tasks finished today, with progress — for the dashboard / My Assigned Work. */
    public function cardsFor(User $user): Collection
    {
        return $user->workAssignments()
            ->with('assigner')
            ->where(fn ($q) => $q->whereNull('completed_at')->orWhere('completed_at', '>=', today()))
            ->latest('assigned_at')
            ->get()
            ->map(fn ($a) => ['task' => $a, 'p' => $this->progress($a)]);
    }

    /** Extra daily targets from open tasks: interested (calling) and leads (lead_create). */
    public function openTargets(User $user): array
    {
        $open = $user->workAssignments()->whereNull('completed_at')->get()
            ->filter(fn ($a) => ! $this->progress($a)['done']);

        return [
            'interested' => (int) $open->where('type', 'calling')->sum(fn ($a) => self::target($a)),
            'leads' => (int) $open->where('type', 'lead_create')->sum('lead_target'),
        ];
    }
}
