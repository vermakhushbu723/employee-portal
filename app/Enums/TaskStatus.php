<?php

namespace App\Enums;

enum TaskStatus: string
{
    case Assigned = 'assigned';
    case InProgress = 'in_progress';
    case Submitted = 'submitted';
    case Verified = 'verified';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Assigned => 'Pending',
            self::InProgress => 'In Progress',
            self::Submitted => 'Submitted',
            self::Verified => 'Verified',
            self::Rejected => 'Rejected',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Assigned => 'warning',
            self::InProgress => 'primary',
            self::Submitted => 'info',
            self::Verified => 'success',
            self::Rejected => 'danger',
        };
    }

    public function isDone(): bool
    {
        return in_array($this, [self::Submitted, self::Verified], true);
    }
}
