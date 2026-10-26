<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One point of an employee's location trail (Live Tracking). */
class LocationLog extends Model
{
    public $timestamps = false;

    protected $fillable = ['user_id', 'latitude', 'longitude', 'accuracy', 'battery_level', 'recorded_at'];

    protected function casts(): array
    {
        return ['latitude' => 'float', 'longitude' => 'float', 'recorded_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
