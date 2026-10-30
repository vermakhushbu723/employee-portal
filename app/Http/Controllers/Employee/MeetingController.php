<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Enums\LeadStatus;
use App\Models\Lead;
use App\Models\Meeting;
use App\Services\AuditLogger;
use App\Services\MeetingPeopleService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/** Employee: Meeting Schedule — book, accept / decline, join. Admin / manager / TL close meetings (review). */
class MeetingController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $tab = in_array($request->query('tab'), ['upcoming', 'past', 'requests', 'emails'], true) ? $request->query('tab') : 'upcoming';

        $mine = fn () => Meeting::where('user_id', $user->id);
        $scopes = [
            // Open meetings stay here (also after their time) until admin / manager / TL closes them
            'upcoming' => fn ($q) => $q->whereIn('status', ['upcoming', 'live'])->orderBy('scheduled_at'),
            'requests' => fn ($q) => $q->where('status', 'pending')->orderBy('scheduled_at'),
            'past' => fn ($q) => $q->whereIn('status', ['completed', 'declined', 'cancelled'])->latest('scheduled_at'),
        ];

        $leadsWithEmail = Lead::ownedBy($user)->whereNotNull('email')->orderByDesc('updated_at')->limit(300)->get(['name', 'email']);

        return view('employee.meetings.index', [
            'tab' => $tab,
            'counts' => collect($scopes)->map(fn ($s) => $s($mine())->count())->put('emails', \App\Models\SentEmail::where('user_id', $user->id)->count()),
            'meetings' => $scopes[$tab === 'emails' ? 'upcoming' : $tab]($mine())->withRoomCount()->with('creator', 'participants', 'lead')->paginate(15)->withQueryString(),
            'emails' => $tab === 'emails' ? \App\Models\SentEmail::with('meeting')->where('user_id', $user->id)->latest()->paginate(20)->withQueryString() : null,
            // Addresses already in the data (leads), offered while typing "To"
            'emailSuggestions' => $leadsWithEmail->map(fn ($l) => ['email' => $l->email, 'label' => $l->name])->unique('email')->values()->all(),
            'live' => $mine()->where('status', 'live')->withRoomCount()->get()->first(fn ($m) => $m->hasPeopleInRoom()),
            'tags' => Meeting::TAGS,
            'leads' => Lead::ownedBy($user)->whereIn('status', ['interested', 'follow_up', 'new', 'converted'])->orderByDesc('updated_at')->limit(200)->get(['id', 'name', 'mobile', 'city', 'email']),
            'platforms' => Meeting::PLATFORMS,
            'managers' => MeetingPeopleService::managers(),
            'myManagerId' => $user->employee?->manager_id,
            'outcomes' => MeetingPeopleService::OUTCOMES,
            'forLead' => $request->integer('lead') ? Lead::ownedBy($user)->find($request->integer('lead')) : null,
        ]);
    }

    public function store(Request $request, AuditLogger $audit, MeetingPeopleService $people)
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
            'ends_at' => self::endsAt($data),
            'notes' => $data['notes'] ?? null,
            'status' => 'upcoming',
        ]);
        $audit->log('meeting.book', "Booked meeting \"{$meeting->title}\" with {$meeting->customer_name} on {$meeting->scheduled_at->format('d M h:i A')}");

        $people->setParticipants($meeting, $data['primary_manager_id'] ?? null, $data['managers'] ?? []);
        if ($lead && $lead->status !== LeadStatus::Meeting && ! in_array($lead->status->value, LeadStatus::positive(), true)) {
            $lead->update(['status' => LeadStatus::Meeting, 'next_action_at' => $meeting->scheduled_at, 'next_action_type' => 'meeting']);
        } elseif ($lead) {
            $lead->update(['next_action_at' => $meeting->scheduled_at, 'next_action_type' => 'meeting']);
        }
        $sent = $people->notify($meeting->load('participants'), $request->user(), self::splitList($data['notify_emails'] ?? ''), self::splitList($data['notify_numbers'] ?? ''));
        $ok = collect($sent)->where('ok', true)->count();
        $manual = collect($sent)->whereNotNull('link');

        return redirect()->route('employee.meetings.index')
            ->with('success', "Meeting booked for {$meeting->scheduled_at->format('d M, h:i A')}.".($sent ? " Invite sent to {$ok} of ".count($sent).'.' : ''))
            ->with('wa_links', $manual->values()->all());
    }

    public function update(Request $request, int $meeting)
    {
        $m = $this->find($request, $meeting);
        abort_if(in_array($m->status, ['completed', 'declined', 'cancelled'], true), 422, 'This meeting is closed.');
        $data = $request->validate([
            'link' => ['nullable', 'url', 'max:500'],
            'date' => ['required', 'date', 'after_or_equal:today'],
            'time' => ['required', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i'],
        ]);
        $m->update(['link' => $data['link'] ?? null, 'scheduled_at' => Carbon::parse($data['date'].' '.$data['time']), 'ends_at' => self::endsAt($data)]);

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

    /** Join: marks the meeting live and opens the meeting room. Other meetings stay open (only a review closes them). */
    public function join(Request $request, int $meeting)
    {
        $m = $this->find($request, $meeting);
        abort_unless(in_array($m->status, ['upcoming', 'live'], true), 422, 'This meeting cannot be joined.');

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
            'end_time' => ['required', 'date_format:H:i'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'primary_manager_id' => ['nullable', 'integer', 'exists:users,id'],
            'managers' => ['nullable', 'array'],
            'managers.*' => ['integer', 'exists:users,id'],
            'notify_emails' => ['nullable', 'string', 'max:1000'],
            'notify_numbers' => ['nullable', 'string', 'max:500'],
        ], ['customer_name.required_without' => 'Choose a lead or type the customer name.']);
    }

    /** "To time" of the booking form; a to-time earlier than the from-time is the next day. Default 30 minutes. */
    public static function endsAt(array $data): Carbon
    {
        $start = Carbon::parse($data['date'].' '.$data['time']);
        if (empty($data['end_time'])) {
            return $start->copy()->addMinutes(30);
        }
        $end = Carbon::parse($data['date'].' '.$data['end_time']);

        return $end->lte($start) ? $end->addDay() : $end;
    }

    /** "a@x.com, b@y.com" / numbers on new lines → list */
    public static function splitList(string $value): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/[\s,;]+/', $value))));
    }
}
