<?php

namespace App\Http\Controllers\Employee;

use App\Enums\LeaveStatus;
use App\Enums\LeaveType;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreLeaveRequest;
use App\Services\LeaveService;
use Illuminate\Http\Request;

class LeaveController extends Controller
{
    /** Screen 11 — My Leave History (+ apply leave). */
    public function index(Request $request)
    {
        $user = $request->user();
        $tab = LeaveStatus::tryFrom((string) $request->query('tab'));

        $counts = $user->leaveRequests()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return view('employee.leaves.index', [
            'tab' => $tab,
            'counts' => $counts,
            'leaves' => $user->leaveRequests()
                ->when($tab, fn ($q) => $q->where('status', $tab))
                ->latest('from_date')
                ->paginate(10)
                ->withQueryString(),
            'balances' => $user->leaveBalances()->where('year', now()->year)->get(),
            'types' => LeaveType::cases(),
        ]);
    }

    public function store(StoreLeaveRequest $request, LeaveService $leaves)
    {
        $leaves->apply($request->user(), $request->validated());

        return redirect()->route('employee.leaves.index')->with('success', 'Leave application submitted for approval.');
    }

    /** Screen 12 — Leave Charges & Summary. */
    public function charges(Request $request)
    {
        $user = $request->user();
        $year = (int) $request->query('year', now()->year);
        $year = ($year >= 2000 && $year <= now()->year) ? $year : now()->year;

        $balances = $user->leaveBalances()->where('year', $year)->get()->keyBy(fn ($b) => $b->leave_type->value);

        $deductions = $user->payrollRecords()
            ->where('type', 'deduction')
            ->whereYear('record_date', $year)
            ->latest('record_date')
            ->get();

        return view('employee.leaves.charges', [
            'year' => $year,
            'balances' => $balances,
            'totals' => [
                'total' => $balances->sum('total'),
                'used' => $balances->sum('used'),
                'available' => $balances->sum(fn ($b) => $b->available()),
                'charges' => $deductions->sum('amount'),
            ],
            'deductions' => $deductions,
            'unpaidDays' => $user->leaveRequests()->where('status', LeaveStatus::Approved)->where('is_paid', false)->whereYear('from_date', $year)->sum('days'),
        ]);
    }
}
