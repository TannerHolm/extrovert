<?php

namespace App\Support;

use Stevebauman\Purify\Facades\Purify;

/**
 * Outreach bodies come in two shapes: plain text (AI drafts, templates,
 * legacy messages) and editor HTML (the WYSIWYG composer). These helpers
 * keep the two worlds straight — HTML is always sanitized before it is
 * stored or sent.
 */
class EmailBody
{
    public static function isHtml(string $body): bool
    {
        return preg_match('/<[a-z][^>]*>/i', $body) === 1;
    }

    /**
     * Sanitize for storage/sending. Plain text passes through untouched so
     * legacy callers and template drafts keep their exact content.
     */
    public static function clean(string $body): string
    {
        return self::isHtml($body) ? Purify::clean($body) : $body;
    }

    /**
     * The HTML flavor for the email's HTML part.
     */
    public static function toHtml(string $cleanBody): string
    {
        return self::isHtml($cleanBody) ? $cleanBody : nl2br(e($cleanBody));
    }

    /**
     * The plain-text flavor for the email's text part, the thread previews,
     * and validation ("is there any actual content here?").
     */
    public static function toText(string $cleanBody): string
    {
        if (! self::isHtml($cleanBody)) {
            return $cleanBody;
        }

        $text = preg_replace('/<br\s*\/?>/i', "\n", $cleanBody);
        $text = preg_replace('/<\/(p|div|h[1-6]|li|blockquote)>/i', "$0\n", $text);
        $text = preg_replace('/<li[^>]*>/i', '- ', $text);

        return trim(html_entity_decode(strip_tags($text), ENT_QUOTES, 'UTF-8'));
    }
}
