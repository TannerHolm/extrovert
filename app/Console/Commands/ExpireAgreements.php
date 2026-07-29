<?php

namespace App\Console\Commands;

use App\Enums\AgreementStatus;
use App\Models\Agreement;
use Illuminate\Console\Command;

/**
 * Voids agreements whose signing window has lapsed, so stale tokens stop
 * working. Teams can re-draft and resend if the deal is still alive.
 */
class ExpireAgreements extends Command
{
    protected $signature = 'extrovert:expire-agreements';

    protected $description = 'Void sent agreements whose expiry date has passed';

    public function handle(): int
    {
        $expired = 0;

        Agreement::query()
            ->whereIn('status', [AgreementStatus::Sent, AgreementStatus::Viewed])
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->chunkById(100, function ($agreements) use (&$expired) {
                foreach ($agreements as $agreement) {
                    $agreement->update(['status' => AgreementStatus::Voided]);
                    $agreement->recordEvent('voided', meta: ['reason' => 'expired']);
                    $expired++;
                }
            });

        $this->info("Voided {$expired} expired agreement(s).");

        return self::SUCCESS;
    }
}
