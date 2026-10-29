<?php

namespace App\Enums;

enum LeadStatus: string
{
    case New = 'new';
    case Interested = 'interested';
    case FollowUp = 'follow_up';
    case Callback = 'callback';
    case Meeting = 'meeting';
    case CallNotPick = 'call_not_pick';
    case CallNotAnswer = 'call_not_answer';
    case NotInterested = 'not_interested';
    case Ready = 'ready';
    case OnHold = 'on_hold';
    case Rejected = 'rejected';
    case Converted = 'converted';
    case Invalid = 'invalid';

    /** $hr = HR & Recruitment wording (candidates, interviews, hiring). */
    public function label(bool $hr = false): string
    {
        return match ($this) {
            self::New => 'New',
            self::Interested => 'Interested',
            self::FollowUp => 'Follow-up',
            self::Callback => 'Callback',
            self::Meeting => $hr ? 'Interview Scheduled' : 'Meeting',
            self::CallNotPick => 'Call Not Pick',
            self::CallNotAnswer => 'Call Not Answer',
            self::NotInterested => 'Not Interested',
            self::Ready => 'Ready',
            self::OnHold => 'On Hold',
            self::Rejected => 'Rejected',
            self::Converted => $hr ? 'Hired / Joined' : 'Converted',
            self::Invalid => 'Invalid',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::New => 'secondary',
            self::Interested, self::Converted => 'success',
            self::FollowUp, self::Callback, self::OnHold => 'warning',
            self::Meeting => 'purple',
            self::Ready => 'info',
            self::CallNotPick, self::CallNotAnswer => 'secondary',
            self::NotInterested, self::Rejected => 'danger',
            self::Invalid => 'danger',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::New => 'stars',
            self::Interested => 'hand-thumbs-up-fill',
            self::FollowUp => 'arrow-repeat',
            self::Callback => 'telephone-inbound-fill',
            self::Meeting => 'calendar-event-fill',
            self::CallNotPick => 'telephone-x-fill',
            self::CallNotAnswer => 'telephone-minus-fill',
            self::NotInterested => 'hand-thumbs-down-fill',
            self::Ready => 'check-circle-fill',
            self::OnHold => 'pause-circle-fill',
            self::Rejected => 'x-circle-fill',
            self::Converted => 'trophy-fill',
            self::Invalid => 'x-octagon-fill',
        };
    }

    /** Statuses that count as "interested / mature" in the work score. */
    public static function positive(): array
    {
        return [self::Interested->value, self::Converted->value];
    }

    /** Not offered in the employee's status list (meetings are booked from Meetings; "Call Not Answer" covers no pick-up). */
    public const HIDDEN_FOR_EMPLOYEE = ['meeting', 'call_not_pick'];

    /** Review page: final results are marked in Next Action instead (Converted / Rejected / Invalid …). */
    public const HIDDEN_IN_REVIEW = ['meeting', 'call_not_pick', 'rejected', 'converted', 'invalid'];

    /** Statuses that ask "when to connect next" (date + time). */
    public const NEEDS_NEXT_TIME = ['follow_up', 'callback'];

    /** Options for a status dropdown; the lead's current status is always kept so it still shows. */
    public static function choosable(array $hide, ?self $current = null): array
    {
        return array_values(array_filter(self::cases(), fn ($s) => ! in_array($s->value, $hide, true) || $s === $current));
    }

    /** Statuses an employee can choose (a call was made). */
    public static function workable(): array
    {
        return array_values(array_filter(self::cases(), fn ($s) => $s !== self::New));
    }
}
