<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\Announcement;
use App\Models\Meeting;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\MeetingPeopleService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * Admin / manager / team leader: all (or team) meetings, give a meeting to an employee (they accept or decline it)
 * and review meetings: close / keep open / reschedule + next tag. Only this review closes a meeting.
 */
class MeetingAdminController extends Controller
{
    public function index(Request $request)
    {
        $me = $request->user();
        $staff = $this->staff($me);
        $tab = in_array($request->query('tab'), ['today', 'review', 'upcoming', 'pending', 'past'], true) ? $request->query('tab') : 'today';

        // Own team's meetings + meetings this manager was added to
        $base = fn () => Meeting::where(fn ($q) => $q->whereIn('user_id', $staff->pluck('id'))
            ->orWhereIn('id', \DB::table('meeting_participants')->where('user_id', $me->id)->select('meeting_id')));
        $scopes = [
            'today' => fn ($q) => $q->whereDate('scheduled_at', today())->orderBy('scheduled_at'),
            // Started and nobody in the room any more, or its time is over: waiting for close / open / reschedule
            'review' => fn ($q) => $q->where(fn ($q) => $q->where('status', 'live')
                ->orWhere(fn ($q) => $q->where('status', 'upcoming')->where('ends_at', '<', now())))
                ->whereDoesntHave('peers', fn ($p) => $p->whereNull('left_at')->where('last_seen_at', '>=', now()->subSeconds(\App\Services\MeetingRoomService::PEER_TIMEOUT)))
                ->orderBy('scheduled_at'),
            'upcoming' => fn ($q) => $q->whereIn('status', ['upcoming', 'live'])->where('ends_at', '>=', now())->orderBy('scheduled_at'),
            'pending' => fn ($q) => $q->where('status', 'pending')->orderBy('scheduled_at'),
            'past' => fn ($q) => $q->whereIn('status', ['completed', 'cancelled', 'declined'])->latest('scheduled_at'),
        ];

        return view('meetings.manage', [
            'tab' => $tab,
            'counts' => collect($scopes)->map(fn ($s) => $s($base())->count()),
            'meetings' => $scopes[$tab]($base())->withRoomCount()->with('user', 'creator', 'participants', 'lead', 'reviewer')->paginate(20)->withQueryString(),
            'staff' => $staff,
            'platforms' => Meeting::PLATFORMS,
            'portal' => $me->role->portal(),
            'managers' => MeetingPeopleService::managers(),
            'people' => app(MeetingPeopleService::class),
            'forLead' => $request->integer('for_lead') ? \App\Models\Lead::with('assignee')->find($request->integer('for_lead')) : null,
        ]);
    }

    public function store(Request $request, AuditLogger $audit)
    {
        $staff = $this->staff($request->user());
        $data = $request->validate([
            'user_id' => ['required', Rule::in($staff->pluck('id')->all())],
            'customer_name' => ['required', 'string', 'max:120'],
            'customer_mobile' => ['nullable', 'string', 'max:20'],
            'city' => ['nullable', 'string', 'max:80'],
            'title' => ['required', 'string', 'max:150'],
            'platform' => ['required', Rule::in(array_keys(Meeting::PLATFORMS))],
            'link' => ['nullable', 'url', 'max:500'],   // only for Google Meet / Zoom / Teams
            'date' => ['required', 'date', 'after_or_equal:today'],
            'time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'lead_id' => ['nullable', 'integer', 'exists:leads,id'],
            'primary_manager_id' => ['nullable', 'integer', 'exists:users,id'],
            'managers' => ['nullable', 'array'],
            'managers.*' => ['integer', 'exists:users,id'],
            'notify_emails' => ['nullable', 'string', 'max:1000'],
            'notify_numbers' => ['nullable', 'string', 'max:500'],
        ], ['user_id.in' => 'Choose an employee from the list.']);

        $meeting = Meeting::create(collect($data)->except(['primary_manager_id', 'managers', 'notify_emails', 'notify_numbers', 'date', 'time', 'end_time'])->all() + [
            'created_by' => $request->user()->id,
            'scheduled_at' => Carbon::parse($data['date'].' '.$data['time']),
            'ends_at' => \App\Http\Controllers\Employee\MeetingController::endsAt($data),
            'status' => 'pending',
        ]);
        $employee = $staff->firstWhere('id', $data['user_id']);

        Announcement::create([
            'title' => 'New Meeting Request',
            'body' => "{$meeting->title} with {$meeting->customer_name} · {$meeting->scheduled_at->format('d M')}, {$meeting->timeRange()} ({$meeting->platformLabel()})\nOpen Meetings → Accept or Decline.",
            'type' => 'important',
            'user_id' => $employee->id,
            'created_by' => $request->user()->id,
            'published_at' => now(),
        ]);
        $audit->log('meeting.assign', "Assigned meeting \"{$meeting->title}\" to {$employee->employee_code}");

        $people = app(MeetingPeopleService::class);
        $people->setParticipants($meeting, $data['primary_manager_id'] ?? null, $data['managers'] ?? []);
        $sent = $people->notify($meeting->load('participants'), $request->user(),
            \App\Http\Controllers\Employee\MeetingController::splitList($data['notify_emails'] ?? ''),
            \App\Http\Controllers\Employee\MeetingController::splitList($data['notify_numbers'] ?? ''));

        return back()->with('success', "Meeting request sent to {$employee->name}. It shows as Pending until they accept."
            .($sent ? ' Invite sent to '.collect($sent)->where('ok', true)->count().' of '.count($sent).'.' : ''))
            ->with('wa_links', collect($sent)->whereNotNull('link')->values()->all());
    }

