<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollRecord extends Model
{
    protected $fillable = ['user_id', 'leave_request_id', 'record_date', 'type', 'title', 'description', 'amount'];

    protected function casts(): array
    {
        return [
            'record_date' => 'date',
            'amount' => 'float',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function leaveRequest(): BelongsTo
    {
        return $this->belongsTo(LeaveRequest::class);
    }
}
