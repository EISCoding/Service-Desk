<?php

namespace App\Support;

/**
 * Inline styles for editor HTML in mails: many mail clients ignore stylesheets,
 * so buttons, headings and dividers get their look directly on the element.
 */
final class MailHtml
{
    public static function inline(string $html, string $accent): string
    {
        return preg_replace_callback('/<(a|hr|h2|h3)\b([^>]*)>/i', function (array $match) use ($accent) {
            $tag = strtolower($match[1]);
            $attributes = $match[2];

            $style = match ($tag) {
                'a' => str_contains($attributes, 'data-type="button"')
                    ? "display:inline-block;padding:11px 22px;border-radius:10px;background-color:{$accent};color:#ffffff;font-weight:600;text-decoration:none;"
                    : null,
                'hr' => 'border:0;border-top:1px solid #e5e5ea;margin:20px 0;',
                'h2' => 'margin:0 0 12px;font-size:20px;line-height:1.3;color:#1d1d1f;',
                'h3' => 'margin:0 0 8px;font-size:17px;line-height:1.35;color:#1d1d1f;',
            };

            if ($style === null) {
                return $match[0];
            }

            $selfClosing = str_ends_with(rtrim($attributes), '/');
            $attributes = rtrim(rtrim($attributes), '/');

            // Existing styles (alignment, color from the editor) come last and win.
            if (preg_match('/\sstyle="([^"]*)"/i', $attributes, $existing)) {
                $attributes = str_replace($existing[0], ' style="'.$style.$existing[1].'"', $attributes);
            } else {
                $attributes = rtrim($attributes).' style="'.$style.'"';
            }

            return '<'.$match[1].$attributes.($selfClosing ? ' /' : '').'>';
        }, $html) ?? $html;
    }
}
