<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\LeadBatch;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/** Admin → Department Data: uploaded / shared / worked data per department. */
class DepartmentDataController extends Controller
{
    public function index(Request $request)
    {
        [$from, $to] = $this->range($request);
        $filters = $this->filters($request);

        $departments = Department::forData()->get()->map(function ($dept) use ($from, $to, $filters) {
            return ['dept' => $dept, 'stats' => $this->stats($this->leads($dept->id, $from, $to, $filters)), 'records' => $this->leads($dept->id, null, null, [])->count()];
        });

        $stats = $this->stats($this->leads($filters['department'], $from, $to, $filters));
        [$pFrom, $pTo] = $this->previous($from, $to);
        $previous = $this->stats($this->leads($filters['department'], $pFrom, $pTo, $filters));

        return view('admin.departments.index', [
            'departments' => $departments,
            'stats' => $stats,
            'change' => $this->change($stats, $previous),
            'from' => $from, 'to' => $to, 'filters' => $filters,
            ...$this->filterOptions(),
        ]);
    }

    public function show(Request $request, Department $department)
    {
        [$from, $to] = $this->range($request);
        $filters = $this->filters($request);
        $base = fn () => $this->leads($department->id, $from, $to, $filters);

        $stats = $this->stats($base());
        [$pFrom, $pTo] = $this->previous($from, $to);
        $previous = $this->stats($this->leads($department->id, $pFrom, $pTo, $filters));

        // Lead flow, last 7 days up to the end of the range
        $flowEnd = $to->copy()->min(today());
        $days = collect(range(6, 0))->map(fn ($i) => $flowEnd->copy()->subDays($i)->toDateString());
        $flowLeads = fn () => Lead::whereIn('batch_id', LeadBatch::where('department_id', $department->id)->select('id'));
        $uploaded = $flowLeads()->whereDate('created_at', '>=', $days->first())->selectRaw('date(created_at) as d, count(*) as c')->groupBy('d')->pluck('c', 'd');
        $activity = fn (?array $statuses = null) => LeadActivity::whereIn('lead_id', $flowLeads()->select('id'))
            ->whereDate('created_at', '>=', $days->first())
            ->whereIn('type', ['status_change', 'follow_up'])
            ->when($statuses, fn ($q) => $q->whereIn('to_status', $statuses))
            ->selectRaw('date(created_at) as d, count(distinct lead_id) as c')->groupBy('d')->pluck('c', 'd');
        $called = $activity();
        $interested = $activity(['interested']);
        $converted = $activity(['converted']);

        $flow = [
            'labels' => $days->map(fn ($d) => Carbon::parse($d)->format('d M'))->all(),
            'uploaded' => $days->map(fn ($d) => (int) ($uploaded[$d] ?? 0))->all(),
            'called' => $days->map(fn ($d) => (int) ($called[$d] ?? 0))->all(),
            'interested' => $days->map(fn ($d) => (int) ($interested[$d] ?? 0))->all(),
            'converted' => $days->map(fn ($d) => (int) ($converted[$d] ?? 0))->all(),
        ];

        // Per employee and per manager
        $byEmployee = $base()->whereNotNull('assigned_to')
            ->selectRaw("assigned_to, count(*) as shared, sum(case when status <> 'new' then 1 else 0 end) as called, sum(case when status in ('interested','follow_up') then 1 else 0 end) as interested, sum(case when status = 'converted' then 1 else 0 end) as converted")
            ->groupBy('assigned_to')->toBase()->get();
        $users = User::whereIn('id', $byEmployee->pluck('assigned_to'))->get()->keyBy('id');
        $employees = $byEmployee->map(fn ($r) => (array) $r + ['user' => $users[$r->assigned_to] ?? null, 'pct' => $r->shared ? (int) round($r->called / $r->shared * 100) : 0])
            ->filter(fn ($r) => $r['user'])->sortByDesc('pct')->values();

        $byManager = $base()->join('lead_batches', 'leads.batch_id', '=', 'lead_batches.id')
            ->selectRaw("lead_batches.manager_id, count(*) as uploaded, sum(case when leads.assigned_to is not null then 1 else 0 end) as shared, sum(case when leads.assigned_to is null then 1 else 0 end) as unassigned, sum(case when leads.assigned_to is not null and leads.status <> 'new' then 1 else 0 end) as called, sum(case when leads.status in ('interested','follow_up') then 1 else 0 end) as interested, sum(case when leads.status = 'converted' then 1 else 0 end) as converted")
            ->groupBy('lead_batches.manager_id')->toBase()->get();
        $managerNames = User::whereIn('id', $byManager->pluck('manager_id')->filter())->pluck('name', 'id');

        $batches = LeadBatch::with('uploader', 'manager')
            ->where('department_id', $department->id)
            ->when($filters['manager'], fn ($q, $m) => $q->where('manager_id', $m))
            ->whereBetween('created_at', [$from, $to])
            ->withCount(['leads as pool_count' => fn ($q) => $q->whereNull('assigned_to'), 'leads as assigned_count' => fn ($q) => $q->whereNotNull('assigned_to')])
            ->latest()->get();
        $latest = LeadBatch::with('uploader')->where('department_id', $department->id)->latest()->first();
        $mandatoryNames = ['name', 'customer name', 'full name', 'mobile', 'mobile number', 'mobile no', 'phone', 'phone number', 'number', 'contact number'];
        $fieldCount = count($latest?->columns ?? []);
        $mandatory = collect($latest?->columns ?? [])->filter(fn ($c) => in_array(strtolower(trim($c)), $mandatoryNames, true))->count();

        // Tabs
        $tabs = ['overview', 'uploaded', 'shared', 'employees', 'interested', 'converted', 'unassigned', 'reports'];
        $tab = in_array($request->query('tab'), $tabs, true) ? $request->query('tab') : 'overview';
        $search = trim((string) $request->query('q'));
        $leadList = function (string $which, int $perPage = 20) use ($base, $search) {
            return $base()->with(['assignee', 'batch'])
                ->when($which === 'shared', fn ($q) => $q->whereNotNull('leads.assigned_to'))
                ->when($which === 'interested', fn ($q) => $q->whereIn('leads.status', ['interested', 'follow_up']))
                ->when($which === 'converted', fn ($q) => $q->where('leads.status', 'converted'))
                ->when($which === 'unassigned', fn ($q) => $q->whereNull('leads.assigned_to'))
                ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q->where('leads.name', 'like', "%{$search}%")->orWhere('leads.mobile', 'like', "%{$search}%")))
                ->latest('leads.updated_at')
                ->paginate($perPage)->withQueryString();
        };
        $list = in_array($tab, ['shared', 'interested', 'converted', 'unassigned'], true) ? $leadList($tab) : null;

        // Reports tab: day by day for the selected range (max 31 days)
        $daily = null;
        if ($tab === 'reports') {
            $start = $from->copy()->max($to->copy()->subDays(30)->startOfDay());
            $rangeDays = collect();
            for ($d = $start->copy(); $d->lte($to->copy()->min(today())); $d->addDay()) {
                $rangeDays->push($d->toDateString());
            }
            $up = $flowLeads()->whereBetween('created_at', [$start, $to])->selectRaw('date(created_at) as d, count(*) as c')->groupBy('d')->pluck('c', 'd');
            $act = fn (?array $s = null) => LeadActivity::whereIn('lead_id', $flowLeads()->select('id'))->whereBetween('created_at', [$start, $to])
                ->whereIn('type', ['status_change', 'follow_up'])->when($s, fn ($q) => $q->whereIn('to_status', $s))
                ->selectRaw('date(created_at) as d, count(distinct lead_id) as c')->groupBy('d')->pluck('c', 'd');
            [$c, $i, $v] = [$act(), $act(['interested']), $act(['converted'])];
            $daily = $rangeDays->reverse()->map(fn ($d) => ['date' => Carbon::parse($d), 'uploaded' => (int) ($up[$d] ?? 0), 'called' => (int) ($c[$d] ?? 0), 'interested' => (int) ($i[$d] ?? 0), 'converted' => (int) ($v[$d] ?? 0)])->values();

            if ($request->boolean('export')) {
                return response()->streamDownload(function () use ($daily) {
                    $out = fopen('php://output', 'w');
                    fwrite($out, "\xEF\xBB\xBF");
                    fputcsv($out, ['Date', 'Uploaded', 'Called', 'Interested', 'Converted'], ',', '"', '');
                    foreach ($daily as $r) {
                        fputcsv($out, [$r['date']->format('d-m-Y'), $r['uploaded'], $r['called'], $r['interested'], $r['converted']], ',', '"', '');
                    }
                    fclose($out);
                }, str($department->name)->slug().'-report.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
            }
        }

        return view('admin.departments.show', [
            'tab' => $tab,
            'list' => $list,
            'daily' => $daily,
            'sharedPreview' => $tab === 'overview' ? $leadList('shared', 8) : null,
            'unassignedPreview' => $tab === 'overview' ? $leadList('unassigned', 8) : null,
            'fieldInfo' => ['total' => $fieldCount, 'mandatory' => $mandatory, 'optional' => max(0, $fieldCount - $mandatory)],
            'department' => $department,
            'stats' => $stats,
            'change' => $this->change($stats, $previous),
            'flow' => $flow,
            'employees' => $employees,
            'managers' => $byManager->map(fn ($r) => (array) $r + ['name' => $managerNames[$r->manager_id] ?? 'No manager']),
            'batches' => $batches,
            'latest' => $latest,
            'from' => $from, 'to' => $to, 'filters' => $filters,
            ...$this->filterOptions(),
        ]);
    }

    /** Uploaded leads of a department (or all), filtered by upload date, manager and employee. */
    private function leads(?int $departmentId, ?Carbon $from, ?Carbon $to, array $filters): Builder
    {
        return Lead::query()
            ->whereNotNull('leads.batch_id')
            ->whereIn('leads.batch_id', LeadBatch::query()
                ->when($departmentId, fn ($q) => $q->where('department_id', $departmentId))
                ->when($filters['manager'] ?? null, fn ($q, $m) => $q->where('manager_id', $m))
                ->select('id'))
            ->when($from && $to, fn ($q) => $q->whereBetween('leads.created_at', [$from, $to]))
            ->when($filters['employee'] ?? null, fn ($q, $e) => $q->where('leads.assigned_to', $e));
    }

    private function stats(Builder $q): array
    {
        $row = (clone $q)->selectRaw("count(*) as uploaded, sum(case when leads.assigned_to is not null then 1 else 0 end) as shared, sum(case when leads.assigned_to is null then 1 else 0 end) as unassigned, sum(case when leads.assigned_to is not null and leads.status <> 'new' then 1 else 0 end) as called, sum(case when leads.status in ('interested','follow_up') then 1 else 0 end) as interested, sum(case when leads.status = 'converted' then 1 else 0 end) as converted")
            ->toBase()->first();

        return array_map('intval', (array) $row);
    }

    private function change(array $now, array $before): array
    {
        return collect($now)->map(fn ($v, $k) => $before[$k] ? (int) round(($v - $before[$k]) / $before[$k] * 100) : null)->all();
    }

    private function range(Request $request): array
    {
        $from = $request->date('from') ?? now()->startOfMonth();
        $to = $request->date('to') ?? now()->endOfMonth();

        return [Carbon::parse($from)->startOfDay(), Carbon::parse($to)->endOfDay()];
    }

    private function previous(Carbon $from, Carbon $to): array
    {
        $days = $from->diffInDays($to) + 1;

        return [$from->copy()->subDays((int) ceil($days)), $from->copy()->subSecond()];
    }

    private function filters(Request $request): array
    {
        return [
            'department' => $request->integer('department') ?: null,
            'manager' => $request->integer('manager') ?: null,
            'employee' => $request->integer('employee') ?: null,
        ];
    }

    private function filterOptions(): array
    {
        return [
            'allDepartments' => Department::forData()->get(),
            'allManagers' => User::whereIn('role', [Role::Manager->value, Role::TeamLeader->value])->orderBy('name')->get(),
            'allEmployees' => User::whereIn('role', [Role::Employee->value, Role::TeamLeader->value])->orderBy('name')->get(),
        ];
    }
}
