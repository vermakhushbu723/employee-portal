<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A customer meeting on Google Meet / Zoom / Teams.
 *   pending   — given by admin/manager, waiting for the employee to accept
 *   upcoming  — booked / accepted
 *   live      — employee joined (shows "In Meeting" on the dashboards)
 *   completed | declined | cancelled
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

    protected $fillable = [
        'user_id', 'created_by', 'lead_id', 'customer_name', 'customer_mobile', 'city', 'title',
        'platform', 'link', 'scheduled_at', 'notes', 'status', 'started_at', 'ended_at', 'room_token',
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
        return ['scheduled_at' => 'datetime', 'started_at' => 'datetime', 'ended_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
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
        if ($this->status === 'upcoming' && $this->scheduled_at->lte(now()) && $this->scheduled_at->gt(now()->subHour())) {
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
