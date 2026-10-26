<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Department extends Model
{
    protected $fillable = ['name', 'code', 'description', 'status', 'for_data'];

    protected function casts(): array
    {
        return ['for_data' => 'boolean'];
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    /** Business departments that calling data is uploaded for (plus any department that already has uploads). */
    public function scopeForData(\Illuminate\Database\Eloquent\Builder $query): void
    {
        $query->where('status', 'active')
            ->where(fn ($q) => $q->where('for_data', true)
                ->orWhereIn('id', LeadBatch::query()->whereNotNull('department_id')->select('department_id')))
            ->orderBy('name');
    }

    /** Bootstrap icon for the department (sidebar, cards, headers). */
    public function icon(): string
    {
        $name = strtolower($this->name);

        return match (true) {
            str_contains($name, 'real') => 'house-door-fill',
            str_contains($name, 'loan') => 'cash-coin',
            str_contains($name, 'credit') => 'credit-card-2-front-fill',
            str_contains($name, 'hr') || str_contains($name, 'human') || str_contains($name, 'recruit') => 'people-fill',
            str_contains($name, 'telecall') || str_contains($name, 'sales') => 'telephone-fill',
            str_contains($name, 'lead') => 'person-plus-fill',
            str_contains($name, 'admin') => 'gear-fill',
            default => 'building',
        };
    }
}
