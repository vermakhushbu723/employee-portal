<?php

namespace App\Models;

use App\Enums\LeaveType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveBalance extends Model
{
    protected $fillable = ['user_id', 'year', 'leave_type', 'total', 'used'];

    protected function casts(): array
    {
        return [
            'leave_type' => LeaveType::class,
            'total' => 'float',
            'used' => 'float',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function available(): float
    {
        return max(0, $this->total - $this->used);
    }
}
