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
        'employee_code', 'name', 'email', 'mobile', 'role', 'custom_role_id', 'status', 'password', 'last_login_at', 'features', 'location_required',
    ];

    /** Employee sections admin can also give a manager / team leader (and take from an employee). */
    public const FEATURES = [
        'attendance' => ['Punch In / Out & work time', 'fingerprint', 'Punch-in popup, active / inactive time, breaks, shift timer'],
        'leads' => ['My Leads', 'people', 'Own leads list, calls, WhatsApp / email, status'],
        'meetings' => ['Meetings', 'camera-video', 'Book and join customer meetings'],
    ];

    /** How admins appear to employees (notifications, work cards, meetings). */
    public const MANAGEMENT = 'Our Management Team';

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'role' => Role::class,
            'last_login_at' => 'datetime',
            'features' => 'array',
            'password' => 'hashed',
            'last_active_at' => 'datetime',
            'battery_charging' => 'boolean',
            'latitude' => 'float',
            'longitude' => 'float',
            'location_at' => 'datetime',
            'device_at' => 'datetime',
            'location_required' => 'boolean',
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

    public function meetings(): HasMany
    {
        return $this->hasMany(Meeting::class);
    }

    public function locationLogs(): HasMany
    {
        return $this->hasMany(LocationLog::class);
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

    public function lateCheckinRequests(): HasMany
    {
        return $this->hasMany(LateCheckinRequest::class);
    }

    public function customRole(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(CustomRole::class, 'custom_role_id');
    }

    public function workAssignments(): HasMany
    {
        return $this->hasMany(WorkAssignment::class);
    }

    /** Role name shown in the UI: the admin-made role if set, else the base role. */
    public function roleLabel(): string
    {
        return $this->customRole?->name ?? $this->role->label();
    }

    /* ---------------------------------------------------------------- Helpers */

    /** Users whose employee profile reports to this user. */
    public function teamMembers(): Builder
    {
        return static::query()->whereHas('employee', fn (Builder $q) => $q->where('manager_id', $this->id));
    }

    /** Name employees see for an action: admins show as "Our Management Team". */
    public function publicName(): string
    {
        return $this->role->portal() === 'admin' ? self::MANAGEMENT : $this->name;
    }

    /** Employees have every section unless admin removed one; managers / team leaders only the ticked ones. */
    public function hasFeature(string $feature): bool
    {
        if ($this->role->portal() === 'admin') {
            return false;
        }
        if ($this->features === null) {
            return $this->role->portal() === 'employee';
        }

        return in_array($feature, $this->features, true);
    }

    /** Punch in / out, active time, breaks and the status bar apply to this user. */
    public function tracksAttendance(): bool
    {
        return $this->hasFeature('attendance');
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
