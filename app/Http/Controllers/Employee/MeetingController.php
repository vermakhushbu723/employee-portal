<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\Meeting;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/** Employee: Meeting Schedule — book, accept / decline, join (In Meeting), end. */
class MeetingController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $tab = in_array($request->query('tab'), ['upcoming', 'past', 'requests'], true) ? $request->query('tab') : 'upcoming';

        $mine = fn () => Meeting::where('user_id', $user->id);
        $scopes = [
            'upcoming' => fn ($q) => $q->whereIn('status', ['upcoming', 'live'])->where('scheduled_at', '>=', now()->subHours(2))->orderBy('scheduled_at'),
            'requests' => fn ($q) => $q->where('status', 'pending')->orderBy('scheduled_at'),
            'past' => fn ($q) => $q->where(fn ($q) => $q->whereIn('status', ['completed', 'declined', 'cancelled'])
                ->orWhere(fn ($q) => $q->where('status', 'upcoming')->where('scheduled_at', '<', now()->subHours(2))))->latest('scheduled_at'),
        ];

        return view('employee.meetings.index', [
            'tab' => $tab,
            'counts' => collect($scopes)->map(fn ($s) => $s($mine())->count()),
            'meetings' => $scopes[$tab]($mine())->with('creator')->paginate(15)->withQueryString(),
            'live' => $mine()->where('status', 'live')->first(),
            'leads' => Lead::ownedBy($user)->whereIn('status', ['interested', 'follow_up', 'new', 'converted'])->orderByDesc('updated_at')->limit(200)->get(['id', 'name', 'mobile', 'city']),
            'platforms' => Meeting::PLATFORMS,
        ]);
    }

    public function store(Request $request, AuditLogger $audit)
    {
        $data = $this->validated($request);
        $lead = ! empty($data['lead_id']) ? Lead::ownedBy($request->user())->find($data['lead_id']) : null;

        $meeting = Meeting::create([
            'user_id' => $request->user()->id,
            'created_by' => $request->user()->id,
            'lead_id' => $lead?->id,
            'customer_name' => $lead?->name ?? $data['customer_name'],
            'customer_mobile' => $lead?->mobile ?? ($data['customer_mobile'] ?? null),
            'city' => $lead?->city ?? ($data['city'] ?? null),
            'title' => $data['title'],
            'platform' => $data['platform'],
            'link' => $data['link'] ?? null,
            'scheduled_at' => Carbon::parse($data['date'].' '.$data['time']),
            'notes' => $data['notes'] ?? null,
            'status' => 'upcoming',
        ]);
        $audit->log('meeting.book', "Booked meeting \"{$meeting->title}\" with {$meeting->customer_name} on {$meeting->scheduled_at->format('d M h:i A')}");

        return redirect()->route('employee.meetings.index')->with('success', "Meeting booked for {$meeting->scheduled_at->format('d M, h:i A')}.".($meeting->link ? ' Send the link to the customer from the list.' : ' Add the meeting link before it starts.'));
    }

    public function update(Request $request, int $meeting)
    {
        $m = $this->find($request, $meeting);
        abort_if(in_array($m->status, ['completed', 'declined', 'cancelled'], true), 422, 'This meeting is closed.');
        $data = $request->validate([
            'link' => ['nullable', 'url', 'max:500'],
            'date' => ['required', 'date', 'after_or_equal:today'],
            'time' => ['required', 'date_format:H:i'],
        ]);
        $m->update(['link' => $data['link'] ?? null, 'scheduled_at' => Carbon::parse($data['date'].' '.$data['time'])]);

        return back()->with('success', 'Meeting updated.');
    }

    public function accept(Request $request, int $meeting)
    {
        $m = $this->find($request, $meeting);
        abort_unless($m->status === 'pending', 422, 'This request was already answered.');
        $m->update(['status' => 'upcoming']);

        return back()->with('success', "Meeting with {$m->customer_name} accepted.");
    }

    public function decline(Request $request, int $meeting)
    {
        $m = $this->find($request, $meeting);
        abort_unless($m->status === 'pending', 422, 'This request was already answered.');
        $m->update(['status' => 'declined', 'notes' => trim($m->notes."\nDeclined: ".$request->input('reason', ''))]);

        return back()->with('success', 'Meeting request declined.');
    }

    /** Join: marks the meeting live (dashboards show "In Meeting") and opens the meeting room. */
    public function join(Request $request, int $meeting)
    {
        $m = $this->find($request, $meeting);
        abort_unless(in_array($m->status, ['upcoming', 'live'], true), 422, 'This meeting cannot be joined.');

        // Only one live meeting at a time
        Meeting::where('user_id', $m->user_id)->where('status', 'live')->whereKeyNot($m->id)->update(['status' => 'completed', 'ended_at' => now()]);
        if ($m->status !== 'live') {
            $m->update(['status' => 'live', 'started_at' => now()]);
        }

        return redirect()->route('employee.meetings.room', $m);
    }

    public function room(Request $request, int $meeting)
    {
        $m = $this->find($request, $meeting)->load('creator', 'lead');

        return view('employee.meetings.room', [
            'm' => $m,
            'next' => Meeting::where('user_id', $m->user_id)->whereIn('status', ['upcoming'])->where('scheduled_at', '>', now())->orderBy('scheduled_at')->first(),
        ]);
    }

    public function end(Request $request, int $meeting)
    {
        $m = $this->find($request, $meeting);
        $m->update(['status' => 'completed', 'ended_at' => now(), 'started_at' => $m->started_at ?? now()]);

        return redirect()->route('employee.meetings.index')->with('success', 'Meeting ended ('.$m->started_at->diffForHumans($m->ended_at, true).').');
    }

    private function find(Request $request, int $id): Meeting
    {
        return Meeting::where('user_id', $request->user()->id)->findOrFail($id);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'lead_id' => ['nullable', 'integer'],
            'customer_name' => ['required_without:lead_id', 'nullable', 'string', 'max:120'],
            'customer_mobile' => ['nullable', 'string', 'max:20'],
            'city' => ['nullable', 'string', 'max:80'],
            'title' => ['required', 'string', 'max:150'],
            'platform' => ['required', Rule::in(array_keys(Meeting::PLATFORMS))],
            'link' => ['nullable', 'url', 'max:500'],
            'date' => ['required', 'date', 'after_or_equal:today'],
            'time' => ['required', 'date_format:H:i'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ], ['customer_name.required_without' => 'Choose a lead or type the customer name.']);
    }
}
