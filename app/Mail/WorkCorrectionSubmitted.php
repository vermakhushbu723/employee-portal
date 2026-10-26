<?php

namespace App\Mail;

use App\Models\WorkCorrection;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Sent to the reviewer right after a work correction is submitted. */
class WorkCorrectionSubmitted extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public WorkCorrection $correction) {}

    public function envelope(): Envelope
    {
        $u = $this->correction->user;

        return new Envelope(
            subject: "Review needed: work data correction — {$u->name} ({$u->employee_code}) — {$this->correction->session->work_date->format('d M Y')}",
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.work-correction');
    }
}
