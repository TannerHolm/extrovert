<?php

namespace App\Actions\Outreach;

use App\Enums\OutreachStatus;
use App\Models\OutreachMessage;
use App\Models\UnmatchedInboundEmail;
use App\Notifications\Outreach\InboundReplyReceived;
use Illuminate\Support\Str;

/**
 * Routes one inbound email: the reply+<token> recipient identifies the
 * outbound message it answers, which pins down the entry and thread. Mail
 * that can't be matched is parked in a review queue, never dropped.
 */
class HandleInboundEmail
{
    /**
     * @param  array<int, string>  $recipients
     * @param  array<string, mixed>  $rawPayload
     */
    public function handle(
        array $recipients,
        ?string $fromEmail,
        ?string $subject,
        ?string $body,
        array $rawPayload = [],
        ?string $providerMessageId = null,
    ): ?OutreachMessage {
        [$replyAddress, $token] = $this->extractToken($recipients);

        $original = $token !== null
            ? OutreachMessage::where('reply_token', $token)
                ->where('direction', OutreachMessage::DIRECTION_OUTBOUND)
                ->first()
            : null;

        if ($original === null) {
            UnmatchedInboundEmail::create([
                'from_email' => $fromEmail,
                'to_email' => $replyAddress ?? ($recipients[0] ?? null),
                'subject' => $subject,
                'body' => $body,
                'raw_payload' => $rawPayload,
                'received_at' => now(),
            ]);

            return null;
        }

        $entry = $original->entry;

        // Providers retry webhooks; the same message must not appear twice.
        if ($providerMessageId !== null) {
            $duplicate = $entry->messages()
                ->where('direction', OutreachMessage::DIRECTION_INBOUND)
                ->where('provider_message_id', $providerMessageId)
                ->exists();

            if ($duplicate) {
                return null;
            }
        }

        $message = $entry->messages()->create([
            'user_id' => null,
            'direction' => OutreachMessage::DIRECTION_INBOUND,
            'from_email' => $fromEmail ?? '',
            'to_email' => $replyAddress ?? '',
            'subject' => $subject !== null && $subject !== '' ? $subject : 'Re: '.$original->subject,
            'body' => $body ?? '',
            'in_reply_to' => $original->message_id,
            'provider_message_id' => $providerMessageId,
            'status' => 'received',
            'sent_at' => now(),
        ]);

        if ($entry->outreach_status === OutreachStatus::Contacted) {
            $entry->update(['outreach_status' => OutreachStatus::Replied]);
        }

        // Replies are time-sensitive — tell whoever owns this relationship.
        $entry->addedBy?->notify(new InboundReplyReceived($message));

        return $message;
    }

    /**
     * Find the reply+<token>@<domain> recipient among the addresses.
     *
     * @param  array<int, string>  $recipients
     * @return array{0: string|null, 1: string|null}
     */
    private function extractToken(array $recipients): array
    {
        foreach ($recipients as $recipient) {
            if (preg_match('/^reply\+([a-z0-9]+)@/i', trim($recipient), $matches)) {
                return [trim($recipient), Str::lower($matches[1])];
            }
        }

        return [null, null];
    }
}
