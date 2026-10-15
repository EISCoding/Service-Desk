<?php

namespace App\Support;

/**
 * Boxicons by name, for icons chosen in PHP or passed to components:
 * "envelope" is the regular icon, "envelope" with $solid the filled one.
 * Names that already carry a set prefix ("bxs-hand") are used as they are,
 * e.g. for icons that only exist in the solid set.
 */
final class Icon
{
    public static function component(string $name, bool $solid = false): string
    {
        if (str_starts_with($name, 'bx-') || str_starts_with($name, 'bxs-')) {
            return $name;
        }

        // Not every icon has a filled variant; fall back to the regular one.
        if ($solid && is_file(resource_path("svg/boxicons/solid/{$name}.svg"))) {
            return 'bxs-'.$name;
        }

        return 'bx-'.$name;
    }
}
