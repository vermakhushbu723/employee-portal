<?php

namespace App\Models;

use App\Enums\LeaveStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** An employee's request to check in after the on-time window. Uses the same pending/approved/rejected states as leave. */
class LateCheckinRequest extends Model
{
    protected $fillable = [
        'user_id', 'work_date', 'reason', 'status', 'rejection_reason',
        'actioned_by', 'actioned_at', 'attendance_session_id',
    ];

    protected function casts(): array
    {
        return [
            'work_date' => 'date',
            'status' => LeaveStatus::class,
            'actioned_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actioned_by');
    }
}
