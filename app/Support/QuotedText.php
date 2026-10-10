<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;

/**
 * Splits a mail body into the new text and the quoted history (previous
 * messages), so the history can be collapsed. Works on the DOM, so both
 * parts stay valid HTML.
 */
final class QuotedText
{
    /** Attribution lines that start the quoted history. */
    private const MARKERS = [
        '/^\\s*(Am|On)\\s.{5,250}\\s(schrieb|wrote)\\b.{0,150}:\\s*$/isu',
        '/^\\s*-{2,}\\s*(Ursprüngliche Nachricht|Original Message|Weitergeleitete Nachricht|Forwarded message)\\s*-{2,}/iu',
        '/^\\s*(Von|From):(\\s|$)/iu',
    ];

    /**
     * @return array{0: string, 1: ?string} [new text, quoted history or null]
     */
    public static function split(string $html): array
    {
        if (trim($html) === '') {
            return [$html, null];
        }

        $document = new DOMDocument;
        $document->loadHTML('<?xml encoding="UTF-8"><div id="sd-root">'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NOERROR | LIBXML_NOWARNING);
        $root = $document->getElementById('sd-root');
        if (! $root) {
            return [$html, null];
        }

        $marker = self::findMarker($document, $root);
        if (! $marker) {
            return [$html, null];
        }

        $quoted = self::detach($marker, $root);
        $main = self::inner($root);

        // Nothing left above the quote: show everything.
        if (trim(strip_tags($main)) === '') {
            return [$html, null];
        }

        return [$main, implode('', array_map(fn (DOMNode $node) => $document->saveHTML($node), $quoted))];
    }

    /**
     * Earliest blockquote or attribution line in document order.
     */
    private static function findMarker(DOMDocument $document, DOMElement $root): ?DOMNode
    {
        $xpath = new DOMXPath($document);

        foreach ($xpath->query('.//blockquote | .//text()', $root) as $node) {
            if ($node instanceof DOMElement) {
                return $node;
            }

            $text = $node->textContent;
            foreach (self::MARKERS as $index => $pattern) {
                if (! preg_match($pattern, $text)) {
                    continue;
                }

                // "Von: …" only counts with a "Gesendet/Sent/Date" line close behind (Outlook header block).
                if ($index === 2 && ! preg_match('/(Gesendet|Sent|Datum|Date):/iu', self::textAfter($node, 400))) {
                    continue;
                }

                return self::blockOf($node, $root);
            }
        }

        return null;
    }

    /**
     * The marker text node itself, or its paragraph / bold wrapper.
     */
    private static function blockOf(DOMNode $node, DOMElement $root): DOMNode
    {
        $current = $node;

        while ($current->parentNode && $current->parentNode !== $root
            && in_array(strtolower($current->parentNode->nodeName), ['b', 'strong', 'span', 'font', 'p', 'div'], true)
            && trim($current->parentNode->textContent) === trim($current->textContent)) {
            $current = $current->parentNode;
        }

        return $current;
    }

    private static function textAfter(DOMNode $node, int $length): string
    {
        $text = '';
        for ($current = $node; $current && mb_strlen($text) < $length; $current = self::next($current)) {
            if ($current->nodeType === XML_TEXT_NODE) {
                $text .= ' '.$current->textContent;
            }
        }

        return $text;
    }

    private static function next(DOMNode $node): ?DOMNode
    {
        if ($node->firstChild) {
            return $node->firstChild;
        }

        while ($node && ! $node->nextSibling) {
            $node = $node->parentNode;
        }

        return $node?->nextSibling;
    }

    /**
     * Removes the marker and everything after it (on every level up to the root).
     *
     * @return list<DOMNode>
     */
    private static function detach(DOMNode $marker, DOMElement $root): array
    {
        $nodes = [];
        $current = $marker;

        while ($current && $current !== $root) {
            $parent = $current->parentNode;
            $sibling = $current;
            while ($sibling) {
                $next = $sibling->nextSibling;
                $nodes[] = $parent->removeChild($sibling);
                $sibling = $next;
            }
            // Continue with what follows the parent.
            $current = $parent !== $root ? $parent->nextSibling : null;
            while (! $current && $parent && $parent !== $root) {
                $parent = $parent->parentNode;
                $current = $parent && $parent !== $root ? $parent->nextSibling : null;
            }
        }

        return $nodes;
    }

    private static function inner(DOMElement $element): string
    {
        $html = '';
        foreach ($element->childNodes as $child) {
            $html .= $element->ownerDocument->saveHTML($child);
        }

        return $html;
    }
}
