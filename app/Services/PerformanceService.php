<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\TaskActivity;
use App\Models\User;
use App\Support\Duration;
use Illuminate\Support\Carbon;

/**
 * Productivity numbers for one employee.
 *   calls       = progress logged on telecalling tasks
 *   leads       = leads created
 *   follow_ups  = follow-up activities on leads
 *   conversions = leads converted
 */
class PerformanceService
{
    public const METRICS = [
        'calls' => ['label' => 'Calls Made', 'icon' => 'telephone-fill', 'tone' => 'success'],
        'leads' => ['label' => 'Leads Created', 'icon' => 'person-fill', 'tone' => 'primary'],
        'follow_ups' => ['label' => 'Follow-ups', 'icon' => 'bar-chart-fill', 'tone' => 'purple'],
        'conversions' => ['label' => 'Conversions', 'icon' => 'trophy-fill', 'tone' => 'warning'],
    ];

    public function monthly(User $user, Carbon $month): array
    {
        $start = $month->copy()->startOfMonth();
        $end = $month->copy()->endOfMonth();
        $targets = $user->targets()->where('period', 'monthly')->pluck('target_value', 'metric');

        $metrics = [];
        foreach (self::METRICS as $key => $meta) {
            $value = $this->count($user, $key, $start, $end);
            $target = (int) ($targets[$key] ?? 0);
            $metrics[$key] = [...$meta, 'value' => $value, 'target' => $target, 'percent' => Duration::percent($value, $target)];
        }

        // Weeks of the month: 1–7, 8–14, 15–21, 22–28, 29–end.
        $weeks = [];
        for ($from = $start->copy(); $from->lte($end); $from->addDays(7)) {
            $to = $from->copy()->addDays(6)->endOfDay()->min($end);
            $weeks[] = [
                'label' => 'W'.(count($weeks) + 1),
                'range' => $from->format('j').'-'.$to->format('j'),
                'calls' => $this->count($user, 'calls', $from, $to),
                'leads' => $this->count($user, 'leads', $from, $to),
                'conversions' => $this->count($user, 'conversions', $from, $to),
            ];
        }

        $withTarget = collect($metrics)->where('target', '>', 0);

        return [
            'metrics' => $metrics,
            'weeks' => $weeks,
            'productivity' => $withTarget->isEmpty() ? 0 : (int) round($withTarget->avg('percent')),
        ];
    }

    /** Today's assignments rolled up by category (for "Today's Summary"). */
    public function today(User $user): array
    {
        return $user->taskAssignments()->with('task')
            ->whereDate('assigned_date', today())
            ->get()
            ->map(fn ($a) => [
                'label' => $a->task->title,
                'tone' => $a->task->categoryMeta()['tone'],
                'icon' => $a->task->categoryMeta()['icon'],
                'completed' => $a->completed_quantity,
                'target' => $a->task->target_quantity,
                'percent' => $a->progress(),
            ])
            ->all();
    }

    private function count(User $user, string $metric, Carbon $from, Carbon $to): int
    {
        $range = [$from->copy()->startOfDay(), $to->copy()->endOfDay()];

        return (int) match ($metric) {
            'calls' => TaskActivity::where('user_id', $user->id)
                ->where('action', 'progress')
                ->whereBetween('created_at', $range)
                ->whereHas('assignment.task', fn ($q) => $q->where('category', 'telecalling'))
                ->sum('quantity'),
            'leads' => Lead::where('created_by', $user->id)->whereBetween('created_at', $range)->count(),
            'follow_ups' => LeadActivity::where('user_id', $user->id)->where('type', 'follow_up')->whereBetween('created_at', $range)->count(),
            'conversions' => Lead::ownedBy($user)->whereBetween('converted_at', $range)->count(),
        };
    }
}
