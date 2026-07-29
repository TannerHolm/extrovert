<?php

namespace App\Mail;

use App\Models\Agreement;
use App\Services\Agreements\AgreementPdfRenderer;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AgreementExecuted extends Mailable
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
            subject: "Fully executed: {$this->agreement->team->name} partnership agreement",
        );
    }

    public function content(): Content
    {
        return new Content(
            text: 'emails.agreement-executed',
            with: ['agreement' => $this->agreement],
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        if ($this->agreement->signed_pdf_path === null) {
            return [];
        }

        return [
            Attachment::fromStorageDisk(AgreementPdfRenderer::DISK, $this->agreement->signed_pdf_path)
                ->as('agreement-signed.pdf')
                ->withMime('application/pdf'),
        ];
    }
}
