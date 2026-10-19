<?php

namespace App\Http\Controllers\Employee;

use App\Enums\TaskStatus;
use App\Http\Controllers\Controller;
use App\Models\TaskAssignment;
use App\Services\TaskService;
use Illuminate\Http\Request;

class TaskController extends Controller
{
    public function __construct(private TaskService $tasks) {}

    /** Screen 6 — My Assigned Work (today / this week / all). */
    public function index(Request $request)
    {
        $user = $request->user();
        $tab = in_array($request->query('tab'), ['today', 'week', 'all'], true) ? $request->query('tab') : 'today';

        $scopes = [
            'today' => fn ($q) => $q->whereDate('assigned_date', today()),
            'week' => fn ($q) => $q->whereBetween('assigned_date', [now()->startOfWeek()->toDateString(), now()->endOfWeek()->toDateString()]),
            'all' => fn ($q) => $q,
        ];

        $tabCounts = collect($scopes)->map(fn ($scope) => $scope($user->taskAssignments())->count());

        $assignments = $scopes[$tab]($user->taskAssignments())
            ->with('task')
            ->orderByDesc('assigned_date')
            ->get();

        return view('employee.tasks.index', [
            'tab' => $tab,
            'tabCounts' => $tabCounts,
            'assignments' => $assignments,
            'summary' => [
                'total' => $assignments->count(),
                'in_progress' => $assignments->where('status', TaskStatus::InProgress)->count(),
                'pending' => $assignments->where('status', TaskStatus::Assigned)->count(),
                'overdue' => $assignments->filter->isOverdue()->count(),
            ],
        ]);
    }

    /** Screen 7 — task details, progress and history. */
    public function show(Request $request, int $assignment)
    {
        $assignment = $this->find($request, $assignment)->load('task.assigner', 'activities');

        return view('employee.tasks.show', compact('assignment'));
    }

    public function start(Request $request, int $assignment)
    {
        $this->tasks->start($this->find($request, $assignment), $request->user());

        return back()->with('success', 'Task started. Log your progress as you work.');
    }

    public function progress(Request $request, int $assignment)
    {
        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:1000'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $this->tasks->addProgress($this->find($request, $assignment), $request->user(), $data['quantity'], $data['note'] ?? null);

        return back()->with('success', "Progress updated (+{$data['quantity']}).");
    }

    public function submit(Request $request, int $assignment)
    {
        $data = $request->validate(['note' => ['nullable', 'string', 'max:500']]);

        $this->tasks->submit($this->find($request, $assignment), $request->user(), $data['note'] ?? null);

        return back()->with('success', 'Work submitted for verification.');
    }

    /** Employees can only reach their own assignments. */
    private function find(Request $request, int $id): TaskAssignment
    {
        return $request->user()->taskAssignments()->with('task')->findOrFail($id);
    }
}
