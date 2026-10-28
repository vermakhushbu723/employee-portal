<?php

namespace App\Services;

use App\Models\Meeting;
use App\Models\MeetingPeer;
use App\Models\MeetingSignal;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Built-in meeting room (WebRTC). Video and audio go directly between the browsers;
 * this service only lets them find each other: who is in the room, and the small
 * offer / answer / candidate messages. Chat lines travel the same way.
 */
class MeetingRoomService
{
    /** A peer that has not polled for this long has left (tab closed, network gone). */
    public const PEER_TIMEOUT = 15;

    public const MAX_PEERS = 8;

    /** Staff who may join from the dashboard: the employee, whoever gave the meeting, admins, the employee's manager. */
    public function canJoin(User $user, Meeting $m): bool
    {
        return $m->user_id === $user->id
            || $m->created_by === $user->id
            || $user->role->portal() === 'admin'
            || $m->user?->employee?->manager_id === $user->id
            || $m->participants()->whereKey($user->id)->exists();   // managers added to the meeting
    }

    public function join(Meeting $m, ?User $user, string $name): array
    {
        if (! $m->isPortal()) {
            throw ValidationException::withMessages(['room' => 'This meeting uses an outside link.']);
        }
        if (in_array($m->status, ['completed', 'cancelled', 'declined'], true)) {
            throw ValidationException::withMessages(['room' => 'This meeting has ended.']);
        }

        // Old messages are not needed any more
        MeetingSignal::where('meeting_id', $m->id)->where('created_at', '<', now()->subMinutes(30))->delete();

        if ($this->active($m)->count() >= self::MAX_PEERS) {
            throw ValidationException::withMessages(['room' => 'The meeting room is full.']);
        }

        // Rejoining (refresh): the old seat of this user is closed
        if ($user) {
            MeetingPeer::where('meeting_id', $m->id)->where('user_id', $user->id)->whereNull('left_at')->update(['left_at' => now()]);
        }

        $peer = MeetingPeer::create([
            'id' => (string) Str::uuid(),
            'meeting_id' => $m->id,
            'user_id' => $user?->id,
            'name' => Str::limit(trim($name) ?: 'Guest', 60, ''),
            'role' => ! $user ? 'guest' : ($user->id === $m->user_id ? 'host' : 'staff'),
            'secret' => Str::random(48),
            'last_seen_at' => now(),
        ]);

        return [
            'peer' => ['id' => $peer->id, 'secret' => $peer->secret, 'name' => $peer->name, 'since' => $peer->created_at->getTimestampMs()],
            'peers' => $this->peerList($m, $peer->id),
            'after' => (int) MeetingSignal::where('meeting_id', $m->id)->max('id'),
            'ice' => $this->iceServers(),
        ];
    }

    public function signal(Meeting $m, MeetingPeer $from, ?string $to, string $type, string $payload): void
    {
        if ($to && ! $this->active($m)->contains('id', $to)) {
            return; // that peer already left
        }
        MeetingSignal::create(['meeting_id' => $m->id, 'from_peer' => $from->id, 'to_peer' => $to, 'type' => $type, 'payload' => $payload]);
    }

    public function poll(Meeting $m, MeetingPeer $peer, int $after): array
    {
        $peer->forceFill(['last_seen_at' => now()])->saveQuietly();

        $signals = MeetingSignal::where('meeting_id', $m->id)->where('id', '>', $after)
            ->where('from_peer', '!=', $peer->id)
            ->where(fn ($q) => $q->whereNull('to_peer')->orWhere('to_peer', $peer->id))
            ->orderBy('id')->limit(200)->get(['id', 'from_peer', 'type', 'payload']);

        return [
            'signals' => $signals->map(fn ($s) => ['id' => $s->id, 'from' => $s->from_peer, 'type' => $s->type, 'payload' => $s->payload])->values(),
            'after' => $signals->max('id') ?? $after,
            'peers' => $this->peerList($m, $peer->id),
            'ended' => in_array($m->fresh()->status, ['completed', 'cancelled'], true),
        ];
    }

    public function leave(MeetingPeer $peer): void
    {
        $peer->forceFill(['left_at' => now()])->saveQuietly();
    }

    /** Finds the caller's peer and checks its secret. */
    public function peer(Meeting $m, ?string $id, ?string $secret): MeetingPeer
    {
        $peer = $id ? MeetingPeer::where('meeting_id', $m->id)->whereKey($id)->whereNull('left_at')->first() : null;
        abort_unless($peer && $secret && hash_equals($peer->secret, $secret), 403, 'Not in this meeting.');

        return $peer;
    }

    /** People in the room now. */
    public function active(Meeting $m): Collection
    {
        return MeetingPeer::where('meeting_id', $m->id)->whereNull('left_at')
            ->where('last_seen_at', '>=', now()->subSeconds(self::PEER_TIMEOUT))->get();
    }

    /** "since" (join time, ms) decides who connects to whom: the one who joined later calls. */
    private function peerList(Meeting $m, string $except): array
    {
        return $this->active($m)->where('id', '!=', $except)
            ->map(fn ($p) => ['id' => $p->id, 'name' => $p->name, 'role' => $p->role, 'since' => $p->created_at->getTimestampMs()])->values()->all();
    }

    /** Users who are in any built-in meeting room right now ("In Meeting" on the dashboards). */
    public static function inRoomUserIds(iterable $userIds): array
    {
        return MeetingPeer::whereIn('user_id', collect($userIds)->all())->whereNull('left_at')
            ->where('last_seen_at', '>=', now()->subSeconds(self::PEER_TIMEOUT))
            ->distinct()->pluck('user_id')->all();
    }

    /** Seconds in which at least two people were in the room together (the real meeting time). */
    public function connectedSeconds(Meeting $m): int
    {
        $events = [];
        foreach (MeetingPeer::where('meeting_id', $m->id)->get(['created_at', 'left_at', 'last_seen_at']) as $p) {
            $from = $p->created_at->getTimestamp();
            $to = min(($p->left_at ?? $p->last_seen_at ?? $p->created_at)->getTimestamp(), ($p->last_seen_at ?? $p->created_at)->getTimestamp() + self::PEER_TIMEOUT);
            if ($to > $from) {
                $events[] = [$from, 1];
                $events[] = [$to, -1];
            }
        }
        usort($events, fn ($a, $b) => $a[0] <=> $b[0] ?: $a[1] <=> $b[1]);

        $total = 0;
        $inRoom = 0;
        $prev = null;
        foreach ($events as [$t, $delta]) {
            if ($inRoom >= 2 && $prev !== null) {
                $total += $t - $prev;
            }
            $inRoom += $delta;
            $prev = $t;
        }

        return $total;
    }

    /** STUN (free, Google) + optional TURN from .env for strict office / mobile networks. */
    public function iceServers(): array
    {
        $servers = [['urls' => ['stun:stun.l.google.com:19302', 'stun:stun1.l.google.com:19302']]];
        if ($turn = config('portal.meeting.turn_url')) {
            $servers[] = ['urls' => array_map('trim', explode(',', $turn)), 'username' => config('portal.meeting.turn_user'), 'credential' => config('portal.meeting.turn_pass')];
        }

        return $servers;
    }
}
