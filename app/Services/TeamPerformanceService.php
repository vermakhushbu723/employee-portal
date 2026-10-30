<?php

namespace App\Services;

use App\Enums\Role;
use App\Models\AttendanceSession;
use App\Models\LeaveRequest;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\LeadCommunication;
use App\Models\Meeting;
use App\Models\User;
use App\Models\WorkAssignment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Team Performance (one screen for admin, manager, team leader):
 *   - hierarchy HR → Manager → Manager → TL → TL → Employee, from each person's Reporting Manager
 *   - per person: leads assigned / called / pending, created, calls, follow-ups, meetings, converted,
 *     work completion, attendance today, weekly / monthly attendance average and work score average
 *   - managers / TLs also get their whole team's totals (everyone below them)
 */
class TeamPerformanceService
{
    /** People the viewer may see: admin = everyone, manager / TL = everyone below them (+ themselves). */
    public function people(User $viewer): Collection
    {
        $all = User::with('employee.department')->where('status', 'active')
            ->where('role', '!=', Role::SuperAdmin->value)->orderBy('name')->get();
        if ($viewer->role->portal() === 'admin') {
            return $all;
        }
        $ids = $this->below($all, $viewer->id);

        return $all->filter(fn ($u) => $u->id === $viewer->id || in_array($u->id, $ids, true))->values();
    }

    /** Ids of everyone below $id in the reporting tree. */
    public function below(Collection $all, int $id): array
    {
        $children = $all->groupBy(fn ($u) => (int) $u->employee?->manager_id);
        $out = [];
        $stack = [$id];
        while ($stack) {
            $cur = array_pop($stack);
            foreach ($children->get($cur, collect()) as $c) {
                if (! in_array($c->id, $out, true) && $c->id !== $id) {
                    $out[] = $c->id;
                    $stack[] = $c->id;
                }
            }
        }

        return $out;
    }

    /**
     * Rows in tree order: [user, depth, parent_id, has_children, own => metrics, team => metrics (self + everyone below)].
     */
    public function tree(User $viewer, Carbon $from, Carbon $to): array
    {
        $people = $this->people($viewer);
        $metrics = $this->metrics($people->pluck('id')->all(), $from, $to);
        $ids = $people->pluck('id')->flip();
        $children = $people->groupBy(fn ($u) => $ids->has((int) $u->employee?->manager_id) ? (int) $u->employee->manager_id : 0);
        $rank = fn (User $u) => [Role::Hr->value => 0, Role::Manager->value => 1, Role::TeamLeader->value => 2][$u->role->value] ?? 3;
        $roots = $viewer->role->portal() === 'admin'
            ? $children->get(0, collect())
            : $people->where('id', $viewer->id);

        $rows = [];
        $walk = function (User $u, int $depth, ?int $parent, array $seen) use (&$walk, &$rows, $children, $metrics, $rank) {
            $kids = $children->get($u->id, collect())->reject(fn ($k) => in_array($k->id, $seen, true))
                ->sortBy(fn ($k) => [$rank($k), $k->name])->values();
            $rows[] = ['user' => $u, 'depth' => $depth, 'parent' => $parent, 'has_children' => $kids->isNotEmpty(), 'own' => $metrics[$u->id] ?? $this->empty()];
            foreach ($kids as $k) {
                $walk($k, $depth + 1, $u->id, array_merge($seen, [$u->id]));
            }
        };
        foreach ($roots->sortBy(fn ($k) => [$rank($k), $k->name]) as $r) {
            $walk($r, 0, null, []);
        }
        // Team = the person + everyone below them (who the viewer may see)
        foreach ($rows as &$row) {
            $row['team_ids'] = array_values(array_intersect(array_merge([$row['user']->id], $this->below($people, $row['user']->id)), $people->pluck('id')->all()));
        }
        unset($row);

        foreach ($rows as &$row) {
            $row['team'] = $this->sum(array_map(fn ($id) => $metrics[$id] ?? $this->empty(), $row['team_ids']));
            $row['members'] = count($row['team_ids']) - 1;
            $row['status'] = $this->status($row['has_children'] ? $row['team'] : $row['own']);
        }

        return $rows;
    }

