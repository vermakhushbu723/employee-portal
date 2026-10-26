<?php

namespace App\Services;

use App\Enums\LeadStatus;
use App\Enums\Role;
use App\Models\Announcement;
use App\Models\DataShare;
use App\Models\Lead;
use App\Models\LeadBatch;
use App\Models\User;
use App\Models\WorkAssignment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Shares (assigns) uploaded calling data to employees, and takes unworked data back. */
class DataShareService
{
    public function __construct(
        private AuditLogger $audit,
        private WorkAssignmentService $assignments,
    ) {}

    /** Employees who can receive data. */
    public static function recipients(): Collection
    {
        return User::whereIn('role', [Role::Employee->value, Role::TeamLeader->value])
            ->where('status', 'active')
            ->orderBy('name')
            ->get();
    }

    /** Unassigned uploaded leads, optionally from one batch. */
    public static function pool(?int $batchId): Builder
    {
        return Lead::query()
            ->whereNotNull('batch_id')
            ->whereNull('assigned_to')
            ->when($batchId, fn ($q) => $q->where('batch_id', $batchId));
    }

    /**
     * mode "equal": split $total (0 = everything available) evenly.
     * mode "each":  give every employee $perEmployee leads.
     *
     * Each employee gets a calling task (file name + admin's work message + interested target),
     * which shows on their dashboard / My Assigned Work and raises today's target until done.
     *
     * @return array<int, int> user id => leads given
     */
    public function share(User $admin, ?int $batchId, array $userIds, string $mode, int $count, ?string $message = null, int $interestedTarget = 0, ?int $managerId = null): array
    {
        $users = self::recipients()->whereIn('id', $userIds)->values();
        if ($users->isEmpty()) {
            throw ValidationException::withMessages(['users' => 'Select at least one active employee.']);
        }

        $available = self::pool($batchId)->count();
        $wanted = $mode === 'each' ? $count * $users->count() : ($count > 0 ? $count : $available);
        $take = min($wanted, $available);

        if ($take === 0) {
            throw ValidationException::withMessages(['count' => 'No unassigned data left in this selection. Upload more data first.']);
        }

        [$given, $byBatch, $share] = DB::transaction(function () use ($admin, $batchId, $users, $take, $mode, $count, $message, $interestedTarget, $managerId) {
            // Logged so it shows under Recent Data Shares and can be rolled back
            $share = DataShare::create([
                'shared_by' => $admin->id,
                'batch_id' => $batchId,
                'manager_id' => $managerId,
                'message' => $message,
                'interested_target' => $interestedTarget,
                'mode' => $mode,
            ]);
            $ids = self::pool($batchId)->orderBy('id')->limit($take)->lockForUpdate()->pluck('id');
            $given = array_fill_keys($users->pluck('id')->all(), 0);
            $now = now();

            // Round-robin so everyone gets a fair mix of the list
            $buckets = [];
            foreach ($ids->values() as $i => $leadId) {
                $userId = $users[$i % $users->count()]->id;
                if ($mode === 'each' && $given[$userId] >= $count) {
                    continue;
                }
                $buckets[$userId][] = $leadId;
                $given[$userId]++;
            }

            foreach ($buckets as $userId => $leadIds) {
                foreach (array_chunk($leadIds, 500) as $chunk) {
                    Lead::whereIn('id', $chunk)->update([
                        'assigned_to' => $userId,
                        'assigned_by' => $admin->id,
                        'assigned_at' => $now,
                        'share_id' => $share->id,
                        'updated_at' => $now,
                    ]);
                }
            }

            // How many leads each employee got from each file (the pool can span files)
            $byBatch = [];
            foreach ($buckets as $userId => $leadIds) {
                foreach (Lead::whereIn('id', $leadIds)->selectRaw('batch_id, count(*) as c')->groupBy('batch_id')->pluck('c', 'batch_id') as $bid => $c) {
                    $byBatch[$bid][$userId] = (int) $c;
                }
            }

            $given = array_filter($given);
            $share->update(['total' => array_sum($given), 'recipients' => $given]);

            return [$given, $byBatch, $share];
        });

        foreach ($byBatch as $bid => $perUser) {
            $this->assignments->createCalling($admin, LeadBatch::find($bid), $perUser, $message, $interestedTarget, $share->id);
        }

        $this->audit->log('data.share', 'Shared '.array_sum($given).' lead(s) with '.count($given).' employee(s)', ['share_id' => $share->id, 'batch_id' => $batchId, 'given' => $given, 'message' => $message], $admin);

        return $given;
    }

