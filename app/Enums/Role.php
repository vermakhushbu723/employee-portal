<?php

namespace App\Enums;

enum Role: string
{
    case SuperAdmin = 'super_admin';
    case Hr = 'hr';
    case Manager = 'manager';
    case TeamLeader = 'team_leader';
    case Employee = 'employee';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super Admin',
            self::Hr => 'HR',
            self::Manager => 'Manager',
            self::TeamLeader => 'Team Leader',
            self::Employee => 'Employee',
        };
    }

    /** Which portal (route prefix + menu) this role lands in. */
    public function portal(): string
    {
        return match ($this) {
            self::SuperAdmin, self::Hr => 'admin',
            self::Manager, self::TeamLeader => 'manager',
            self::Employee => 'employee',
        };
    }
}
