<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Announcement extends Model
{
    protected $fillable = ['user_id', 'created_by', 'type', 'title', 'body', 'published_at'];

    protected function casts(): array
    {
        return ['published_at' => 'datetime'];
    }

    /** Company-wide announcements plus the ones addressed to this user. */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        $query->where(fn (Builder $q) => $q->whereNull('user_id')->orWhere('user_id', $user->id))
            ->where('published_at', '<=', now());
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** [icon, tone] for the list UI. */
    public function style(): array
    {
        return match ($this->type) {
            'important' => ['megaphone-fill', 'danger'],
            'alert' => ['exclamation-triangle-fill', 'warning'],
            default => ['file-earmark-text-fill', 'primary'],
        };
    }
}
