<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class Shift extends Model
{
    protected $fillable = [
        'name', 'start_time', 'end_time', 'required_minutes',
        'break_allowed_minutes', 'grace_minutes', 'is_default',
    ];

    protected function casts(): array
    {
        return [
            'required_minutes' => 'integer',
            'break_allowed_minutes' => 'integer',
            'grace_minutes' => 'integer',
            'is_default' => 'boolean',
        ];
    }

    /** Default shift, or an unsaved 09:30–18:30 shift when none exists. */
    public static function fallback(): self
    {
        return static::query()->where('is_default', true)->first()
            ?? new static([
                'name' => 'General',
                'start_time' => '09:30:00',
                'end_time' => '18:30:00',
                'required_minutes' => 480,
                'break_allowed_minutes' => 60,
                'grace_minutes' => 10,
            ]);
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
