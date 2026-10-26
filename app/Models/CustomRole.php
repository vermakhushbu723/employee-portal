<?php

namespace App\Models;

use App\Enums\Role;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Admin-made role (e.g. "Recruiter") that works on top of a base portal role. */
class CustomRole extends Model
{
    protected $table = 'roles';

    protected $fillable = ['name', 'base_role', 'description'];

    protected function casts(): array
    {
        return ['base_role' => Role::class];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'custom_role_id');
    }
}
