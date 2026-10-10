<?php

namespace App\Support;

/**
 * Accent colors to choose from (Apple system colors). Text and links use the
 * darker variant in light mode and the brighter one in dark mode; buttons
 * (accent-fill) always use the darker variant so white text keeps 4.5:1.
 */
final class AccentColor
{
    /**
     * key => [label, light (also fill), light hover, dark, dark hover]
     */
    public const PALETTE = [
        'blue' => ['Blau', '#0066cc', '#0071e3', '#2997ff', '#5aadff'],
        'purple' => ['Lila', '#8944ab', '#9a55bd', '#cd7cf5', '#da8fff'],
        'pink' => ['Rosa', '#d30f45', '#e0204f', '#ff6482', '#ff7a93'],
        'red' => ['Rot', '#d70015', '#e3242b', '#ff6961', '#ff7b74'],
        'orange' => ['Orange', '#c93400', '#d9480f', '#ff9f0a', '#ffb340'],
        'green' => ['Grün', '#1f7f37', '#248a3d', '#30d158', '#4fdd73'],
        'graphite' => ['Graphit', '#636366', '#6e6e73', '#98989d', '#aeaeb2'],
    ];

    /**
     * CSS overriding the default accent (blue is already defined in app.css).
     */
    public static function css(string $key): ?string
    {
        if ($key === 'blue' || ! isset(self::PALETTE[$key])) {
            return null;
        }

        [, $light, $lightHover, $dark, $darkHover] = self::PALETTE[$key];

        return ":root{--accent:{$light};--accent-hover:{$lightHover};--accent-fill:{$light};--accent-fill-hover:{$lightHover}}"
            .".dark{--accent:{$dark};--accent-hover:{$darkHover};--accent-fill:{$light};--accent-fill-hover:{$lightHover}}";
    }
}
