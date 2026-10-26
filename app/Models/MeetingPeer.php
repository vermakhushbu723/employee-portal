<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Someone currently in a built-in meeting room (staff or customer). */
class MeetingPeer extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['id', 'meeting_id', 'user_id', 'name', 'role', 'secret', 'last_seen_at', 'left_at'];

    protected $hidden = ['secret'];

    protected function casts(): array
    {
        return ['last_seen_at' => 'datetime', 'left_at' => 'datetime'];
    }
}
