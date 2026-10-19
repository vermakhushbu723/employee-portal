<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Services\PerformanceService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class PerformanceController extends Controller
{
    /** Screen 13 — My Performance. */
    public function __invoke(Request $request, PerformanceService $performance)
    {
        $months = collect(range(0, 5))->map(fn ($i) => now()->startOfMonth()->subMonths($i));

        $selected = $months->first(fn ($m) => $m->format('Y-m') === $request->query('month')) ?? $months->first();

        return view('employee.performance', [
            'months' => $months,
            'month' => $selected,
            'report' => $performance->monthly($request->user(), Carbon::parse($selected)),
            'today' => $performance->today($request->user()),
        ]);
    }
}
