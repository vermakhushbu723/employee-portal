<?php

namespace App\Http\Controllers\Manager;

use App\Enums\LeaveStatus;
use App\Http\Controllers\Controller;
use App\Models\AttendanceSession;
use App\Models\LeaveRequest;
use App\Services\LeaveService;
use Illuminate\Http\Request;

/** Leave requests and exception checkouts from the manager's own team only. */
class ApprovalController extends Controller
{
    public function __construct(private LeaveService $leaves) {}

    public function index(Request $request)
    {
        $teamIds = $request->user()->teamMembers()->pluck('id');

        return view('manager.approvals', [
            'leaves' => LeaveRequest::with('user')->whereIn('user_id', $teamIds)->where('status', LeaveStatus::Pending)->oldest('from_date')->get(),
            'checkouts' => AttendanceSession::with('user')->whereIn('user_id', $teamIds)->whereIn('review_status', ['pending', 'verified'])->latest('work_date')->get(),
        ]);
    }

    public function approveLeave(Request $request, int $leave)
    {
        $this->leaves->approve($this->teamLeave($request, $leave), $request->user());

        return back()->with('success', 'Leave approved.');
    }

    public function rejectLeave(Request $request, int $leave)
    {
        $data = $request->validate(['rejection_reason' => ['required', 'string', 'max:300']]);
        $this->leaves->reject($this->teamLeave($request, $leave), $request->user(), $data['rejection_reason']);

        return back()->with('success', 'Leave rejected.');
    }

    private function teamLeave(Request $request, int $id): LeaveRequest
    {
        return LeaveRequest::whereIn('user_id', $request->user()->teamMembers()->pluck('id'))->findOrFail($id);
    }
}
