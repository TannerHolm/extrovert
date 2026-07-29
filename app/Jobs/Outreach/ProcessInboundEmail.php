<?php

namespace App\Jobs\Outreach;

use App\Actions\Outreach\HandleInboundEmail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessInboundEmail implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @param  array<int, string>  $recipients
     * @param  array<string, mixed>  $rawPayload
     */
    public function __construct(
        public array $recipients,
        public ?string $fromEmail,
        public ?string $subject,
        public ?string $body,
        public array $rawPayload = [],
        public ?string $providerMessageId = null,
    ) {
        //
    }

    public function handle(HandleInboundEmail $handler): void
    {
        $handler->handle(
            recipients: $this->recipients,
            fromEmail: $this->fromEmail,
            subject: $this->subject,
            body: $this->body,
            rawPayload: $this->rawPayload,
            providerMessageId: $this->providerMessageId,
        );
    }
}
