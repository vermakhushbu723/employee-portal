<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Services\AttendanceService;
use Illuminate\Http\Request;

/** Browser heartbeat for the navbar "active time" timer. */
class ActivityController extends Controller
{
    public function __invoke(Request $request, AttendanceService $attendance)
    {
        return response()->json($attendance->heartbeat($request->user(), $request->boolean('active')));
    }
}
