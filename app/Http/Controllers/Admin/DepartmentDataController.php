<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\AttendanceSession;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\LeadBatch;
use App\Models\User;
use App\Services\LiveStatusService;
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

        // Uploads made before departments existed (no department selected)
        $noDept = $this->stats(Lead::query()
            ->whereIn('leads.batch_id', LeadBatch::whereNull('department_id')->select('id'))
            ->whereBetween('leads.created_at', [$from, $to]));

        return view('admin.departments.index', [
            'noDept' => $noDept,
            'departments' => $departments,
            'stats' => $stats,
            'change' => $this->change($stats, $previous),
            'from' => $from, 'to' => $to, 'filters' => $filters,
            ...$this->filterOptions(),
        ]);
    }

    /** Create a department for data upload + tracking (appears in the sidebar, upload form and this page). */
    public function store(Request $request, \App\Services\AuditLogger $audit)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80', 'unique:departments,name'],
            'code' => ['nullable', 'string', 'max:20', 'alpha_dash', 'unique:departments,code'],
            'description' => ['nullable', 'string', 'max:255'],
        ], ['name.unique' => 'A department with this name already exists.']);

        $department = Department::create($data + ['status' => 'active', 'for_data' => true]);
        $audit->log('department.create', "Created department {$department->name}", ['department_id' => $department->id]);

        return redirect()->route('admin.departments.show', $department)->with('success', "Department \"{$department->name}\" created. Select it when uploading data to track its daily work.");
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
        $tabs = ['team', 'overview', 'uploaded', 'shared', 'employees', 'interested', 'converted', 'unassigned', 'reports'];
        // Opening a department shows the full team list first
        $tab = in_array($request->query('tab'), $tabs, true) ? $request->query('tab') : 'team';
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

        $team = $tab === 'team' ? $this->teamPerformance($department, $from, $to, $filters) : null;
        if ($team && $request->boolean('export')) {
            return response()->streamDownload(function () use ($team) {
                $out = fopen('php://output', 'w');
                fwrite($out, "\xEF\xBB\xBF");
                fputcsv($out, ['Employee', 'ID', 'Total Records', 'Calls Done', 'Calls Pending', 'Interested', 'Interested %', 'Leads Created', 'Leads Created %', 'Avg Work Time / Day', 'Last Login', 'Last Active Date', 'Status'], ',', '"', '');
                foreach ($team['rows'] as $r) {
                    fputcsv($out, [$r['user']->name, $r['user']->employee_code, $r['total'], $r['done'], $r['pending'], $r['interested'], $r['interested_pct'], $r['created'], $r['created_pct'],
                        \App\Support\Duration::hm($r['work_avg']), $r['login']?->format('h:i A'), $r['last_date']?->format('d-m-Y'), $r['label']], ',', '"', '');
                }
                fclose($out);
            }, str($department->name)->slug().'-team-work.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
        }

        return view('admin.departments.show', [
            'team' => $team,
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

    /**
     * "My Team Work Report" for a department: every employee of the department (and anyone who got
     * its data) with records, calls done / pending, interested and leads created (count + % of calls),
     * average working time, last login, last active day and live status.
     */
    private function teamPerformance(Department $department, Carbon $from, Carbon $to, array $filters): array
    {
        $agg = $this->leads($department->id, $from, $to, $filters)->whereNotNull('leads.assigned_to')
            ->selectRaw("leads.assigned_to, count(*) as total, sum(case when leads.status <> 'new' then 1 else 0 end) as done, sum(case when leads.status in ('interested','follow_up','converted') then 1 else 0 end) as interested")
            ->groupBy('leads.assigned_to')->toBase()->get()->keyBy('assigned_to');

        $ids = Employee::where('department_id', $department->id)->pluck('user_id')->merge($agg->keys())->unique();
        $users = User::whereIn('id', $ids)
            ->whereIn('role', [Role::Employee->value, Role::TeamLeader->value])
            ->when($filters['employee'], fn ($q, $e) => $q->whereKey($e))
            ->when($filters['manager'], fn ($q, $m) => $q->whereHas('employee', fn ($e) => $e->where('manager_id', $m)))
            ->with('employee.department')->orderBy('name')->get();
        $userIds = $users->pluck('id');

        $created = Lead::whereIn('created_by', $userIds)->whereBetween('created_at', [$from, $to])
            ->selectRaw('created_by, count(*) as c')->groupBy('created_by')->pluck('c', 'created_by');
        $sessions = AttendanceSession::whereIn('user_id', $userIds)
            ->whereDate('work_date', '>=', $from->toDateString())->whereDate('work_date', '<=', $to->toDateString())
            ->orderBy('work_date')->get()->groupBy('user_id');
        $live = app(LiveStatusService::class)->rows($users)->keyBy(fn ($r) => $r['user']->id);

        $rows = $users->map(function (User $u) use ($agg, $created, $sessions, $live) {
            $a = $agg->get($u->id);
            $done = (int) ($a->done ?? 0);
            $interested = (int) ($a->interested ?? 0);
            $made = (int) ($created[$u->id] ?? 0);
            $days = $sessions->get($u->id, collect());
            $last = $days->last();

            return [
                'user' => $u,
                'total' => (int) ($a->total ?? 0),
                'done' => $done,
                'pending' => (int) ($a->total ?? 0) - $done,
                'interested' => $interested,
                'interested_pct' => $done ? min(100, (int) round($interested / $done * 100)) : 0,
                'created' => $made,
                'created_pct' => $done ? min(100, (int) round($made / $done * 100)) : 0,
                'work_avg' => $days->count() ? (int) round($days->sum('active_seconds') / $days->count()) : 0,
                'days' => $days->count(),
                'login' => $last?->check_in_at,
                'last_date' => $last?->work_date,
                'state' => $live[$u->id]['state'] ?? 'offline',
                'label' => $live[$u->id]['label'] ?? 'Offline',
                'tone' => $live[$u->id]['tone'] ?? 'danger',
                'session_today' => $live[$u->id]['session'] ?? null,
            ];
        })->sortByDesc('done')->values();

        $worked = $rows->filter(fn ($r) => $r['days'] > 0);
        $calls = $rows->sum('done');

        return [
            'rows' => $rows,
            'kpis' => [
                'employees' => $rows->count(),
                'calls' => $calls,
                'interested' => $rows->sum('interested'),
                'created' => $rows->sum('created'),
                'work_avg' => $worked->count() ? (int) round($worked->avg('work_avg')) : 0,
                'conversion' => $calls ? round($rows->sum('interested') / $calls * 100, 1) : 0,
            ],
        ];
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
        // Quick ranges: This Week / This Month (default) / custom dates
        if ($request->query('period') === 'week') {
            return [now()->startOfWeek()->startOfDay(), now()->endOfWeek()->endOfDay()];
        }
        if ($request->query('period') === 'month') {
            return [now()->startOfMonth()->startOfDay(), now()->endOfMonth()->endOfDay()];
        }

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
