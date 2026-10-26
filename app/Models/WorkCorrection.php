<?php

namespace App\Models;

use App\Support\Duration;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A correction of an employee's day (calls, interested, leads created, working time),
 * submitted by admin / manager and approved or rejected by the reviewer.
 * The row itself is the audit history: original + corrected data, who, when, decision.
 */
class WorkCorrection extends Model
{
    /** Fields that can be corrected: key => [label, type] */
    public const FIELDS = [
        'calls' => ['Calls Done', 'count'],
        'interested' => ['Interested Leads', 'count'],
        'leads_created' => ['Leads Created', 'count'],
        'active_seconds' => ['Working Time', 'time'],
    ];

    protected $fillable = [
        'session_id', 'user_id', 'submitted_by', 'reviewer_id', 'original', 'corrected', 'reason', 'details',
        'status', 'reviewed_by', 'reviewed_at', 'review_note', 'emailed_at',
    ];

    protected function casts(): array
    {
        return ['original' => 'array', 'corrected' => 'array', 'reviewed_at' => 'datetime', 'emailed_at' => 'datetime'];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(AttendanceSession::class, 'session_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /** [label, tone] */
    public function badge(): array
    {
        return match ($this->status) {
            'approved' => ['Approved', 'success'],
            'rejected' => ['Rejected', 'danger'],
            default => ['Pending Review', 'warning'],
        };
    }

    public static function format(string $field, $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        return (self::FIELDS[$field][1] ?? 'count') === 'time' ? Duration::hm((int) $value) : (string) (int) $value;
    }
}
