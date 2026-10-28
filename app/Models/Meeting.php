<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A customer meeting (built-in Portal Meeting, Google Meet / Zoom / Teams).
 *   pending   — given by admin/manager, waiting for the employee to accept
 *   upcoming  — booked / accepted (or re-opened / rescheduled after a review)
 *   live      — started; stays open after everyone leaves until admin / manager / TL reviews it
 *   completed — closed by admin / manager / TL in the review (never automatically)
 *   declined | cancelled
 */
class Meeting extends Model
{
    public const PLATFORMS = [
        // Built-in room: video call inside the dashboard, customer joins with a link (no app / login)
        'portal' => ['Portal Meeting', 'camera-video-fill', null],
        'google_meet' => ['Google Meet', 'camera-video-fill', 'https://meet.google.com/new'],
        'zoom' => ['Zoom', 'camera-reels-fill', 'https://zoom.us/start/videomeeting'],
        'teams' => ['Teams', 'microsoft-teams', 'https://teams.microsoft.com/v2/'],
        'other' => ['Other', 'link-45deg', null],
    ];

    /** Next step marked in the review: [label, tone, icon, lead status it sets]. */
    public const TAGS = [
        'interested' => ['Interested', 'success', 'hand-thumbs-up-fill', 'interested'],
        'follow_up' => ['Follow Up', 'info', 'arrow-repeat', 'follow_up'],
        'hire' => ['Hire', 'success', 'person-check-fill', 'converted'],
        'lead_closed' => ['Lead Closed', 'primary', 'trophy-fill', 'converted'],
        'irrelevant' => ['Irrelevant', 'secondary', 'slash-circle', 'not_interested'],
        'fake_lead' => ['Fake Lead', 'danger', 'exclamation-octagon-fill', 'invalid'],
    ];

    protected $fillable = [
        'user_id', 'created_by', 'lead_id', 'customer_name', 'customer_mobile', 'city', 'title',
        'platform', 'link', 'scheduled_at', 'ends_at', 'notes', 'status', 'started_at', 'ended_at', 'room_token',
        'outcome', 'outcome_note', 'duration_seconds', 'notified', 'tag', 'reviewed_by', 'reviewed_at',
    ];

    protected static function booted(): void
    {
        // Every portal meeting gets a secret join link for the customer
        static::saving(function (Meeting $m) {
            if ($m->platform === 'portal' && ! $m->room_token) {
                $m->room_token = \Illuminate\Support\Str::random(32);
            }
        });
    }

    /** Joins inside the dashboard (built-in room) instead of an outside link. */
    public function isPortal(): bool
    {
        return $this->platform === 'portal';
    }

    /** Link to send to the customer (portal room) or the outside meeting link. */
    public function joinLink(): ?string
    {
        return $this->isPortal() ? route('meet.guest', $this->room_token) : $this->link;
    }

    protected function casts(): array
    {
        return ['scheduled_at' => 'datetime', 'ends_at' => 'datetime', 'started_at' => 'datetime', 'ended_at' => 'datetime', 'reviewed_at' => 'datetime', 'notified' => 'array'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Primary manager + other managers added to this meeting. */
    public function participants(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(User::class, 'meeting_participants')->withPivot('role')->withTimestamps();
    }

    public function peers(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(MeetingPeer::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /** Planned end (meetings booked before "to time" existed: 30 minutes). */
    public function endsAt(): \Illuminate\Support\Carbon
    {
        return $this->ends_at ?? $this->scheduled_at->copy()->addMinutes(30);
    }

    /** "03:10 PM – 03:40 PM" */
    public function timeRange(): string
    {
        return $this->scheduled_at->format('h:i A').' – '.$this->endsAt()->format('h:i A');
    }

    /** Somebody is in the built-in room right now. */
    public function hasPeopleInRoom(): bool
    {
        if (isset($this->attributes['in_room_count'])) {
            return $this->attributes['in_room_count'] > 0;
        }

        return $this->peers()->whereNull('left_at')
            ->where('last_seen_at', '>=', now()->subSeconds(\App\Services\MeetingRoomService::PEER_TIMEOUT))->exists();
    }

    /** Started (or its time is over) and still open: admin / manager / TL must close, re-open or reschedule it. */
    public function needsReview(): bool
    {
        return ($this->status === 'live' && ! $this->hasPeopleInRoom())
            || ($this->status === 'upcoming' && $this->endsAt()->isPast());
    }

    public function tagMeta(): ?array
    {
        return $this->tag ? (self::TAGS[$this->tag] ?? null) : null;
    }

    /** Adds in_room_count (people in the built-in room now) to a meeting query. */
    public function scopeWithRoomCount(Builder $q): Builder
    {
        return $q->withCount(['peers as in_room_count' => fn ($p) => $p->whereNull('left_at')
            ->where('last_seen_at', '>=', now()->subSeconds(\App\Services\MeetingRoomService::PEER_TIMEOUT))]);
    }

    public function primaryManager(): ?User
    {
        return $this->participants->first(fn ($p) => $p->pivot->role === 'primary');
    }

    /** [label, tone, icon] of the outcome, or null while not finished. */
    public function outcomeMeta(): ?array
    {
        return $this->outcome ? (\App\Services\MeetingPeopleService::OUTCOMES[$this->outcome] ?? null) : null;
    }

    /** How long the meeting ran (connected time). */
    public function durationLabel(): ?string
    {
        $s = $this->duration_seconds ?? ($this->started_at && $this->ended_at ? $this->started_at->diffInSeconds($this->ended_at, true) : null);

        return $s === null ? null : \App\Support\Duration::hm((int) $s);
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function scopeUpcoming(Builder $q): Builder
    {
        return $q->whereIn('status', ['upcoming', 'live'])->where('scheduled_at', '>=', today());
    }

    public function platformLabel(): string
    {
        return self::PLATFORMS[$this->platform][0] ?? 'Meeting';
    }

    public function platformIcon(): string
    {
        return self::PLATFORMS[$this->platform][1] ?? 'link-45deg';
    }

    /** [label, tone] for the status badge. */
    public function badge(): array
    {
        if ($this->needsReview()) {
            return [$this->status === 'live' ? 'Awaiting Review' : 'Time Over · Review', 'warning'];
        }
        if ($this->status === 'upcoming' && $this->scheduled_at->lte(now())) {
            return ['Starting Now', 'success'];
        }

        return match ($this->status) {
            'pending' => ['Pending', 'warning'],
            'live' => ['Live Now', 'success'],
            'completed' => ['Completed', 'primary'],
            'declined' => ['Declined', 'danger'],
            'cancelled' => ['Cancelled', 'secondary'],
            default => ['Upcoming', 'info'],
        };
    }
}
