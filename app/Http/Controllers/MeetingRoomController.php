<?php

namespace App\Http\Controllers;

use App\Models\Meeting;
use App\Services\MeetingRoomService;
use Illuminate\Http\Request;

/**
 * Built-in meeting room API (used by public/js/meeting-room.js).
 *   Staff (logged in): /room/{meeting}/…   — employee, admin, manager, whoever gave the meeting
 *   Customer (no login): /meet/{token}/…    — the secret link sent to the customer
 */
class MeetingRoomController extends Controller
{
    public function __construct(private MeetingRoomService $room) {}

    /* ---------------- Customer page */

    public function guest(string $token)
    {
        $m = Meeting::where('room_token', $token)->firstOrFail();

        return view('meetings.guest', ['m' => $m, 'api' => $this->api($m, true)]);
    }

    /* ---------------- API (staff + customer) */

    public function join(Request $request, ...$key)
    {
        $m = $this->meeting($request, $key);
        $user = $request->user();
        $name = $user ? $user->name.($user->id === $m->user_id ? '' : ' ('.$user->roleLabel().')') : (string) $request->validate(['name' => ['required', 'string', 'max:60']])['name'];

        // The employee joining their own meeting = "In Meeting" on the dashboards
        if ($user && $user->id === $m->user_id && $m->status === 'upcoming') {
            Meeting::where('user_id', $m->user_id)->where('status', 'live')->whereKeyNot($m->id)->update(['status' => 'completed', 'ended_at' => now()]);
            $m->update(['status' => 'live', 'started_at' => $m->started_at ?? now()]);
        }

        return response()->json($this->room->join($m, $user, $name));
    }

    public function signal(Request $request, ...$key)
    {
        $m = $this->meeting($request, $key);
        $data = $request->validate([
            'peer' => ['required', 'string'], 'secret' => ['required', 'string'],
            'batch' => ['required', 'string', 'max:400000'],
        ]);
        $peer = $this->room->peer($m, $data['peer'], $data['secret']);

        // A batch of messages: [{to, type, payload}, …]
        $batch = json_decode($data['batch'], true);
        abort_unless(is_array($batch) && count($batch) <= 50, 422);
        foreach ($batch as $msg) {
            if (! is_array($msg) || ! in_array($msg['type'] ?? null, ['offer', 'answer', 'candidate', 'chat'], true)
                || ! is_string($msg['payload'] ?? null) || strlen($msg['payload']) > 60000
                || (isset($msg['to']) && (! is_string($msg['to']) || strlen($msg['to']) > 36))) {
                continue;
            }
            $this->room->signal($m, $peer, $msg['to'] ?? null, $msg['type'], $msg['payload']);
        }

        return response()->noContent();
    }

    public function poll(Request $request, ...$key)
    {
        $m = $this->meeting($request, $key);
        $peer = $this->room->peer($m, $request->query('peer'), $request->query('secret'));

        return response()->json($this->room->poll($m, $peer, $request->integer('after')))->header('Cache-Control', 'no-store');
    }

    public function leave(Request $request, ...$key)
    {
        $m = $this->meeting($request, $key);
        $this->room->leave($this->room->peer($m, $request->input('peer'), $request->input('secret')));

        return response()->noContent();
    }

    /** Endpoints for the browser. */
    public static function api(Meeting $m, bool $guest): array
    {
        $p = $guest ? ['meet.guest.join', 'meet.guest.signal', 'meet.guest.poll', 'meet.guest.leave'] : ['meet.join', 'meet.signal', 'meet.poll', 'meet.leave'];
        $arg = $guest ? $m->room_token : $m->id;

        return [
            'join' => route($p[0], $arg), 'signal' => route($p[1], $arg), 'poll' => route($p[2], $arg), 'leave' => route($p[3], $arg),
            'guest' => $guest,
            'invite' => $m->isPortal() ? route('meet.guest', $m->room_token) : null,
        ];
    }

    /** Resolve the meeting from /room/{id} (staff, access checked) or /meet/{token} (customer). */
    private function meeting(Request $request, array $key): Meeting
    {
        $value = $request->route('token') ?? $request->route('meeting');
        if ($request->route('token') !== null) {
            return Meeting::where('room_token', $value)->firstOrFail();
        }

        $m = Meeting::with('user.employee')->findOrFail((int) $value);
        abort_unless($request->user() && $this->room->canJoin($request->user(), $m), 403);

        return $m;
    }
}
