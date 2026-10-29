<?php

namespace App\Http\Controllers\Employee;

use App\Enums\LeadStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreLeadRequest;
use App\Models\Lead;
use App\Services\LeadService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LeadController extends Controller
{
    public function __construct(private LeadService $leads) {}

    /** Screen 9 — My Leads. */
    public function index(Request $request)
    {
        $user = $request->user();
        // Tabs: All Leads, New Data, Today Task, then the working statuses. A lead moves tab as soon as its status changes.
        $statusTabs = ['interested', 'not_interested', 'call_not_pick', 'call_not_answer', 'callback', 'meeting', 'follow_up', 'converted'];
        $tabs = ['all', 'new', 'today', ...$statusTabs, ...array_map(fn ($s) => $s->value, LeadStatus::cases())];
        $tab = in_array($request->query('tab'), $tabs, true) ? $request->query('tab') : 'all';
        $search = trim((string) $request->query('q'));
        $city = trim((string) $request->query('city'));
        $source = trim((string) $request->query('source'));
        // "Start Calling" on a work card opens only that shared file's leads
        $batch = $request->integer('batch') ? \App\Models\LeadBatch::find($request->integer('batch')) : null;

        $mine = fn () => Lead::ownedBy($user);
        $counts = $mine()->selectRaw('status, count(*) as total')->groupBy('status')->toBase()->pluck('total', 'status');

        // Today Task = new data not called yet + follow-ups / callbacks / meetings due today (or overdue)
        $todayTask = fn ($q) => $q->where(fn ($q) => $q
            ->where('status', LeadStatus::New->value)
            ->orWhere(fn ($q) => $q->whereIn('status', [LeadStatus::FollowUp->value, LeadStatus::Callback->value])->whereDate('follow_up_date', '<=', today()))
            ->orWhere(fn ($q) => $q->whereNotNull('next_action_at')->whereDate('next_action_at', '<=', today())->whereNotIn('status', [LeadStatus::Converted->value, LeadStatus::NotInterested->value, LeadStatus::Rejected->value])));

        $leads = $mine()
            ->with(['corrector', 'department', 'meetings' => fn ($q) => $q->whereIn('status', ['pending', 'upcoming', 'live'])->orderBy('scheduled_at')])
            ->withSum(['communications as talk_seconds' => fn ($c) => $c->where('channel', 'call')], 'duration_seconds')
            ->when($batch, fn ($q) => $q->where('batch_id', $batch->id))
            ->when($tab === 'today', $todayTask)
            ->when(! in_array($tab, ['all', 'today'], true), fn ($q) => $q->where('status', $tab))
            ->when($city !== '', fn ($q) => $q->where('city', $city))
            ->when($source !== '', fn ($q) => $q->where('source', $source))
            ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('mobile', 'like', "%{$search}%")
                ->orWhere('city', 'like', "%{$search}%")))
            // Newly shared data first, then latest
            ->orderByRaw('case when status = ? and assigned_at is not null then 0 else 1 end', [LeadStatus::New->value])
            ->orderByDesc('assigned_at')
            ->latest('updated_at')
            ->paginate(15)
            ->withQueryString();

        // Extra uploaded columns ("Add Field") — e.g. Age, Experience, Civil Score
        $standard = ['name', 'mobile', 'mobile number', 'phone', 'phone number', 'number', 'city', 'state', 'email', 'source', 'interest', 'remarks', 'alternate number', 'customer name', 'full name'];
        $extraFields = \App\Models\LeadBatch::whereIn('id', $mine()->whereNotNull('batch_id')->select('batch_id'))->pluck('columns')
            ->flatten()->filter()->map(fn ($c) => trim($c))->unique(fn ($c) => strtolower($c))
            ->reject(fn ($c) => in_array(strtolower($c), $standard, true))->values();

        return view('employee.leads.index', [
            'leads' => $leads,
            'tab' => $tab,
            'batch' => $batch,
            'counts' => $counts,
            'total' => $counts->sum(),
            'todayCount' => $todayTask($mine())->count(),
            'statusTabs' => $statusTabs,
            'cities' => $mine()->whereNotNull('city')->distinct()->orderBy('city')->limit(100)->pluck('city'),
            'sources' => $mine()->whereNotNull('source')->distinct()->orderBy('source')->limit(50)->pluck('source'),
            'filters' => compact('city', 'source', 'search'),
            'extraFields' => $extraFields,
        ]);
    }

    /** Screen 8 — Create Lead. */
    public function create()
    {
        return view('employee.leads.create', ['options' => config('portal.lead')]);
    }

    public function store(StoreLeadRequest $request)
    {
        $lead = $this->leads->create($request->user(), $request->validated());

        return redirect()->route('employee.leads.index')->with('success', "Lead \"{$lead->name}\" saved.");
    }

    public function show(Request $request, int $lead)
    {
        $lead = Lead::ownedBy($request->user())->with(['activities.user', 'department', 'corrector', 'communications.user', 'meetings.participants'])->findOrFail($lead);

        // Converted / hired list for the bottom card (same department)
        $converted = Lead::ownedBy($request->user())->where('status', LeadStatus::Converted->value)
            ->when($lead->department_id, fn ($q) => $q->where('department_id', $lead->department_id))
            ->latest('converted_at')->limit(6)->get(['id', 'name', 'purpose', 'interest', 'converted_at', 'next_action_at', 'status']);

        return view('employee.leads.show', compact('lead', 'converted'));
    }

    public function updateStatus(Request $request, int $lead)
    {
        $lead = Lead::ownedBy($request->user())->findOrFail($lead);

        $data = $request->validate([
            'status' => ['required', Rule::enum(LeadStatus::class)],
            'follow_up_date' => ['nullable', 'required_if:status,follow_up,callback', 'date', 'after_or_equal:today'],
            'note' => ['nullable', 'string', 'max:500'],
        ], ['follow_up_date.required_if' => 'Pick the date and time to connect next.']);

        $this->leads->updateStatus($lead, $request->user(), LeadStatus::from($data['status']), $data['follow_up_date'] ?? null, $data['note'] ?? null);

        // Inline dropdown in the list sends JSON
        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'message' => "{$lead->name} → ".LeadStatus::from($data['status'])->label()]);
        }

        return back()->with('success', "{$lead->name} marked as ".LeadStatus::from($data['status'])->label().'.');
    }
}
