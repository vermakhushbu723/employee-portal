<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** E-mail an employee sends to a lead from the portal (template or own text, optional file). */
class LeadEmail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $mailSubject,
        public string $text,
        public User $sender,
        public ?string $filePath = null,
        public ?string $fileName = null,
        public ?string $replyAddress = null,
    ) {}

    /** Sent from the company mailbox; the customer's reply goes to the employee's own e-mail ID. */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->mailSubject,
            replyTo: $this->replyAddress ? [new \Illuminate\Mail\Mailables\Address($this->replyAddress, $this->sender->publicName())] : [],
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.lead-email');
    }

    public function attachments(): array
    {
        return $this->filePath && is_file($this->filePath)
            ? [Attachment::fromPath($this->filePath)->as($this->fileName ?: basename($this->filePath))]
            : [];
    }
}
