<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskActivity extends Model
{
    protected $fillable = ['task_assignment_id', 'user_id', 'action', 'quantity', 'note', 'created_at'];

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(TaskAssignment::class, 'task_assignment_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
