<?php

namespace App\Http\Controllers;

use App\Enums\LeadStatus;
use App\Models\Department;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\User;
use App\Services\LeadReviewService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * Daily Work Report → Review: an employee's (or a whole department's) lead work, which admin /
 * manager can correct inline — purpose of connect, status, next meeting / action, remark.
 */
class LeadReviewController extends Controller
{
    public function __construct(private LeadReviewService $review) {}

    public function index(Request $request)
    {
        $reviewer = $request->user();
        $employee = $request->integer('user') ? User::find($request->integer('user')) : null;
        $date = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $request->query('date')) ? Carbon::parse($request->query('date')) : null;
        $dept = $request->integer('department') ?: null;
        $department = $dept ? Department::find($dept) : null;
        $status = LeadStatus::tryFrom((string) $request->query('status'));
        $city = trim((string) $request->query('city'));
        $source = trim((string) $request->query('source'));
        $q = trim((string) $request->query('q'));

        $base = fn () => $this->review->scope($reviewer)
            ->when($employee, fn ($x) => $x->where(fn ($x) => $x->where('assigned_to', $employee->id)->orWhere('created_by', $employee->id)))
            // A day: leads worked on (or created) that day
            ->when($date, fn ($x) => $x->where(fn ($x) => $x
                ->whereIn('id', LeadActivity::whereDate('created_at', $date)->when($employee, fn ($a) => $a->where('user_id', $employee->id))->select('lead_id'))
                ->orWhereDate('created_at', $date)))
            ->when($dept, fn ($x) => $x->where('department_id', $dept))
            ->when($city !== '', fn ($x) => $x->where('city', $city))
            ->when($source !== '', fn ($x) => $x->where('source', $source))
            ->when($q !== '', fn ($x) => $x->where(fn ($x) => $x->where('name', 'like', "%{$q}%")->orWhere('mobile', 'like', "%{$q}%")->orWhere('city', 'like', "%{$q}%")));

        $counts = $base()->selectRaw('status, count(*) as n')->groupBy('status')->toBase()->pluck('n', 'status');

        $leads = $base()->when($status, fn ($x) => $x->where('status', $status->value))
            ->with(['department', 'assignee', 'corrector', 'communications' => fn ($c) => $c->latest()->limit(20)])
            ->withSum(['communications as talk_seconds' => fn ($c) => $c->where('channel', 'call')], 'duration_seconds')
            ->withCount(['communications as voice_notes' => fn ($c) => $c->where('channel', 'voice_note')])
            ->latest('updated_at')
            ->paginate(10)->withQueryString();

        $hr = (bool) $department?->isHr();

        return view('review.index', [
            'leads' => $leads,
            'counts' => $counts,
            'total' => $counts->sum(),
            'employee' => $employee,
            'date' => $date,
            'department' => $department,
            'hr' => $hr,
            'status' => $status,
            'filters' => compact('city', 'source', 'q'),
            'departments' => Department::forData()->get(),
            'cities' => $this->review->scope($reviewer)->whereNotNull('city')->distinct()->orderBy('city')->limit(100)->pluck('city'),
            'sources' => $this->review->scope($reviewer)->whereNotNull('source')->distinct()->orderBy('source')->limit(50)->pluck('source'),
            'portal' => $reviewer->role->portal(),
        ]);
    }

    /** Inline correction of one lead (JSON). */
    public function update(Request $request, Lead $lead)
    {
        $data = $request->validate([
            'purpose' => ['sometimes', 'nullable', 'string', 'max:150'],
            'status' => ['sometimes', Rule::enum(LeadStatus::class)],
            'next_action_at' => ['sometimes', 'nullable', 'date'],
            'next_action_type' => ['sometimes', 'nullable', 'in:call,meeting,visit,document'],
            'remarks' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $result = $this->review->update($lead, $request->user(), $data);

        return response()->json([
            'ok' => true,
            'message' => $result['changed'] ? "{$lead->name}: ".implode('; ', $result['changed']).($result['score'] ? ' — employee score updated.' : '') : 'Nothing changed.',
            'score' => $result['score'],
        ]);
    }
}
