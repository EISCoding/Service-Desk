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
    private const CID_HOST = 'cid.servicedesk.invalid';

    private static ?HtmlSanitizer $sanitizer = null;

    public static function sanitize(?string $html): string
    {
        if ($html === null || trim($html) === '') {
            return '';
        }

        // Full HTML mails: only the content of <body> counts.
        if (preg_match('#<body[^>]*>(.*?)(</body>|$)#is', $html, $match)) {
            $html = $match[1];
        }

        // Inline mail images (cid:…) pass the sanitizer under a reserved host and
        // are turned back afterwards; all other images are dropped (privacy,
        // tracking pixels).
        $html = preg_replace('/(<img\b[^>]*\bsrc\s*=\s*["\']?)cid:/i', '$1https://'.self::CID_HOST.'/', $html);

        $clean = self::sanitizer()->sanitize($html);
        $clean = preg_replace_callback(
            '#src="https://'.preg_quote(self::CID_HOST, '#').'/([^"]*)"#',
            fn (array $match) => 'src="cid:'.e(html_entity_decode($match[1], ENT_QUOTES | ENT_HTML5)).'"',
            $clean,
        );
        $clean = preg_replace('/<img(?![^>]*\ssrc=)[^>]*>/i', '', $clean);

        return trim($clean);
    }

    /**
     * Plain text for previews and search (block elements become line breaks).
     */
    public static function toText(?string $html): string
    {
        // Links keep their address when the link text differs from it ("Passwort festlegen (https://…)").
        $html = preg_replace_callback('#<a\s[^>]*href="([^"]+)"[^>]*>(.*?)</a>#is', function (array $m) {
            $url = html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $label = trim(html_entity_decode(strip_tags($m[2]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

            return $label === '' || $label === $url || str_starts_with($url, 'mailto:') ? $m[2] : $m[2].' ('.e($url).')';
        }, (string) $html);

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
                // Only inline images of mails (cid:…), shown through the attachment controller.
                ->allowElement('img', ['src', 'alt', 'width', 'height', 'title'])
                ->allowMediaSchemes(['https'])
                ->allowMediaHosts([self::CID_HOST])
                ->allowRelativeMedias(false)
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
