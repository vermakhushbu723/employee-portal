<?php

namespace App\Mail;

use App\Models\Meeting;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Meeting invite with date, time and the meeting's own join link. */
class MeetingInvite extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Meeting $meeting, public User $sender) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Meeting invite: {$this->meeting->title} — {$this->meeting->scheduled_at->format('d M Y, h:i A')}");
    }

    public function content(): Content
    {
        return new Content(view: 'emails.meeting-invite');
    }
}