    /**
     * Roll back a share sent by mistake: leads not called yet (status "new") go back to the
     * unassigned pool; leads already worked on stay with the employee. Calling tasks are
     * reduced (or removed) and the employees are told.
     *
     * @return int leads returned to the pool
     */
    public function rollback(User $admin, DataShare $share): int
    {
        if ($share->rolled_back_at) {
            throw ValidationException::withMessages(['share' => 'This share was already rolled back.']);
        }

        [$returned, $perUser] = DB::transaction(function () use ($share, $admin) {
            $leads = fn () => Lead::where('share_id', $share->id)->where('status', LeadStatus::New->value);
            $perUser = $leads()->selectRaw('assigned_to, count(*) as c')->groupBy('assigned_to')->pluck('c', 'assigned_to')->all();
            $returned = $leads()->update(['assigned_to' => null, 'assigned_by' => null, 'assigned_at' => null, 'share_id' => null, 'updated_at' => now()]);

            foreach (WorkAssignment::where('share_id', $share->id)->get() as $task) {
                $left = Lead::where('share_id', $share->id)->where('assigned_to', $task->user_id)->count();
                $left === 0 ? $task->delete() : $task->update(['total_records' => $left]);
            }

            $share->update(['rolled_back_at' => now(), 'rolled_back_by' => $admin->id, 'rolled_back_count' => $returned]);

            return [$returned, $perUser];
        });

        $file = $share->batch?->file_name ?: $share->batch?->name ?: 'shared data';
        foreach ($perUser as $userId => $n) {
            Announcement::create([
                'user_id' => $userId,
                'created_by' => $admin->id,
                'type' => 'important',
                'title' => 'Shared Data Taken Back',
                'body' => "{$n} un-called lead(s) from {$file} were taken back by admin (shared by mistake). Leads you already called stay with you.",
            ]);
        }

        $this->audit->log('data.rollback', "Rolled back share #{$share->id}: {$returned} un-called lead(s) returned to the pool", ['share_id' => $share->id, 'per_user' => $perUser], $admin);

        return $returned;
    }

    /** Take back leads the employee has not worked on yet (status still "new"). */
    public function recall(User $admin, User $employee, ?int $batchId): int
    {
        $count = Lead::where('assigned_to', $employee->id)
            ->whereNotNull('batch_id')
            ->where('status', LeadStatus::New->value)
            ->when($batchId, fn ($q) => $q->where('batch_id', $batchId))
            ->update(['assigned_to' => null, 'assigned_by' => null, 'assigned_at' => null, 'share_id' => null, 'updated_at' => now()]);

        $this->audit->log('data.recall', "Took back {$count} unworked lead(s) from {$employee->employee_code}", ['user_id' => $employee->id, 'batch_id' => $batchId], $admin);

        return $count;
    }

    /** Per-employee progress on shared data (for the live table). */
    public static function distribution(?int $batchId): Collection
    {
        $rows = Lead::query()
            ->whereNotNull('batch_id')
            ->whereNotNull('assigned_to')
            ->when($batchId, fn ($q) => $q->where('batch_id', $batchId))
            ->selectRaw('assigned_to, status, count(*) as total, max(updated_at) as last_update')
            ->groupBy('assigned_to', 'status')
            ->toBase()   // plain rows: keep status as a string, not the enum cast
            ->get()
            ->groupBy('assigned_to');

        $users = User::whereIn('id', $rows->keys())->get()->keyBy('id');

        return $rows->map(function ($statuses, $userId) use ($users) {
            $by = $statuses->pluck('total', 'status');
            $assigned = (int) $statuses->sum('total');
            $pending = (int) ($by[LeadStatus::New->value] ?? 0);

            return [
                'user' => $users[$userId] ?? null,
                'assigned' => $assigned,
                'pending' => $pending,
                'worked' => $assigned - $pending,
                'interested' => (int) (($by[LeadStatus::Interested->value] ?? 0) + ($by[LeadStatus::FollowUp->value] ?? 0)),
                'converted' => (int) ($by[LeadStatus::Converted->value] ?? 0),
                'last_update' => $statuses->max('last_update'),
            ];
        })->filter(fn ($r) => $r['user'])->sortByDesc('assigned')->values();
    }
}
