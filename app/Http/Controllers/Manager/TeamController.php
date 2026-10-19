<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Services\TeamMonitorService;
use Illuminate\Http\Request;

/** My Team + Live Employee Monitoring. */
class TeamController extends Controller
{
    public function __invoke(Request $request, TeamMonitorService $monitor)
    {
        return view('manager.team', ['team' => $monitor->rows($request->user())]);
    }
}
