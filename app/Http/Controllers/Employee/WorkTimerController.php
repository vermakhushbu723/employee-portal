<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Services\AttendanceService;
use Illuminate\Http\Request;

class WorkTimerController extends Controller
{
    public function __construct(private AttendanceService $attendance) {}

    public function index(Request $request)
    {
        return view('employee.work-timer', ['timer' => $this->attendance->timer($request->user())]);
    }

    /** Polled by public/js/app.js to keep the live timer in sync with the server. */
    public function status(Request $request)
    {
        return response()->json($this->attendance->timer($request->user()));
    }
}
