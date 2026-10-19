<?php

namespace App\Http\Controllers\Admin;

use App\Enums\LeaveStatus;
use App\Http\Controllers\Controller;
use App\Models\LateCheckinRequest;
use App\Services\AttendanceService;
use Illuminate\Http\Request;

/** Approve / reject late check-in requests (after the 09:55–10:15 window). */
class LateCheckinController extends Controller
{
    public function __construct(private AttendanceService $attendance) {}

    public function index(Request $request)
    {
        $status = LeaveStatus::tryFrom((string) $request->query('status', 'pending'));

        return view('admin.late-requests.index', [
            'status' => $status,
            'requests' => LateCheckinRequest::with('user.employee.shift', 'approver')
                ->when($status, fn ($q) => $q->where('status', $status))
                ->latest()
                ->paginate(20)
                ->withQueryString(),
            'pendingCount' => LateCheckinRequest::where('status', LeaveStatus::Pending)->count(),
        ]);
    }

    public function approve(Request $request, LateCheckinRequest $lateRequest)
    {
        $this->attendance->decideLateRequest($lateRequest, $request->user(), true);

        return back()->with('success', "Approved — {$lateRequest->user->name} can check in now.");
    }

    public function reject(Request $request, LateCheckinRequest $lateRequest)
    {
        $data = $request->validate(['rejection_reason' => ['nullable', 'string', 'max:300']]);
        $this->attendance->decideLateRequest($lateRequest, $request->user(), false, $data['rejection_reason'] ?? null);

        return back()->with('success', "Rejected late check-in of {$lateRequest->user->name}.");
    }
}
