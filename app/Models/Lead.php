<?php

namespace App\Models;

use App\Enums\LeadStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Lead extends Model
{
    protected $fillable = [
        'batch_id', 'share_id', 'created_by', 'assigned_to', 'assigned_by', 'assigned_at', 'name', 'mobile', 'alternate_mobile', 'email', 'state', 'city',
        'source', 'interest', 'remarks', 'extra', 'status', 'follow_up_date', 'converted_at', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => LeadStatus::class,
            'follow_up_date' => 'date',
            'converted_at' => 'datetime',
            'assigned_at' => 'datetime',
            'extra' => 'array',
        ];
    }

    public function latestActivity(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(LeadActivity::class)->latestOfMany();
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(LeadBatch::class, 'batch_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /** Leads an employee created or was assigned. */
    public function scopeOwnedBy(Builder $query, User $user): void
    {
        $query->where(fn (Builder $q) => $q->where('created_by', $user->id)->orWhere('assigned_to', $user->id));
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(LeadActivity::class)->latest();
    }

    protected function initials(): Attribute
    {
        return Attribute::get(function () {
            $parts = preg_split('/\s+/', trim($this->name));

            return strtoupper(substr($parts[0] ?? '', 0, 1).substr(count($parts) > 1 ? end($parts) : '', 0, 1));
        });
    }
}
