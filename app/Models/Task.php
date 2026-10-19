<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Task extends Model
{
    protected $fillable = [
        'title', 'description', 'category', 'priority', 'target_quantity', 'unit',
        'deadline_at', 'is_mandatory', 'rules', 'related_list', 'assigned_by',
    ];

    protected function casts(): array
    {
        return [
            'deadline_at' => 'datetime',
            'is_mandatory' => 'boolean',
            'rules' => 'array',
            'target_quantity' => 'integer',
        ];
    }

    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(TaskAssignment::class);
    }

    /** Label / icon / tone for the category from config/portal.php. */
    public function categoryMeta(): array
    {
        return config("portal.task_categories.{$this->category}")
            ?? ['label' => ucfirst(str_replace('_', ' ', $this->category)), 'icon' => 'briefcase-fill', 'tone' => 'primary'];
    }
}
