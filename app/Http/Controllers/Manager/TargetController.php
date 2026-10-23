<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Admin\EmployeeController;
use App\Http\Controllers\Controller;
use App\Services\AuditLogger;
use App\Services\WorkScoreService;
use Illuminate\Http\Request;

/** Manager sets daily lead-creation and interested targets for their own team. */
class TargetController extends Controller
{
    public function index(Request $request, WorkScoreService $score)
    {
        $team = $request->user()->teamMembers()->orderBy('name')->get()
            ->map(fn ($u) => ['user' => $u, 'targets' => $score->targets($u)]);

        return view('manager.targets', ['team' => $team, 'defaults' => config('portal.work_score.default_targets')]);
    }

    public function update(Request $request, AuditLogger $audit)
    {
        $teamIds = $request->user()->teamMembers()->pluck('id')->all();

        $data = $request->validate([
            'targets' => ['required', 'array'],
            'targets.*.leads' => ['required', 'integer', 'min:0', 'max:1000'],
            'targets.*.interested' => ['required', 'integer', 'min:0', 'max:1000'],
        ]);

        $updated = 0;
        foreach ($data['targets'] as $userId => $values) {
            if (! in_array((int) $userId, $teamIds, true)) {
                continue; // only own team
            }
            $member = $request->user()->teamMembers()->find($userId);
            EmployeeController::saveTargets($member, $values);
            $updated++;
        }

        $audit->log('targets.update', "Updated daily targets for {$updated} team member(s)");

        return back()->with('success', "Daily targets saved for {$updated} employee(s).");
    }
}
