<?php

namespace App\Enums;

enum LeaveType: string
{
    case Casual = 'casual';
    case Sick = 'sick';
    case Earned = 'earned';
    case Personal = 'personal';
    case Emergency = 'emergency';
    case WorkFromHome = 'wfh';
    case Unpaid = 'unpaid';

    public function label(): string
    {
        return match ($this) {
            self::Casual => 'Casual Leave',
            self::Sick => 'Sick Leave',
            self::Earned => 'Earned Leave',
            self::Personal => 'Personal Leave',
            self::Emergency => 'Emergency Leave',
            self::WorkFromHome => 'Work From Home Request',
            self::Unpaid => 'Unpaid Leave',
        };
    }

    /** The balance bucket this leave is charged against (null = no balance). */
    public function balanceType(): ?self
    {
        return match ($this) {
            self::Casual, self::Personal, self::Emergency => self::Casual,
            self::Sick => self::Sick,
            self::Earned => self::Earned,
            self::WorkFromHome, self::Unpaid => null,
        };
    }

    /** WFH is a request, not an absence. */
    public function countsAsAbsence(): bool
    {
        return $this !== self::WorkFromHome;
    }

    /** Types that carry a yearly balance. */
    public static function balanced(): array
    {
        return [self::Casual, self::Sick, self::Earned];
    }
}
