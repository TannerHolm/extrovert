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
        public string $fromEmail,
        public string $replyToEmail,
        public string $fromName = '',
        public string $replyToName = '',
    ) {}

    public function envelope(): Envelope
    {
        // From is the team's verified sending identity (or the app default); replies go to the rep.
        return new Envelope(
            from: new Address($this->fromEmail, $this->fromName),
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
