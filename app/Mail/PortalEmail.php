<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** E-mail written in the Send Email popup (template or own text). Replies go to the sender's own e-mail ID. */
class PortalEmail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $mailSubject,
        public string $text,
        public User $sender,
        public ?string $replyAddress = null,
        public ?string $filePath = null,
        public ?string $fileName = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->mailSubject,
            replyTo: $this->replyAddress ? [new Address($this->replyAddress, $this->sender->publicName())] : [],
        );
    }

    public function content(): Content
    {
        // Same simple layout as lead e-mails
        return new Content(view: 'emails.lead-email');
    }

    public function attachments(): array
    {
        return $this->filePath && is_file($this->filePath)
            ? [Attachment::fromPath($this->filePath)->as($this->fileName ?: basename($this->filePath))]
            : [];
    }
}
