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
        $tab = in_array($request->query('tab'), ['all', 'follow_up', 'converted'], true) ? $request->query('tab') : 'all';
        $search = trim((string) $request->query('q'));
        $status = LeadStatus::tryFrom((string) $request->query('status'));

        $counts = Lead::ownedBy($user)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $leads = Lead::ownedBy($user)
            ->when($tab !== 'all', fn ($q) => $q->where('status', $tab))
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('mobile', 'like', "%{$search}%")
                ->orWhere('city', 'like', "%{$search}%")))
            ->latest()
            ->paginate(12)
            ->withQueryString();

        return view('employee.leads.index', [
            'leads' => $leads,
            'tab' => $tab,
            'counts' => $counts,
            'total' => $counts->sum(),
            'today' => Lead::ownedBy($user)->whereDate('created_at', today())->count(),
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
        $lead = Lead::ownedBy($request->user())->with('activities.user')->findOrFail($lead);

        return view('employee.leads.show', compact('lead'));
    }

    public function updateStatus(Request $request, int $lead)
    {
        $lead = Lead::ownedBy($request->user())->findOrFail($lead);

        $data = $request->validate([
            'status' => ['required', Rule::enum(LeadStatus::class)],
            'follow_up_date' => ['nullable', 'required_if:status,follow_up', 'date', 'after_or_equal:today'],
            'note' => ['nullable', 'string', 'max:500'],
        ], ['follow_up_date.required_if' => 'Please pick a follow-up date.']);

        $this->leads->updateStatus($lead, $request->user(), LeadStatus::from($data['status']), $data['follow_up_date'] ?? null, $data['note'] ?? null);

        return back()->with('success', "{$lead->name} marked as ".LeadStatus::from($data['status'])->label().'.');
    }
}
