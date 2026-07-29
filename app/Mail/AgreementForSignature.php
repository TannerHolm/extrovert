<?php

namespace App\Mail;

use App\Models\Agreement;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AgreementForSignature extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Agreement $agreement,
        public string $fromEmail,
        public string $fromName = '',
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address($this->fromEmail, $this->fromName),
            subject: "{$this->agreement->team->name} — partnership agreement for your signature",
        );
    }

    public function content(): Content
    {
        return new Content(
            text: 'emails.agreement-for-signature',
            with: [
                'agreement' => $this->agreement,
                'signUrl' => route('sign.show', ['token' => $this->agreement->sign_token]),
            ],
        );
    }
}