    /** Metrics for many users at once (keyed by user id). */
    public function metrics(array $ids, Carbon $from, Carbon $to): array
    {
        if (! $ids) {
            return [];
        }
        $range = [$from->copy()->startOfDay(), $to->copy()->endOfDay()];
        $assigned = Lead::whereIn('assigned_to', $ids)->selectRaw("assigned_to as u, count(*) as total, sum(case when status = 'new' then 1 else 0 end) as pending")
            ->groupBy('assigned_to')->get()->keyBy('u');
        $created = Lead::whereIn('created_by', $ids)->whereBetween('created_at', $range)->selectRaw('created_by as u, count(*) as n')->groupBy('created_by')->pluck('n', 'u');
        $calls = LeadCommunication::whereIn('user_id', $ids)->where('channel', 'call')->whereBetween('created_at', $range)->selectRaw('user_id as u, count(*) as n')->groupBy('user_id')->pluck('n', 'u');
        $worked = LeadActivity::whereIn('user_id', $ids)->whereBetween('created_at', $range)->whereIn('type', ['status_change', 'follow_up'])
            ->selectRaw('user_id as u, count(distinct lead_id) as n')->groupBy('user_id')->pluck('n', 'u');
        $followUps = LeadActivity::whereIn('user_id', $ids)->whereBetween('created_at', $range)->whereIn('to_status', ['follow_up', 'callback'])
            ->selectRaw('user_id as u, count(*) as n')->groupBy('user_id')->pluck('n', 'u');
        $meetings = Meeting::whereIn('user_id', $ids)->whereBetween('scheduled_at', $range)->whereNotIn('status', ['declined', 'cancelled'])
            ->selectRaw('user_id as u, count(*) as n')->groupBy('user_id')->pluck('n', 'u');
        $converted = Lead::whereIn('assigned_to', $ids)->where('status', 'converted')->whereBetween('converted_at', $range)
            ->selectRaw('assigned_to as u, count(*) as n')->groupBy('assigned_to')->pluck('n', 'u');
        $avg = $this->attendanceAverages($ids);
        $today = AttendanceSession::whereIn('user_id', $ids)->whereDate('work_date', today())->get()->keyBy('user_id');
        $onLeave = LeaveRequest::whereIn('user_id', $ids)->where('status', 'approved')->whereDate('from_date', '<=', today())->whereDate('to_date', '>=', today())->pluck('user_id')->flip();

        $out = [];
        foreach ($ids as $id) {
            $a = $assigned->get($id);
            $total = (int) ($a->total ?? 0);
            $pending = (int) ($a->pending ?? 0);
            $s = $today->get($id);
            $out[$id] = [
                'assigned' => $total,
                'completed' => $total - $pending,
                'pending' => $pending,
                'created' => (int) ($created[$id] ?? 0),
                'calls' => max((int) ($calls[$id] ?? 0), (int) ($worked[$id] ?? 0)),
                'follow_ups' => (int) ($followUps[$id] ?? 0),
                'meetings' => (int) ($meetings[$id] ?? 0),
                'converted' => (int) ($converted[$id] ?? 0),
                'completion' => $total ? (int) round(($total - $pending) / $total * 100) : null,
                'today' => $s ? ['present', $s->check_in_at->format('h:i A'), $s->is_late] : (isset($onLeave[$id]) ? ['leave', null, false] : ['absent', null, false]),
                'today_score' => $s?->work_score,
            ] + ($avg[$id] ?? ['week_att' => 0, 'month_att' => 0, 'week_score' => null, 'month_score' => null]);
        }

        return $out;
    }

    /**
     * Attendance % (days present ÷ working days, Sundays off) and average work score,
     * for the last 7 days and this month so far.
     */
    public function attendanceAverages(array $ids): array
    {
        if (! $ids) {
            return [];
        }
        $weekFrom = today()->subDays(6);
        $monthFrom = today()->startOfMonth();
        $sessions = AttendanceSession::whereIn('user_id', $ids)->whereDate('work_date', '>=', min($weekFrom, $monthFrom))
            ->get(['user_id', 'work_date', 'work_score'])->groupBy('user_id');
        $workingDays = fn (Carbon $start) => collect(\Carbon\CarbonPeriod::create($start, today()))->reject(fn ($d) => $d->isSunday())->count();
        $weekDays = max(1, $workingDays($weekFrom));
        $monthDays = max(1, $workingDays($monthFrom));

        $out = [];
        foreach ($ids as $id) {
            $list = $sessions->get($id, collect());
            $week = $list->filter(fn ($s) => $s->work_date->gte($weekFrom));
            $month = $list->filter(fn ($s) => $s->work_date->gte($monthFrom));
            $out[$id] = [
                'week_att' => (int) min(100, round($week->count() / $weekDays * 100)),
                'month_att' => (int) min(100, round($month->count() / $monthDays * 100)),
                'week_score' => $week->whereNotNull('work_score')->isNotEmpty() ? (int) round($week->whereNotNull('work_score')->avg('work_score')) : null,
                'month_score' => $month->whereNotNull('work_score')->isNotEmpty() ? (int) round($month->whereNotNull('work_score')->avg('work_score')) : null,
            ];
        }

        return $out;
    }

