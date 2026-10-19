<?php

namespace App\Http\Controllers\Admin;

use App\Enums\LeaveStatus;
use App\Http\Controllers\Controller;
use App\Models\LeaveRequest;
use App\Services\LeaveService;
use Illuminate\Http\Request;

class LeaveController extends Controller
{
    public function __construct(private LeaveService $leaves) {}

    public function index(Request $request)
    {
        $status = LeaveStatus::tryFrom((string) $request->query('status', 'pending'));

        return view('admin.leaves.index', [
            'status' => $status,
            'leaves' => LeaveRequest::with('user', 'approver')
                ->when($status, fn ($q) => $q->where('status', $status))
                ->latest()
                ->paginate(15)
                ->withQueryString(),
        ]);
    }

    public function approve(Request $request, LeaveRequest $leave)
    {
        $this->leaves->approve($leave, $request->user());

        return back()->with('success', 'Leave approved.');
    }

    public function reject(Request $request, LeaveRequest $leave)
    {
        $data = $request->validate(['rejection_reason' => ['required', 'string', 'max:300']]);
        $this->leaves->reject($leave, $request->user(), $data['rejection_reason']);

        return back()->with('success', 'Leave rejected.');
    }
}
