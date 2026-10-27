<?php

namespace App\Models;

use App\Enums\AttendanceStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AttendanceSession extends Model
{
    protected $fillable = [
        'user_id', 'work_date', 'check_in_at', 'check_out_at', 'status', 'is_late',
        'worked_minutes', 'break_minutes', 'checkout_type', 'checkout_reason',
        'review_status', 'reviewed_by', 'ip_address', 'active_seconds', 'last_heartbeat_at',
        'leads_created', 'interested_count', 'target_leads', 'target_interested', 'work_score', 'grade', 'corrected_by', 'correction_note', 'report_sent_at', 'calls_made', 'corrected_at',
        'logout_requested_at', 'logout_verified_by', 'logout_verified_at', 'logout_note', 'reviewed_at', 'decision_seen_at', 'extension_seconds',
    ];

    protected function casts(): array
    {
        return [
            'work_date' => 'date',
            'check_in_at' => 'datetime',
            'check_out_at' => 'datetime',
            'last_heartbeat_at' => 'datetime',
            'corrected_at' => 'datetime',
            'logout_requested_at' => 'datetime',
            'logout_verified_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'decision_seen_at' => 'datetime',
            'report_sent_at' => 'datetime',
            'work_score' => 'integer',
            'active_seconds' => 'integer',
            'extension_seconds' => 'integer',
            'is_late' => 'boolean',
            'status' => AttendanceStatus::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'logout_verified_by');
    }

    public function breaks(): HasMany
    {
        return $this->hasMany(BreakSession::class)->orderBy('started_at');
    }

    public function activeBreak(): ?BreakSession
    {
        return $this->breaks->firstWhere('ended_at', null);
    }

    /** Exception checkout (logout request) still waiting for management. */
    public function logoutPending(): bool
    {
        return in_array($this->review_status, ['pending', 'verified'], true);
    }

    public function isOpen(): bool
    {
        return $this->check_out_at === null;
    }
}