    /** Work given out (admin direct or by a manager / TL), with progress, for the viewer's people. */
    public function assignedWork(User $viewer, Carbon $from, Carbon $to): Collection
    {
        $people = $this->people($viewer)->keyBy('id');
        $assignments = app(WorkAssignmentService::class);

        return WorkAssignment::with('assigner', 'user.employee.manager')
            ->whereIn('user_id', $people->keys())
            ->whereBetween('assigned_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->latest('assigned_at')->limit(300)->get()
            ->map(function (WorkAssignment $a) use ($assignments, $people) {
                $p = $assignments->progress($a);
                $chain = [];
                $cur = $a->user?->employee?->manager_id;
                for ($i = 0; $cur && $i < 6; $i++) {
                    $m = $people->get($cur) ?? User::with('employee')->find($cur);
                    if (! $m) {
                        break;
                    }
                    array_unshift($chain, $m);
                    $cur = $m->employee?->manager_id;
                }
                $pct = $a->type === 'calling'
                    ? ($p['total'] ? (int) round((($p['called'] / $p['total']) * 0.7 + ($p['target'] ? min(1, $p['interested'] / $p['target']) : 1) * 0.3) * 100) : 0)
                    : $p['created_pct'];

                return ['task' => $a, 'p' => $p, 'chain' => $chain, 'pct' => $pct, 'direct' => $a->assigner?->role->portal() === 'admin'];
            });
    }

    /** Leads with their TL / manager review status (review = a correction or a "Reviewed" mark). */
    public function leadProgress(User $viewer, Carbon $from, Carbon $to, ?string $status = null): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $ids = $this->people($viewer)->pluck('id');
        $leads = Lead::with(['assignee.employee.manager', 'creator', 'meetings' => fn ($q) => $q->whereIn('status', ['pending', 'upcoming', 'live'])->orderBy('scheduled_at')])
            ->where(fn ($q) => $q->whereIn('assigned_to', $ids)->orWhereIn('created_by', $ids))
            ->where(fn ($q) => $q->whereBetween('updated_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()]))
            ->when($status, fn ($q) => $q->where('status', $status))
            ->latest('updated_at')->paginate(25)->withQueryString();

        $reviews = LeadActivity::with('user:id,role')->whereIn('lead_id', $leads->pluck('id'))->whereIn('type', ['correction', 'review'])->get()->groupBy('lead_id');
        $leads->getCollection()->transform(function (Lead $l) use ($reviews) {
            $by = $reviews->get($l->id, collect());
            $l->tl_review = $by->contains(fn ($r) => $r->user?->role === Role::TeamLeader);
            $l->manager_review = $by->contains(fn ($r) => in_array($r->user?->role, [Role::Manager, Role::Hr, Role::SuperAdmin], true));

            return $l;
        });

        return $leads;
    }

    public function status(array $m): array
    {
        $v = $m['completion'] ?? $m['month_score'];
        if ($v === null) {
            return ['—', 'secondary'];
        }

        return $v >= 70 ? ['Good', 'success'] : ($v >= 50 ? ['Average', 'warning'] : ['Poor', 'danger']);
    }

    private function empty(): array
    {
        return ['assigned' => 0, 'completed' => 0, 'pending' => 0, 'created' => 0, 'calls' => 0, 'follow_ups' => 0, 'meetings' => 0, 'converted' => 0,
            'completion' => null, 'today' => ['absent', null, false], 'today_score' => null, 'week_att' => 0, 'month_att' => 0, 'week_score' => null, 'month_score' => null];
    }

    /** Totals of several people (counts summed, averages averaged). */
    private function sum(array $list): array
    {
        $out = $this->empty();
        foreach (['assigned', 'completed', 'pending', 'created', 'calls', 'follow_ups', 'meetings', 'converted'] as $k) {
            $out[$k] = array_sum(array_column($list, $k));
        }
        $out['completion'] = $out['assigned'] ? (int) round($out['completed'] / $out['assigned'] * 100) : null;
        foreach (['week_att', 'month_att'] as $k) {
            $out[$k] = $list ? (int) round(array_sum(array_column($list, $k)) / count($list)) : 0;
        }
        foreach (['week_score', 'month_score'] as $k) {
            $vals = array_filter(array_column($list, $k), fn ($v) => $v !== null);
            $out[$k] = $vals ? (int) round(array_sum($vals) / count($vals)) : null;
        }
        $out['present'] = count(array_filter($list, fn ($m) => $m['today'][0] === 'present'));
        $out['people'] = count($list);

        return $out;
    }
}
