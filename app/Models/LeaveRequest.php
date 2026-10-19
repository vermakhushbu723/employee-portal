<?php

namespace App\Models;

use App\Enums\LeaveStatus;
use App\Enums\LeaveType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveRequest extends Model
{
    protected $fillable = [
        'user_id', 'leave_type', 'from_date', 'to_date', 'days', 'reason', 'status',
        'is_paid', 'rejection_reason', 'actioned_by', 'actioned_at',
    ];

    protected function casts(): array
    {
        return [
            'leave_type' => LeaveType::class,
            'status' => LeaveStatus::class,
            'from_date' => 'date',
            'to_date' => 'date',
            'days' => 'float',
            'is_paid' => 'boolean',
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

    public function isMultiDay(): bool
    {
        return ! $this->from_date->isSameDay($this->to_date);
    }
}
