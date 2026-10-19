<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class Shift extends Model
{
    protected $fillable = [
        'name', 'start_time', 'end_time', 'required_minutes',
        'break_allowed_minutes', 'grace_minutes', 'checkin_opens_minutes', 'is_default',
    ];

    protected function casts(): array
    {
        return [
            'required_minutes' => 'integer',
            'break_allowed_minutes' => 'integer',
            'grace_minutes' => 'integer',
            'checkin_opens_minutes' => 'integer',
            'is_default' => 'boolean',
        ];
    }

    /** Default shift, or an unsaved 10:00–19:00 shift when none exists. */
    public static function fallback(): self
    {
        return static::query()->where('is_default', true)->first()
            ?? new static([
                'name' => 'General',
                'start_time' => '10:00:00',
                'end_time' => '19:00:00',
                'required_minutes' => 480,
                'break_allowed_minutes' => 60,
                'grace_minutes' => 15,
                'checkin_opens_minutes' => 5,
            ]);
    }

    /** Earliest time check-in is allowed (e.g. 09:55 for a 10:00 shift). */
    public function checkInOpensAt(CarbonInterface $date): Carbon
    {
        return $this->startsAt($date)->subMinutes($this->checkin_opens_minutes ?? 5);
    }

    /** Last on-time check-in (e.g. 10:15). After this, admin approval is needed. */
    public function lateAfter(CarbonInterface $date): Carbon
    {
        return $this->startsAt($date)->addMinutes($this->grace_minutes);
    }

    public function startsAt(CarbonInterface $date): Carbon
    {
        return Carbon::parse($date->toDateString().' '.$this->start_time);
    }

    public function endsAt(CarbonInterface $date): Carbon
    {
        $end = Carbon::parse($date->toDateString().' '.$this->end_time);

        return $end->lte($this->startsAt($date)) ? $end->addDay() : $end;
    }

    public function label(): string
    {
        return Carbon::parse($this->start_time)->format('h:i A').' - '.Carbon::parse($this->end_time)->format('h:i A');
    }
}
