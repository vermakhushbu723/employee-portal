<?php

namespace App\Models;

use App\Enums\Role;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    protected $fillable = [
        'employee_code', 'name', 'email', 'mobile', 'role', 'status', 'password', 'last_login_at',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'role' => Role::class,
            'last_login_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /* ---------------------------------------------------------------- Relations */

    public function employee(): HasOne
    {
        return $this->hasOne(Employee::class);
    }

    public function attendanceSessions(): HasMany
    {
        return $this->hasMany(AttendanceSession::class);
    }

    public function taskAssignments(): HasMany
    {
        return $this->hasMany(TaskAssignment::class);
    }

    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }

    public function leaveBalances(): HasMany
    {
        return $this->hasMany(LeaveBalance::class);
    }

    public function payrollRecords(): HasMany
    {
        return $this->hasMany(PayrollRecord::class);
    }

    public function targets(): HasMany
    {
        return $this->hasMany(Target::class);
    }

    /* ---------------------------------------------------------------- Helpers */

    /** Users whose employee profile reports to this user. */
    public function teamMembers(): Builder
    {
        return static::query()->whereHas('employee', fn (Builder $q) => $q->where('manager_id', $this->id));
    }

    public function currentShift(): Shift
    {
        return $this->employee?->shift ?? Shift::fallback();
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function hasRole(Role ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    public function homeRoute(): string
    {
        return $this->role->portal().'.dashboard';
    }

    protected function initials(): Attribute
    {
        return Attribute::get(function () {
            $parts = preg_split('/\s+/', trim($this->name));

            return strtoupper(substr($parts[0] ?? '', 0, 1).substr(end($parts) ?: '', 0, 1));
        });
    }
}