    /** Admin / manager join a built-in meeting inside their dashboard. */
    public function room(Request $request, Meeting $meeting)
    {
        abort_unless(app(\App\Services\MeetingRoomService::class)->canJoin($request->user(), $meeting->load('user.employee')), 403);
        if (! $meeting->isPortal()) {
            return $meeting->link ? redirect()->away($meeting->link) : back()->with('error', 'This meeting has no link yet.');
        }

        return view('meetings.room', ['m' => $meeting, 'portal' => $request->user()->role->portal()]);
    }

    /**
     * Review after (or instead of) the meeting: close it, keep it open, or reschedule it, with the result and
     * the next tag (Interested / Follow Up / Hire / Lead Closed / Irrelevant / Fake Lead), which also updates the lead.
     */
    public function review(Request $request, Meeting $meeting, AuditLogger $audit)
    {
        $me = $request->user();
        abort_unless($this->staff($me)->contains('id', $meeting->user_id)
            || $meeting->participants()->whereKey($me->id)->exists(), 403);
        abort_if(in_array($meeting->status, ['cancelled', 'declined'], true), 422, 'This meeting was cancelled.');

        $data = $request->validate([
            'action' => ['required', Rule::in(['close', 'open', 'reschedule'])],
            'outcome' => ['nullable', Rule::in(array_keys(MeetingPeopleService::OUTCOMES))],
            'tag' => ['nullable', Rule::in(array_keys(Meeting::TAGS))],
            'outcome_note' => ['nullable', 'string', 'max:500'],
            'date' => ['required_if:action,reschedule', 'nullable', 'date', 'after_or_equal:today'],
            'time' => ['required_if:action,reschedule', 'nullable', 'date_format:H:i'],
            'end_time' => ['required_if:action,reschedule', 'nullable', 'date_format:H:i'],
        ]);

        $seconds = app(\App\Services\MeetingRoomService::class)->connectedSeconds($meeting);
        $fill = [
            'tag' => $data['tag'] ?? $meeting->tag,
            'outcome' => $data['outcome'] ?? $meeting->outcome ?? ($seconds > 0 ? 'success' : 'not_connected'),
            'outcome_note' => $data['outcome_note'] ?? $meeting->outcome_note,
            'duration_seconds' => $meeting->isPortal() ? $seconds : ($meeting->started_at ? (int) $meeting->started_at->diffInSeconds(now(), true) : 0),
            'reviewed_by' => $me->id,
            'reviewed_at' => now(),
        ];
        $fill += match ($data['action']) {
            'close' => ['status' => 'completed', 'ended_at' => $meeting->ended_at ?? now()],
            'open' => ['status' => $meeting->status === 'completed' ? 'upcoming' : $meeting->status, 'ended_at' => null],
            'reschedule' => [
                'status' => 'upcoming', 'ended_at' => null,
                'scheduled_at' => Carbon::parse($data['date'].' '.$data['time']),
                'ends_at' => \App\Http\Controllers\Employee\MeetingController::endsAt($data),
            ],
        };
        // A meeting that is still running stays "live"
        if ($data['action'] !== 'close' && $meeting->status === 'live' && $meeting->hasPeopleInRoom()) {
            $fill['status'] = 'live';
        }
        $meeting->update($fill);

        // The tag moves the lead on (e.g. Fake Lead → Invalid, Hire / Lead Closed → Converted)
        if (($tag = Meeting::TAGS[$meeting->tag] ?? null) && $meeting->lead) {
            $meeting->lead->update(['status' => $tag[3]] + ($data['action'] === 'reschedule'
                ? ['next_action_at' => $meeting->scheduled_at, 'next_action_type' => 'meeting'] : []));
        }

        $what = ['close' => 'closed', 'open' => 'kept open', 'reschedule' => 'rescheduled to '.$meeting->scheduled_at->format('d M').', '.$meeting->timeRange()][$data['action']];
        Announcement::create([
            'title' => ['close' => 'Meeting Closed', 'open' => 'Meeting Kept Open', 'reschedule' => 'Meeting Rescheduled'][$data['action']],
            'body' => "{$meeting->title} with {$meeting->customer_name} was {$what} by ".$me->publicName().'.'
                .($meeting->tagMeta() ? ' Next: '.$meeting->tagMeta()[0].'.' : '').($meeting->outcome_note ? "\nNote: {$meeting->outcome_note}" : ''),
            'type' => 'general',
            'user_id' => $meeting->user_id,
            'created_by' => $me->id,
            'published_at' => now(),
        ]);
        $audit->log('meeting.review', "Meeting #{$meeting->id} {$what}".($meeting->tag ? " · tag {$meeting->tag}" : ''));

        return redirect()->route($me->role->portal().'.meetings.index', ['tab' => $data['action'] === 'close' ? 'past' : 'today'])
            ->with('success', "Meeting {$what}.".($meeting->durationLabel() ? ' Connected time '.$meeting->durationLabel().'.' : ''));
    }

    public function cancel(Request $request, Meeting $meeting)
    {
        abort_unless($this->staff($request->user())->contains('id', $meeting->user_id), 403);
        abort_if(in_array($meeting->status, ['completed', 'cancelled'], true), 422);
        abort_if($meeting->status === 'live', 422, 'This meeting has started — use Review to close it.');
        $meeting->update(['status' => 'cancelled']);

        return back()->with('success', 'Meeting cancelled.');
    }

    private function staff(User $user)
    {
        return ($user->role->portal() === 'admin'
            ? User::whereIn('role', [Role::Employee->value, Role::TeamLeader->value])
            : $user->teamMembers())->where('status', 'active')->orderBy('name')->get(['id', 'name', 'employee_code']);
    }
}
