<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** An e-mail ID a user connected in My Profile (verified with an OTP). Used as "reply to" on e-mails they send. */
class UserEmail extends Model
{
    public const MAX_PER_USER = 4;

    protected $fillable = ['user_id', 'email', 'otp_hash', 'otp_expires_at', 'otp_attempts', 'otp_sent_at', 'verified_at', 'is_primary'];

    protected $hidden = ['otp_hash'];

    protected function casts(): array
    {
        return ['otp_expires_at' => 'datetime', 'otp_sent_at' => 'datetime', 'verified_at' => 'datetime', 'is_primary' => 'boolean'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isVerified(): bool
    {
        return $this->verified_at !== null;
    }
}
