<?php

namespace App\Actions\Agreements;

use App\Enums\AgreementStatus;
use App\Mail\AgreementForSignature;
use App\Models\Agreement;
use App\Services\Agreements\AgreementPdfRenderer;
use Illuminate\Support\Facades\Mail;

/**
 * Sends (or resends) an agreement for signature: snapshots the PDF, stores
 * its hash for tamper evidence, and emails the tokenized signing link from
 * the team's verified sending identity.
 */
class SendAgreement
{
    public function __construct(private readonly AgreementPdfRenderer $renderer)
    {
        //
    }

    public function handle(Agreement $agreement, ?string $ip = null, ?string $userAgent = null): Agreement
    {
        $isResend = $agreement->status !== AgreementStatus::Draft;

        if (! $isResend) {
            $snapshot = $this->renderer->renderSnapshot($agreement);

            $agreement->update([
                'status' => AgreementStatus::Sent,
                'pdf_path' => $snapshot['path'],
                'content_hash' => $snapshot['hash'],
                'sent_at' => now(),
                'expires_at' => now()->addDays(14),
            ]);
        } else {
            $agreement->update(['expires_at' => now()->addDays(14)]);
        }

        $from = $agreement->team->sendingFrom();

        Mail::to($agreement->signer_email)->send(new AgreementForSignature(
            agreement: $agreement,
            fromEmail: $from['address'],
            fromName: $from['name'] ?? '',
        ));

        $agreement->recordEvent($isResend ? 'resent' : 'sent', $ip, $userAgent);

        return $agreement;
    }
}
