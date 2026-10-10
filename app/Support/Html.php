<?php

namespace App\Support;

use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * HTML of ticket messages (editor and incoming mails) is sanitized before it
 * is stored: no scripts, no event handlers, no javascript: links, no styles.
 */
final class Html
{
    private static ?HtmlSanitizer $sanitizer = null;

    public static function sanitize(?string $html): string
    {
        if ($html === null || trim($html) === '') {
            return '';
        }

        return trim(self::sanitizer()->sanitize($html));
    }

    /**
     * Plain text for previews and search (block elements become line breaks).
     */
    public static function toText(?string $html): string
    {
        $text = preg_replace('#<(br|/p|/div|/li|/h[1-6]|/blockquote)[^>]*>#i', "\n", (string) $html);
        $text = html_entity_decode(strip_tags((string) $text), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace("/[ \t]+/", ' ', preg_replace("/\n{3,}/", "\n\n", $text)));
    }

    /**
     * Turn plain text (e.g. text-only mails) into safe HTML paragraphs.
     */
    public static function fromText(string $text): string
    {
        $paragraphs = preg_split("/\R{2,}/", trim($text));

        return collect($paragraphs)
            ->map(fn (string $paragraph) => '<p>'.nl2br(e($paragraph), false).'</p>')
            ->implode('');
    }

    private static function sanitizer(): HtmlSanitizer
    {
        return self::$sanitizer ??= new HtmlSanitizer(
            (new HtmlSanitizerConfig)
                ->allowSafeElements()
                ->blockElement('img') // inline images get their own handling with mail import (Phase 4)
                ->allowLinkSchemes(['http', 'https', 'mailto', 'tel'])
                ->allowRelativeLinks(false)
                ->forceAttribute('a', 'rel', 'noopener noreferrer')
                ->forceAttribute('a', 'target', '_blank')
                ->dropAttribute('style', '*')
                ->dropAttribute('class', '*')
                ->withMaxInputLength(2_000_000)
        );
    }
}
