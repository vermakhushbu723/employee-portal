<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** One-time code to confirm an e-mail ID connected in My Profile. */
class EmailOtp extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public string $code, public string $name) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Your verification code: {$this->code}");
    }

    public function content(): Content
    {
        return new Content(view: 'emails.email-otp');
    }
}
