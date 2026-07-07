<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OutreachEmail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $subjectLine,
        public string $bodyText,
        public string $replyToEmail,
        public string $replyToName = '',
    ) {}

    public function envelope(): Envelope
    {
        // From is the team/app sending identity (config mail.from); replies go to the rep.
        return new Envelope(
            subject: $this->subjectLine,
            replyTo: [new Address($this->replyToEmail, $this->replyToName)],
        );
    }

    public function content(): Content
    {
        return new Content(
            text: 'emails.outreach',
            with: ['body' => $this->bodyText],
        );
    }
}
