<?php

namespace App\Models;

use App\Enums\TaskStatus;
use App\Support\Duration;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TaskAssignment extends Model
{
    protected $fillable = [
        'task_id', 'user_id', 'assigned_date', 'completed_quantity', 'status',
        'started_at', 'submitted_at', 'verified_at', 'remarks', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'assigned_date' => 'date',
            'started_at' => 'datetime',
            'submitted_at' => 'datetime',
            'verified_at' => 'datetime',
            'status' => TaskStatus::class,
            'completed_quantity' => 'integer',
        ];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(TaskActivity::class)->latest();
    }

    public function remaining(): int
    {
        return max(0, $this->task->target_quantity - $this->completed_quantity);
    }

    public function progress(): int
    {
        return Duration::percent($this->completed_quantity, $this->task->target_quantity);
    }

    public function isOverdue(): bool
    {
        return ! $this->status->isDone()
            && $this->task->deadline_at !== null
            && $this->task->deadline_at->isPast();
    }

    /** [label, tone] shown on cards; overdue wins over the stored status. */
    public function badge(): array
    {
        if ($this->isOverdue()) {
            return ['Overdue', 'danger'];
        }

        if ($this->status === TaskStatus::Assigned && $this->completed_quantity === 0) {
            return ['Not Started', 'secondary'];
        }

        return [$this->status->label(), $this->status->tone()];
    }
}
