<?php

namespace App\Services\Agreements;

use App\Models\Agreement;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Renders agreement PDFs. The snapshot taken at send time is immutable and
 * its sha256 hash is stored — that hash is what makes the signed artifact
 * tamper-evident. The executed copy re-renders the same body plus the
 * signature block and audit trail.
 */
class AgreementPdfRenderer
{
    public const DISK = 'local';

    /**
     * Render the immutable snapshot sent for signature.
     *
     * @return array{path: string, hash: string}
     */
    public function renderSnapshot(Agreement $agreement): array
    {
        $pdf = $this->pdf($agreement, signed: false);
        $path = $this->basePath($agreement).'.pdf';

        Storage::disk(self::DISK)->put($path, $pdf);

        return ['path' => $path, 'hash' => hash('sha256', $pdf)];
    }

    /**
     * Render the executed copy: body + signature block + audit page.
     */
    public function renderSigned(Agreement $agreement): string
    {
        $pdf = $this->pdf($agreement, signed: true);
        $path = $this->basePath($agreement).'-signed.pdf';

        Storage::disk(self::DISK)->put($path, $pdf);

        return $path;
    }

    private function pdf(Agreement $agreement, bool $signed): string
    {
        return Pdf::loadView('pdf.agreement', [
            'agreement' => $agreement,
            'bodyHtml' => Str::markdown($agreement->body_markdown),
            'signed' => $signed,
            'events' => $signed ? $agreement->events()->get() : collect(),
        ])->output();
    }

    private function basePath(Agreement $agreement): string
    {
        return "agreements/{$agreement->team_id}/agreement-{$agreement->id}";
    }
}
