<?php

namespace App\Http\Controllers;

use App\Models\AttendanceSession;
use App\Services\LogoutReviewService;
use Illuminate\Http\Request;

/** Logout Requests (admin + manager): verify employee exception checkouts. */
class LogoutRequestController extends Controller
{
    public function __construct(private LogoutReviewService $reviews) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $tab = in_array($request->query('tab'), ['pending', 'approved', 'rejected', 'all'], true) ? $request->query('tab') : 'pending';
        $base = fn () => $this->reviews->visible($user);

        return view('logout-requests.index', [
            'tab' => $tab,
            'counts' => [
                'pending' => $base()->whereIn('review_status', ['pending', 'verified'])->count(),
                'approved' => $base()->where('review_status', 'approved')->count(),
                'rejected' => $base()->where('review_status', 'rejected')->count(),
                'all' => $base()->count(),
            ],
            'items' => $base()->with('user.employee.department', 'reviewer', 'verifier')
                ->when($tab === 'pending', fn ($q) => $q->whereIn('review_status', ['pending', 'verified']))
                ->when(in_array($tab, ['approved', 'rejected'], true), fn ($q) => $q->where('review_status', $tab))
                ->latest('logout_requested_at')->latest('check_out_at')
                ->paginate(20)->withQueryString(),
            'isAdmin' => $this->reviews->isAdmin($user),
            'portal' => $user->role->portal(),
        ]);
    }

    /** Admin 1-step approve, or manager step 1 (verify) / step 2 (confirm with password). */
    public function approve(Request $request, AttendanceSession $session)
    {
        $user = $request->user();

        if ($this->reviews->isAdmin($user)) {
            $note = $request->validate(['note' => ['nullable', 'string', 'max:500']])['note'] ?? null;
            $this->reviews->approveByAdmin($session->load('user'), $user, $note);

            return back()->with('success', "Logout request of {$session->user->name} accepted. The employee sees the confirmation now.");
        }

        if ($request->input('step') === '2') {
            $data = $request->validate(['password' => ['required', 'string']], ['password.required' => 'Enter your password to confirm step 2.']);
            $this->reviews->confirmByManager($session->load('user'), $user, $data['password']);

            return back()->with('success', "Step 2 done — logout request of {$session->user->name} accepted.");
        }

        $data = $request->validate([
            'checked_reason' => ['accepted'],
            'checked_work' => ['accepted'],
            'note' => ['required', 'string', 'min:3', 'max:500'],
        ], [
            'checked_reason.accepted' => 'Tick that you checked the reason.',
            'checked_work.accepted' => "Tick that you checked today's work.",
            'note.required' => 'Write a short verification note.',
        ]);
        $this->reviews->verifyByManager($session->load('user'), $user, $data['note']);

        return back()->with('success', 'Step 1 verified. Now confirm step 2 with your password.');
    }

    public function reject(Request $request, AttendanceSession $session)
    {
        $note = $request->validate(['note' => ['required', 'string', 'min:3', 'max:500']], ['note.required' => 'Write why the logout is not accepted.'])['note'];
        $this->reviews->reject($session->load('user'), $request->user(), $note);

        return back()->with('success', "Logout request rejected — {$session->user->name}'s day is open again.");
    }
}
