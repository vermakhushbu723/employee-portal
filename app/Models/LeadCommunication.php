<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A call (with talk time), WhatsApp / e-mail sent from the portal, or a voice note on a lead. */
class LeadCommunication extends Model
{
    public const CHANNELS = [
        'call' => ['Call', 'telephone-fill', 'success'],
        'whatsapp' => ['WhatsApp', 'whatsapp', 'success'],
        'email' => ['Email', 'envelope-fill', 'primary'],
        'voice_note' => ['Voice note', 'mic-fill', 'danger'],
    ];

    protected $fillable = ['lead_id', 'user_id', 'channel', 'status', 'subject', 'body', 'duration_seconds', 'file_path'];

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function meta(): array
    {
        return self::CHANNELS[$this->channel] ?? ['Note', 'chat-dots', 'secondary'];
    }
}
