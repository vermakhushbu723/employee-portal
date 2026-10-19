<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Employee extends Model
{
    protected $fillable = [
        'user_id', 'department_id', 'shift_id', 'manager_id',
        'designation', 'work_type', 'training_from', 'training_to', 'joining_date', 'monthly_salary',
    ];

    protected function casts(): array
    {
        return [
            'training_from' => 'date',
            'training_to' => 'date',
            'joining_date' => 'date',
            'monthly_salary' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }
}
