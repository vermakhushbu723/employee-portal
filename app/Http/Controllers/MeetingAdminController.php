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

/** Admin / manager: all (or team) meetings, and assign a meeting to an employee (they accept or decline it). */
class MeetingAdminController extends Controller
{
    public function index(Request $request)
    {
        $me = $request->user();
        MeetingPeopleService::closeMissed();
        $staff = $this->staff($me);
        $tab = in_array($request->query('tab'), ['today', 'upcoming', 'pending', 'past'], true) ? $request->query('tab') : 'today';

        // Own team's meetings + meetings this manager was added to
        $base = fn () => Meeting::where(fn ($q) => $q->whereIn('user_id', $staff->pluck('id'))
            ->orWhereIn('id', \DB::table('meeting_participants')->where('user_id', $me->id)->select('meeting_id')));
        $scopes = [
            'today' => fn ($q) => $q->whereDate('scheduled_at', today())->orderBy('scheduled_at'),
            'upcoming' => fn ($q) => $q->whereIn('status', ['upcoming', 'live'])->where('scheduled_at', '>=', now()->subHours(2))->orderBy('scheduled_at'),
            'pending' => fn ($q) => $q->where('status', 'pending')->orderBy('scheduled_at'),
            'past' => fn ($q) => $q->where('scheduled_at', '<', today())->latest('scheduled_at'),
        ];

        return view('meetings.manage', [
            'tab' => $tab,
            'counts' => collect($scopes)->map(fn ($s) => $s($base())->count()),
            'meetings' => $scopes[$tab]($base())->with('user', 'creator', 'participants', 'lead')->paginate(20)->withQueryString(),
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
            'notes' => ['nullable', 'string', 'max:1000'],
            'lead_id' => ['nullable', 'integer', 'exists:leads,id'],
            'primary_manager_id' => ['nullable', 'integer', 'exists:users,id'],
            'managers' => ['nullable', 'array'],
            'managers.*' => ['integer', 'exists:users,id'],
            'notify_emails' => ['nullable', 'string', 'max:1000'],
            'notify_numbers' => ['nullable', 'string', 'max:500'],
        ], ['user_id.in' => 'Choose an employee from the list.']);

        $meeting = Meeting::create(collect($data)->except(['primary_manager_id', 'managers', 'notify_emails', 'notify_numbers'])->all() + [
            'created_by' => $request->user()->id,
            'scheduled_at' => Carbon::parse($data['date'].' '.$data['time']),
            'status' => 'pending',
        ]);
        $employee = $staff->firstWhere('id', $data['user_id']);

        Announcement::create([
            'title' => 'New Meeting Request',
            'body' => "{$meeting->title} with {$meeting->customer_name} · {$meeting->scheduled_at->format('d M, h:i A')} ({$meeting->platformLabel()})\nOpen Meetings → Accept or Decline.",
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

    public function cancel(Request $request, Meeting $meeting)
    {
        abort_unless($this->staff($request->user())->contains('id', $meeting->user_id), 403);
        abort_if(in_array($meeting->status, ['completed', 'cancelled'], true), 422);
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
