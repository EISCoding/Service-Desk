<?php

namespace App\Support;

use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;
use Symfony\Component\HtmlSanitizer\Visitor\AttributeSanitizer\AttributeSanitizerInterface;

/**
 * The few formatting attributes the editor writes: text alignment, text colors
 * from a fixed palette, buttons and placeholder chips. Everything else in
 * "style" is dropped, so stored HTML cannot carry layout or tracking CSS.
 */
final class RichTextAttributes implements AttributeSanitizerInterface
{
    /**
     * Text colors offered by the editor; readable on light surfaces and in mails
     * (dark mode lightens them, see app.css).
     */
    public const COLORS = [
        '#d1242f' => 'Rot',
        '#c2410c' => 'Orange',
        '#1a7f37' => 'Grün',
        '#0b62d6' => 'Blau',
        '#8250df' => 'Lila',
        '#6e7781' => 'Grau',
    ];

    public const ALIGNMENTS = ['left', 'center', 'right', 'justify'];

    /** Allowed values of data-type per element. */
    public const TYPES = ['a' => ['button'], 'span' => ['placeholder']];

    public function getSupportedElements(): ?array
    {
        return null;
    }

    public function getSupportedAttributes(): ?array
    {
        return ['style', 'data-type'];
    }

    public function sanitizeAttribute(string $element, string $attribute, string $value, HtmlSanitizerConfig $config): ?string
    {
        if ($attribute === 'data-type') {
            return in_array($value, self::TYPES[$element] ?? [], true) ? $value : null;
        }

        $kept = [];
        foreach (explode(';', $value) as $declaration) {
            [$property, $setting] = array_map('trim', explode(':', $declaration, 2)) + [1 => ''];
            $property = strtolower($property);
            $setting = strtolower($setting);

            if ($property === 'text-align' && in_array($setting, self::ALIGNMENTS, true)) {
                $kept['text-align'] = $setting;
            } elseif ($property === 'color' && isset(self::COLORS[$hex = self::hex($setting)])) {
                $kept['color'] = $hex;
            }
        }

        return $kept ? implode('; ', array_map(fn (string $property, string $setting) => "{$property}: {$setting}", array_keys($kept), $kept)) : null;
    }

    /**
     * Browsers report colors as rgb(…); the palette is stored as hex.
     */
    private static function hex(string $color): string
    {
        if (preg_match('/^rgb\(\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(\d{1,3})\s*\)$/', $color, $match)) {
            return sprintf('#%02x%02x%02x', ...array_map(fn ($channel) => min(255, (int) $channel), array_slice($match, 1)));
        }

        return $color;
    }
}
