<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** One "Share" action from Data Upload & Share — listed under Recent Data Shares and can be rolled back. */
class DataShare extends Model
{
    protected $fillable = [
        'shared_by', 'batch_id', 'manager_id', 'message', 'interested_target', 'mode', 'total', 'recipients',
        'rolled_back_at', 'rolled_back_by', 'rolled_back_count',
    ];

    protected function casts(): array
    {
        return ['recipients' => 'array', 'rolled_back_at' => 'datetime'];
    }

    public function sharer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'shared_by');
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(LeadBatch::class, 'batch_id');
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function rolledBackBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rolled_back_by');
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class, 'share_id');
    }
}
