<?php

namespace App\Mail;

use App\Models\AttendanceSession;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DailyWorkReport extends Mailable
{S
    use Queueable, SerializesModels;

    public function __construct(public AttendanceSession $session, public array $work) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Daily Work Report — {$this->session->user->name} ({$this->session->user->employee_code}) — {$this->session->work_date->format('d M Y')}",
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.daily-report');
    }
}
