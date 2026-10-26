<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\Announcement;
use App\Models\Meeting;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/** Admin / manager: all (or team) meetings, and assign a meeting to an employee (they accept or decline it). */
class MeetingAdminController extends Controller
{
    public function index(Request $request)
    {
        $staff = $this->staff($request->user());
        $tab = in_array($request->query('tab'), ['today', 'upcoming', 'pending', 'past'], true) ? $request->query('tab') : 'today';

        $base = fn () => Meeting::whereIn('user_id', $staff->pluck('id'));
        $scopes = [
            'today' => fn ($q) => $q->whereDate('scheduled_at', today())->orderBy('scheduled_at'),
            'upcoming' => fn ($q) => $q->whereIn('status', ['upcoming', 'live'])->where('scheduled_at', '>=', now()->subHours(2))->orderBy('scheduled_at'),
            'pending' => fn ($q) => $q->where('status', 'pending')->orderBy('scheduled_at'),
            'past' => fn ($q) => $q->where('scheduled_at', '<', today())->latest('scheduled_at'),
        ];

        return view('meetings.manage', [
            'tab' => $tab,
            'counts' => collect($scopes)->map(fn ($s) => $s($base())->count()),
            'meetings' => $scopes[$tab]($base())->with('user', 'creator')->paginate(20)->withQueryString(),
            'staff' => $staff,
            'platforms' => Meeting::PLATFORMS,
            'portal' => $request->user()->role->portal(),
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
        ], ['user_id.in' => 'Choose an employee from the list.']);

        $meeting = Meeting::create($data + [
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

        return back()->with('success', "Meeting request sent to {$employee->name}. It shows as Pending until they accept.");
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
