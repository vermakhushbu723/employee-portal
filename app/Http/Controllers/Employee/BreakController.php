<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\BreakSession;
use App\Services\AttendanceService;
use App\Services\BreakService;
use Illuminate\Http\Request;

class BreakController extends Controller
{
    public function __construct(private BreakService $breaks) {}

    public function index(Request $request, AttendanceService $attendance)
    {
        $user = $request->user();

        return view('employee.breaks', [
            'timer' => $attendance->timer($user),
            'history' => BreakSession::where('user_id', $user->id)->latest('started_at')->limit(20)->get(),
        ]);
    }

    public function start(Request $request)
    {
        $this->breaks->start($request->user());

        return back()->with('success', 'Break started. Your work timer is paused.');
    }

    public function end(Request $request)
    {
        $break = $this->breaks->end($request->user());

        return back()->with('success', "Break ended after {$break->duration_minutes} min. Your work timer has resumed.");
    }
}
