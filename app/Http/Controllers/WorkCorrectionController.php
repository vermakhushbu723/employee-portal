<?php

namespace App\Http\Controllers;

use App\Models\AttendanceSession;
use App\Models\User;
use App\Models\WorkCorrection;
use App\Services\WorkCorrectionService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Correction Requests (admin + manager).
 * Admin: every employee. Manager: own team, plus anything they must review.
 */
class WorkCorrectionController extends Controller
{
    public function __construct(private WorkCorrectionService $corrections) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $tab = in_array($request->query('tab'), ['pending', 'approved', 'rejected', 'all', 'mine'], true) ? $request->query('tab') : 'pending';

        $base = fn () => $this->visible($user);
        $counts = [
            'pending' => $base()->where('status', 'pending')->count(),
            'mine' => $base()->where('status', 'pending')->where('reviewer_id', $user->id)->count(),
            'approved' => $base()->where('status', 'approved')->count(),
            'rejected' => $base()->where('status', 'rejected')->count(),
            'all' => $base()->count(),
        ];

        $items = $base()->with('user.employee.department', 'submitter', 'reviewer', 'reviewedBy', 'session')
            ->when($tab === 'mine', fn ($q) => $q->where('status', 'pending')->where('reviewer_id', $user->id))
            ->when(in_array($tab, ['pending', 'approved', 'rejected'], true), fn ($q) => $q->where('status', $tab))
            ->latest()->paginate(20)->withQueryString();

        return view('corrections.index', ['items' => $items, 'tab' => $tab, 'counts' => $counts, 'portal' => $user->role->portal()]);
    }

    public function create(Request $request, AttendanceSession $session)
    {
        $session->load('user.employee.department', 'breaks');
        abort_unless($this->corrections->canCorrect($request->user(), $session->user), 403);

        return view('corrections.create', [
            'session' => $session,
            'original' => $this->corrections->original($session),
            'reviewers' => $this->corrections->reviewers($request->user(), $session->user),
            'pending' => WorkCorrection::where('session_id', $session->id)->where('status', 'pending')->first(),
            'history' => WorkCorrection::with('submitter', 'reviewedBy')->where('session_id', $session->id)->latest()->get(),
            'portal' => $request->user()->role->portal(),
        ]);
    }

    public function store(Request $request, AttendanceSession $session)
    {
        $data = $request->validate([
            'calls' => ['required', 'integer', 'min:0', 'max:5000'],
            'interested' => ['required', 'integer', 'min:0', 'max:5000'],
            'leads_created' => ['required', 'integer', 'min:0', 'max:5000'],
            'work_hours' => ['required', 'integer', 'min:0', 'max:16'],
            'work_minutes' => ['required', 'integer', 'min:0', 'max:59'],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
            'details' => ['required', 'string', 'min:5', 'max:2000'],
            'reviewer_id' => ['required', 'integer'],
        ], [
            'reason.required' => 'Write the reason for the mistake.',
            'details.required' => 'Write the correct information / details.',
            'reviewer_id.required' => 'Choose who should review this correction.',
        ]);

        $corrected = [
            'calls' => $data['calls'],
            'interested' => $data['interested'],
            'leads_created' => $data['leads_created'],
            'active_seconds' => $data['work_hours'] * 3600 + $data['work_minutes'] * 60,
        ];

        [$correction, $mailed] = $this->corrections->submit($request->user(), $session->load('user'), $corrected, $data['reason'], $data['details'], (int) $data['reviewer_id']);

        $portal = $request->user()->role->portal();

        return redirect()->route($portal.'.corrections.show', $correction)->with('success',
            "Correction submitted for review to {$correction->reviewer->name}."
            .($mailed ? ' Review e-mail sent.' : ' (Review e-mail could not be sent — the reviewer will still see it under Correction Requests.)'));
    }

    public function show(Request $request, WorkCorrection $correction)
    {
        abort_unless($this->visible($request->user())->whereKey($correction->id)->exists(), 403);
        $correction->load('user.employee.department', 'submitter', 'reviewer', 'reviewedBy', 'session');

        return view('corrections.show', [
            'c' => $correction,
            'canReview' => $this->corrections->canReview($request->user(), $correction),
            'history' => WorkCorrection::with('submitter', 'reviewedBy')->where('session_id', $correction->session_id)->whereKeyNot($correction->id)->latest()->get(),
            'portal' => $request->user()->role->portal(),
        ]);
    }

    public function approve(Request $request, WorkCorrection $correction)
    {
        $note = $request->validate(['note' => ['nullable', 'string', 'max:500']])['note'] ?? null;
        $this->corrections->approve($correction->load('user', 'session'), $request->user(), $note);
        $s = $correction->session->fresh();

        return back()->with('success', "Correction approved. {$correction->user->name}'s day is now {$s->work_score}% ({$s->grade}) — working time, work and attendance were recalculated.");
    }

    public function reject(Request $request, WorkCorrection $correction)
    {
        $note = $request->validate(['note' => ['required', 'string', 'min:3', 'max:500']], ['note.required' => 'Write why you reject it.'])['note'];
        $this->corrections->reject($correction->load('user'), $request->user(), $note);

        return back()->with('success', 'Correction rejected. The original data stays.');
    }

    /** Corrections this user may see. */
    private function visible(User $user): Builder
    {
        $q = WorkCorrection::query();
        if ($user->role->portal() === 'admin') {
            return $q;
        }

        $team = $user->teamMembers()->pluck('id');

        return $q->where(fn ($q) => $q->whereIn('user_id', $team)->orWhere('reviewer_id', $user->id)->orWhere('submitted_by', $user->id));
    }
}
