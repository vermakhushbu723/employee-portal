<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Http\Requests\AssignWorkRequest;
use App\Services\TaskService;
use Illuminate\Http\Request;

class AssignWorkController extends Controller
{
    public function create(Request $request)
    {
        return view('manager.assign-work', [
            'team' => $request->user()->teamMembers()->with('employee')->orderBy('name')->get(),
            'categories' => config('portal.task_categories'),
        ]);
    }

    public function store(AssignWorkRequest $request, TaskService $tasks)
    {
        $task = $tasks->assign($request->user(), $request->taskData(), $request->validated('assignees'));

        return redirect()->route('manager.assign-work.create')
            ->with('success', "\"{$task->title}\" assigned to ".count($request->validated('assignees')).' employee(s).');
    }
}
