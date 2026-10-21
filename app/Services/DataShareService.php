<?php

namespace App\Services;

use App\Enums\LeadStatus;
use App\Enums\Role;
use App\Models\Announcement;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Shares (assigns) uploaded calling data to employees, and takes unworked data back. */
class DataShareService
{
    public function __construct(private AuditLogger $audit) {}

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
     * @return array<int, int> user id => leads given
     */
    public function share(User $admin, ?int $batchId, array $userIds, string $mode, int $count): array
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

        $given = DB::transaction(function () use ($admin, $batchId, $users, $take, $mode, $count) {
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
                        'updated_at' => $now,
                    ]);
                }

                Announcement::create([
                    'user_id' => $userId,
                    'created_by' => $admin->id,
                    'type' => 'important',
                    'title' => 'New Calling Data Assigned',
                    'body' => count($leadIds).' new lead(s) have been shared with you. Open My Leads → New Data to start calling.',
                ]);
            }

            return array_filter($given);
        });

        $this->audit->log('data.share', 'Shared '.array_sum($given).' lead(s) with '.count($given).' employee(s)', ['batch_id' => $batchId, 'given' => $given], $admin);

        return $given;
    }

    /** Take back leads the employee has not worked on yet (status still "new"). */
    public function recall(User $admin, User $employee, ?int $batchId): int
    {
        $count = Lead::where('assigned_to', $employee->id)
            ->whereNotNull('batch_id')
            ->where('status', LeadStatus::New->value)
            ->when($batchId, fn ($q) => $q->where('batch_id', $batchId))
            ->update(['assigned_to' => null, 'assigned_by' => null, 'assigned_at' => null, 'updated_at' => now()]);

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
