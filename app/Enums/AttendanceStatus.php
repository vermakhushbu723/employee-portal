<?php

namespace App\Enums;

enum AttendanceStatus: string
{
    case Present = 'present';
    case Late = 'late';
    case HalfDay = 'half_day';
    case Absent = 'absent';
    case Leave = 'leave';
    case WeeklyOff = 'weekly_off';
    case Holiday = 'holiday';

    public function label(): string
    {
        return match ($this) {
            self::Present => 'Present',
            self::Late => 'Late',
            self::HalfDay => 'Half Day',
            self::Absent => 'Absent',
            self::Leave => 'Leave',
            self::WeeklyOff => 'Weekly Off',
            self::Holiday => 'Holiday',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Present, self::Late => 'success',
            self::HalfDay => 'warning',
            self::Absent => 'secondary',
            self::Leave => 'danger',
            self::WeeklyOff, self::Holiday => 'primary',
        };
    }
}
