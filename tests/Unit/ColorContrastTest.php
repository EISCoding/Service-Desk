<?php

use App\Support\AccentColor;

/*
| WCAG 2.1 AA: 4.5:1 for normal text. Guards the design tokens in app.css and
| the accent palette against regressions.
*/

function luminance(string $hex): float
{
    $channels = array_map(fn (string $c) => hexdec($c) / 255, str_split(ltrim($hex, '#'), 2));
    [$r, $g, $b] = array_map(fn (float $c) => $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4, $channels);

    return 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;
}

function contrast(string $a, string $b): float
{
    [$light, $dark] = [max(luminance($a), luminance($b)), min(luminance($a), luminance($b))];

    return ($light + 0.05) / ($dark + 0.05);
}

/**
 * @return array<string, string>
 */
function tokens(string $selector): array
{
    preg_match('/^'.preg_quote($selector, '/').' \{(.*?)^\}/ms', file_get_contents(resource_path('css/app.css')), $block);
    preg_match_all('/--([a-z-]+):\s*(#[0-9a-f]{6});/', $block[1], $matches);

    return array_combine($matches[1], $matches[2]);
}

it('keeps text tokens readable in light and dark mode', function (string $selector) {
    $t = tokens($selector);

    foreach (['ink', 'ink-muted', 'ink-faint', 'accent'] as $text) {
        foreach (['surface', 'canvas', 'surface-muted'] as $background) {
            expect(contrast($t[$text], $t[$background]))->toBeGreaterThanOrEqual(4.5, "{$selector}: {$text} on {$background}");
        }
    }

    expect(contrast($t['on-accent'], $t['accent-fill']))->toBeGreaterThanOrEqual(4.5, "{$selector}: button text");
})->with([':root', '.dark']);

it('offers only accent colors with enough contrast', function (string $key) {
    [, $light, , $dark] = AccentColor::PALETTE[$key];

    $lightTokens = tokens(':root');
    $darkTokens = tokens('.dark');

    foreach (['surface', 'canvas', 'surface-muted'] as $background) {
        expect(contrast($light, $lightTokens[$background]))->toBeGreaterThanOrEqual(4.5, "{$key} on light {$background}")
            ->and(contrast($dark, $darkTokens[$background]))->toBeGreaterThanOrEqual(4.5, "{$key} on dark {$background}");
    }

    expect(contrast('#ffffff', $light))->toBeGreaterThanOrEqual(4.5, "{$key} button text");
})->with(array_keys(AccentColor::PALETTE));
