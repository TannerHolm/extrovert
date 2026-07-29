<?php

namespace App\Actions\Agreements;

use App\Enums\AgreementStatus;
use App\Enums\DealStatus;
use App\Jobs\Shopify\ProvisionDealAttribution;
use App\Mail\AgreementExecuted;
use App\Models\Agreement;
use App\Services\Agreements\AgreementPdfRenderer;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Executes a signature: verifies the sent snapshot is untampered, stamps the
 * signature + audit trail into the executed PDF, advances the deal, and
 * emails the executed copy to both parties.
 */
class SignAgreement
{
    public function __construct(private readonly AgreementPdfRenderer $renderer)
    {
        //
    }

    /**
     * @param  array{typed_name: string, consent: bool}  $signature
     */
    public function handle(Agreement $agreement, array $signature, ?string $ip, ?string $userAgent): Agreement
    {
        if (! $agreement->status->isSignable() || $agreement->isExpired()) {
            throw new RuntimeException('This agreement can no longer be signed.');
        }

        // Tamper evidence: the stored snapshot must still hash to what was sent.
        $snapshot = Storage::disk(AgreementPdfRenderer::DISK)->get($agreement->pdf_path);

        if ($agreement->content_hash === null || ! hash_equals($agreement->content_hash, hash('sha256', $snapshot))) {
            throw new RuntimeException('Agreement content failed integrity verification.');
        }

        $agreement->update([
            'status' => AgreementStatus::Signed,
            'signed_at' => now(),
            'signature_payload' => [
                'typed_name' => $signature['typed_name'],
                'consented_to_electronic_signature' => true,
            ],
            'signed_ip' => $ip,
            'signed_user_agent' => $userAgent,
        ]);

        $agreement->recordEvent('signed', $ip, $userAgent, [
            'typed_name' => $signature['typed_name'],
        ]);

        $agreement->update([
            'signed_pdf_path' => $this->renderer->renderSigned($agreement->fresh(['events'])),
        ]);

        // A signed agreement is the paper form of "agreed" — advance the deal
        // and provision attribution handles.
        $deal = $agreement->deal;

        if ($deal->status === DealStatus::Draft) {
            $deal->update(['status' => DealStatus::Agreed]);
            ProvisionDealAttribution::dispatch($deal);
        }

        $from = $agreement->team->sendingFrom();

        $recipients = collect([$agreement->signer_email, $agreement->createdBy?->email])
            ->filter()
            ->unique();

        foreach ($recipients as $recipient) {
            Mail::to($recipient)->send(new AgreementExecuted(
                agreement: $agreement,
                fromEmail: $from['address'],
                fromName: $from['name'] ?? '',
            ));
        }

        return $agreement->refresh();
    }
}
