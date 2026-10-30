<?php

namespace App\Http\Controllers;

use App\Enums\LeadStatus;
use App\Models\Department;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Services\LeadReviewService;
use App\Services\TeamPerformanceService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Team Performance — one screen, three tabs, for admin (everyone), manager and team leader (their own team):
 *   Hierarchy     HR → Manager → TL → Employee with work, attendance today, weekly / monthly averages
 *   Assigned Work work given by admin (direct) or a manager / TL, and how far each person got
 *   Lead Progress every lead with its TL review and Manager review status ("Reviewed" mark)
 */
class TeamPerformanceController extends Controller
{
    public function __construct(private TeamPerformanceService $team) {}

    public function index(Request $request)
    {
        $viewer = $request->user();
        $tab = in_array($request->query('tab'), ['hierarchy', 'work', 'leads'], true) ? $request->query('tab') : 'hierarchy';
        $from = $this->date($request->query('from')) ?? today()->startOfMonth();
        $to = $this->date($request->query('to')) ?? today();
        if ($to->lt($from)) {
            [$from, $to] = [$to, $from];
        }
        $dept = $request->integer('department') ?: null;
        $q = trim((string) $request->query('q'));

        $rows = $this->team->tree($viewer, $from, $to);
        $match = fn ($u) => (! $dept || $u->employee?->department_id === $dept)
            && ($q === '' || str_contains(strtolower($u->name.' '.$u->employee_code), strtolower($q)));
        if ($dept || $q !== '') {
            // Keep the people that match plus the managers above them, so the tree still reads correctly
            $keep = [];
            foreach ($rows as $i => $r) {
                if ($match($r['user'])) {
                    $keep[$r['user']->id] = true;
                    $parent = $r['parent'];
                    while ($parent && ! isset($keep[$parent])) {
                        $keep[$parent] = true;
                        $parent = collect($rows)->firstWhere('user.id', $parent)['parent'] ?? null;
                    }
                }
            }
            $rows = array_values(array_filter($rows, fn ($r) => isset($keep[$r['user']->id])));
        }
        $top = $rows ? collect($rows)->firstWhere('depth', 0) : null;
        $all = collect($rows);

        return view('team.performance', [
            'tab' => $tab, 'from' => $from, 'to' => $to, 'dept' => $dept, 'q' => $q,
            'portal' => $viewer->role->portal(),
            'rows' => $rows,
            'kpi' => [
                'managers' => $all->filter(fn ($r) => in_array($r['user']->role->value, ['manager', 'hr'], true))->count(),
                'tls' => $all->filter(fn ($r) => $r['user']->role->value === 'team_leader')->count(),
                'employees' => $all->filter(fn ($r) => $r['user']->role->value === 'employee')->count(),
                'assigned' => $all->sum('own.assigned'), 'completed' => $all->sum('own.completed'), 'pending' => $all->sum('own.pending'),
                'calls' => $all->sum('own.calls'), 'meetings' => $all->sum('own.meetings'), 'converted' => $all->sum('own.converted'),
                'week_att' => $all->count() ? (int) round($all->avg('own.week_att')) : 0, 'month_att' => $all->count() ? (int) round($all->avg('own.month_att')) : 0,
                'month_score' => (int) round((float) $all->pluck('own.month_score')->filter(fn ($v) => $v !== null)->avg()),
            ],
            'work' => $tab === 'work' ? $this->team->assignedWork($viewer, $from, $to) : collect(),
            'leads' => $tab === 'leads' ? $this->team->leadProgress($viewer, $from, $to, $request->query('status')) : null,
            'departments' => Department::forData()->get(),
            'statuses' => LeadStatus::cases(),
            'top' => $top,
        ]);
    }

    /** "Reviewed" mark on a lead without changing it (counts as TL / Manager review). */
    public function reviewed(Request $request, Lead $lead, LeadReviewService $review)
    {
        abort_unless($review->canReview($request->user(), $lead), 403);
        LeadActivity::create([
            'lead_id' => $lead->id, 'user_id' => $request->user()->id, 'type' => 'review',
            'from_status' => $lead->status->value, 'to_status' => $lead->status->value,
            'note' => 'Reviewed by '.$request->user()->name.' ('.$request->user()->roleLabel().')',
        ]);

        return $request->expectsJson()
            ? response()->json(['ok' => true, 'message' => "{$lead->name} marked reviewed."])
            : back()->with('success', "{$lead->name} marked reviewed.");
    }

    private function date(?string $v): ?Carbon
    {
        try {
            return $v ? Carbon::parse($v)->startOfDay() : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
