<?php

namespace App\Support;

/**
 * Accent colors to choose from (Apple system colors). Light mode uses the darker,
 * accessible variants so white text on buttons keeps its contrast; light accents
 * in dark mode get dark text on buttons.
 */
final class AccentColor
{
    /**
     * key => [label, light, light hover, dark, dark hover, text on accent in dark mode]
     */
    public const PALETTE = [
        'blue' => ['Blau', '#0071e3', '#0077ed', '#0a84ff', '#409cff', '#ffffff'],
        'purple' => ['Lila', '#8944ab', '#9a55bd', '#bf5af2', '#cd7cf5', '#ffffff'],
        'pink' => ['Rosa', '#d30f45', '#e0204f', '#ff375f', '#ff6482', '#ffffff'],
        'red' => ['Rot', '#d70015', '#e3242b', '#ff453a', '#ff6961', '#ffffff'],
        'orange' => ['Orange', '#c93400', '#d9480f', '#ff9f0a', '#ffb340', '#1d1d1f'],
        'green' => ['Grün', '#248a3d', '#2a9a46', '#30d158', '#4fdd73', '#1d1d1f'],
        'graphite' => ['Graphit', '#6e6e73', '#7c7c81', '#98989d', '#aeaeb2', '#1d1d1f'],
    ];

    /**
     * CSS overriding the default accent (blue is already defined in app.css).
     */
    public static function css(string $key): ?string
    {
        if ($key === 'blue' || ! isset(self::PALETTE[$key])) {
            return null;
        }

        [, $light, $lightHover, $dark, $darkHover, $onDark] = self::PALETTE[$key];

        return ":root{--accent:{$light};--accent-hover:{$lightHover};--on-accent:#ffffff}"
            .".dark{--accent:{$dark};--accent-hover:{$darkHover};--on-accent:{$onDark}}";
    }
}
