<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A connection message (offer / answer / candidate) or chat line passed between meeting peers. */
class MeetingSignal extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['meeting_id', 'from_peer', 'to_peer', 'type', 'payload'];
}
