<?php

namespace App\Actions\Agreements;

use Anthropic\Client;
use App\Enums\CompensationType;
use App\Models\AgreementTemplate;
use App\Models\Deal;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Produces the draft agreement body for a deal: the team's template merged
 * with the deal's terms, optionally polished by Claude when an API key is
 * configured. Either way the team reviews and edits before anything is sent.
 */
class DraftAgreement
{
    public function handle(Deal $deal): string
    {
        $merged = $this->mergeTemplate($deal);

        $apiKey = config('services.anthropic.key');

        if ($apiKey === null) {
            return $merged;
        }

        try {
            return $this->polish($apiKey, $deal, $merged);
        } catch (\Throwable $exception) {
            // Drafting must never block on the AI layer — the merged template
            // is always a complete, reviewable agreement.
            Log::warning('Agreement draft polish failed; using template merge.', [
                'deal_id' => $deal->id,
                'error' => $exception->getMessage(),
            ]);

            return $merged;
        }
    }

    private function mergeTemplate(Deal $deal): string
    {
        $template = AgreementTemplate::defaultFor($deal->team);
        $influencer = $deal->entry->influencer;

        $replacements = [
            '{{effective_date}}' => now()->toFormattedDateString(),
            '{{brand_name}}' => $deal->team->name,
            '{{influencer_name}}' => $influencer->display_name ?? $influencer->handle,
            '{{influencer_handle}}' => $influencer->handle,
            '{{deliverables_list}}' => $this->deliverablesList($deal),
            '{{compensation_summary}}' => $this->compensationSummary($deal),
            '{{usage_rights}}' => $deal->usage_rights
                ?? 'The Creator grants the Brand a non-exclusive license to reshare the Deliverables on the Brand\'s own social channels, with attribution.',
            '{{start_date}}' => $deal->starts_at?->toFormattedDateString() ?? now()->toFormattedDateString(),
            '{{end_date}}' => $deal->ends_at?->toFormattedDateString() ?? 'the completion of the Deliverables',
            '{{signer_name}}' => $influencer->display_name ?? $influencer->handle,
        ];

        return strtr($template->body_markdown, $replacements);
    }

    private function deliverablesList(Deal $deal): string
    {
        $deliverables = collect($deal->deliverables ?? []);

        if ($deliverables->isEmpty()) {
            return '- Deliverables to be agreed in writing between the parties.';
        }

        return $deliverables->map(function (array $deliverable) {
            $line = sprintf(
                '- One %s on %s',
                $deliverable['type'] ?? 'post',
                ucfirst($deliverable['platform'] ?? ''),
            );

            if (! empty($deliverable['due_date'])) {
                $line .= ', published no later than '.Carbon::parse($deliverable['due_date'])->toFormattedDateString();
            }

            return $line;
        })->implode("\n");
    }

    private function compensationSummary(Deal $deal): string
    {
        $parts = [];

        if ($deal->flat_fee_cents !== null) {
            $parts[] = sprintf('a flat fee of $%s USD', number_format($deal->flat_fee_cents / 100, 2));
        }

        if ($deal->commission_rate !== null) {
            $parts[] = sprintf(
                'a commission of %s%% of attributed revenue generated through the Creator\'s discount code or referral link',
                rtrim(rtrim(number_format((float) $deal->commission_rate, 2), '0'), '.'),
            );
        }

        if ($deal->product_value_cents !== null) {
            $parts[] = sprintf('gifted product with a retail value of $%s USD', number_format($deal->product_value_cents / 100, 2));
        }

        if ($parts === []) {
            $parts[] = match ($deal->compensation_type) {
                CompensationType::Gifted => 'gifted product as agreed between the parties',
                default => 'compensation as agreed in writing between the parties',
            };
        }

        return 'The Brand will provide the Creator with '.implode(', plus ', $parts).'.';
    }

    /**
     * Ask Claude to tailor the merged draft to this deal. The model only
     * refines wording — placeholders are already resolved, and the output is
     * still reviewed by a human before sending.
     */
    private function polish(string $apiKey, Deal $deal, string $merged): string
    {
        $client = new Client(apiKey: $apiKey);

        $message = $client->messages->create(
            maxTokens: 8000,
            model: 'claude-opus-5',
            system: 'You are refining an influencer partnership agreement draft. '
                .'Keep the exact same section structure, headings, parties, amounts, dates, and legal substance. '
                .'Only improve clarity and fill small gaps (e.g. awkward phrasing from template merging). '
                .'Return ONLY the revised agreement as markdown, with no preamble or commentary.',
            messages: [[
                'role' => 'user',
                'content' => "Refine this agreement draft:\n\n".$merged,
            ]],
        );

        $text = collect($message->content)
            ->filter(fn ($block) => ($block->type ?? null) === 'text')
            ->map(fn ($block) => $block->text)
            ->implode("\n");

        return trim($text) !== '' ? trim($text) : $merged;
    }
}
