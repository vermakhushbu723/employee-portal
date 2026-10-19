<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BreakSession extends Model
{
    protected $fillable = ['attendance_session_id', 'user_id', 'started_at', 'ended_at', 'duration_minutes'];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    public function attendanceSession(): BelongsTo
    {
        return $this->belongsTo(AttendanceSession::class);
    }
}
