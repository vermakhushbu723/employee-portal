<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Target extends Model
{
    protected $fillable = ['user_id', 'metric', 'period', 'target_value'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
