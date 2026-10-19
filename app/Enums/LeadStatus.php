<?php

namespace App\Enums;

enum LeadStatus: string
{
    case New = 'new';
    case Interested = 'interested';
    case FollowUp = 'follow_up';
    case NotInterested = 'not_interested';
    case Converted = 'converted';
    case Invalid = 'invalid';

    public function label(): string
    {
        return match ($this) {
            self::New => 'New',
            self::Interested => 'Interested',
            self::FollowUp => 'Follow-up',
            self::NotInterested => 'Not Interested',
            self::Converted => 'Converted',
            self::Invalid => 'Invalid',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::New => 'secondary',
            self::Interested, self::Converted => 'success',
            self::FollowUp => 'warning',
            self::NotInterested => 'primary',
            self::Invalid => 'danger',
        };
    }
}
