<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** An e-mail sent from the Send Email popup (Meetings / Email Templates page). */
class SentEmail extends Model
{
    protected $fillable = ['user_id', 'meeting_id', 'lead_id', 'to', 'subject', 'body', 'status'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }
}
