<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Work given to an employee:
 *   calling     — a shared file: call every record, find `interested_target` interested customers
 *   lead_create — create `lead_target` new leads
 */
class WorkAssignment extends Model
{
    protected $fillable = [
        'user_id', 'assigned_by', 'batch_id', 'share_id', 'type', 'title', 'message',
        'total_records', 'interested_target', 'lead_target', 'assigned_at', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(LeadBatch::class, 'batch_id');
    }
}
