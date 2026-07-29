<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Jobs\Outreach\ProcessInboundEmail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InboundEmailWebhookController extends Controller
{
    /**
     * Receive inbound email from the mail provider. Authenticated by a shared
     * token, normalized per message, and queued — nothing is parsed inline.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $expected = config('services.inbound_mail.webhook_token');

        // No token configured means inbound capture is off — hide the endpoint.
        abort_if($expected === null, 404);

        $provided = $request->header('X-Webhook-Token', $request->query('token', ''));

        abort_unless(is_string($provided) && hash_equals($expected, $provided), 401);

        $payload = $request->json()->all();
        $items = isset($payload['items']) && is_array($payload['items']) ? $payload['items'] : [$payload];

        $queued = 0;

        foreach ($items as $item) {
            if (! is_array($item) || $item === []) {
                continue;
            }

            ProcessInboundEmail::dispatch(
                recipients: $this->recipients($item),
                fromEmail: $this->fromAddress($item),
                subject: $item['Subject'] ?? $item['subject'] ?? null,
                body: $item['ExtractedMarkdownMessage'] ?? $item['RawTextBody'] ?? $item['text'] ?? $item['body'] ?? null,
                rawPayload: $item,
                providerMessageId: $item['MessageId'] ?? $item['message_id'] ?? null,
            );
            $queued++;
        }

        return response()->json(['ok' => true, 'queued' => $queued]);
    }

    /**
     * Collect recipient addresses across the provider's possible shapes
     * (Brevo sends both `Recipients` strings and `To` objects).
     *
     * @param  array<string, mixed>  $item
     * @return array<int, string>
     */
    private function recipients(array $item): array
    {
        return collect([...($item['Recipients'] ?? []), ...($item['To'] ?? []), $item['to'] ?? null])
            ->flatten(1)
            ->map(fn ($recipient) => is_array($recipient) ? ($recipient['Address'] ?? null) : $recipient)
            ->filter(fn ($address) => is_string($address) && $address !== '')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function fromAddress(array $item): ?string
    {
        $from = $item['From'] ?? $item['from'] ?? null;

        if (is_array($from)) {
            return $from['Address'] ?? $from['address'] ?? null;
        }

        return is_string($from) ? $from : null;
    }
}
