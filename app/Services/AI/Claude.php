<?php

namespace App\Services\AI;

use Anthropic\Client;
use Illuminate\Support\Facades\Log;

/**
 * The one Claude entry point for every AI assist feature. Callers always
 * have a non-AI fallback: draft() returns null when no key is configured or
 * the API call fails, and the caller uses its template instead.
 */
class Claude
{
    public const MODEL = 'claude-opus-5';

    public function enabled(): bool
    {
        return config('services.anthropic.key') !== null;
    }

    /**
     * Ask Claude for a single text completion. Null means "no AI available"
     * — never an exception, so callers degrade gracefully.
     */
    public function draft(string $system, string $prompt, int $maxTokens = 4000): ?string
    {
        if (! $this->enabled()) {
            return null;
        }

        try {
            $client = new Client(apiKey: config('services.anthropic.key'));

            $message = $client->messages->create(
                maxTokens: $maxTokens,
                model: self::MODEL,
                system: $system,
                messages: [['role' => 'user', 'content' => $prompt]],
            );

            $text = collect($message->content)
                ->filter(fn ($block) => ($block->type ?? null) === 'text')
                ->map(fn ($block) => $block->text)
                ->implode("\n");

            return trim($text) !== '' ? trim($text) : null;
        } catch (\Throwable $exception) {
            Log::warning('Claude draft failed; caller will use its fallback.', [
                'error' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Like draft(), but expects a JSON object back and returns it decoded.
     *
     * @return array<string, mixed>|null
     */
    public function draftJson(string $system, string $prompt, int $maxTokens = 2000): ?array
    {
        $raw = $this->draft(
            $system.' Respond ONLY with a single valid JSON object — no markdown fences, no commentary.',
            $prompt,
            $maxTokens,
        );

        if ($raw === null) {
            return null;
        }

        // Tolerate a fenced response despite the instruction.
        $raw = trim(preg_replace('/^```(?:json)?|```$/m', '', $raw));

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : null;
    }
}
