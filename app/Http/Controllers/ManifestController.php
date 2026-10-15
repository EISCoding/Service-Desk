<?php

namespace App\Http\Controllers;

use App\Support\Branding;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Web app manifest (/manifest.json): "Zum Home-Bildschirm" on iPhone and Android
 * opens the staff area (or the portal) as an app without browser bars; on iPhones
 * this is also required for web push. Name and colors follow the system settings.
 */
class ManifestController extends Controller
{
    public function __invoke(Request $request, Branding $branding): JsonResponse
    {
        $portal = $request->query('start') === 'portal';
        $name = (string) config('app.name');
        $start = $portal ? '/portal' : '/intern';

        $icon = fn (string $file, int $size, string $purpose) => [
            'src' => $branding->iconUrl($file, absolute: false), 'sizes' => "{$size}x{$size}", 'type' => 'image/png', 'purpose' => $purpose,
        ];

        $shortcut = fn (string $label, string $url) => [
            'name' => $label, 'short_name' => $label, 'url' => $url,
            'icons' => [['src' => $branding->iconUrl('icon-any-192.png', absolute: false), 'sizes' => '192x192', 'type' => 'image/png']],
        ];

        return response()->json([
            'id' => $start,
            'name' => $portal ? "{$name} – Kundenportal" : $name,
            'short_name' => $name,
            'description' => $portal ? "Ihre Anfragen an {$name}" : "Tickets und Kunden von {$name}",
            'lang' => 'de',
            'dir' => 'ltr',
            'start_url' => $start,
            'scope' => '/',
            'display' => 'standalone',
            'orientation' => 'any',
            'background_color' => '#f5f5f7',
            'theme_color' => '#f5f5f7',
            'categories' => ['business', 'productivity'],
            'icons' => [
                $icon('icon-any-192.png', 192, 'any'),
                $icon('icon-any-512.png', 512, 'any'),
                $icon('icon-192.png', 192, 'maskable'),
                $icon('icon-512.png', 512, 'maskable'),
                $icon('apple-touch-icon.png', 180, 'any'),
            ],
            'shortcuts' => $portal
                ? [$shortcut('Neues Ticket', '/portal/tickets/neu'), $shortcut('Meine Tickets', '/portal')]
                : [$shortcut('Übersicht', '/intern'), $shortcut('Tickets', '/intern/tickets'), $shortcut('Board', '/intern/tickets/board')],
        ], headers: [
            'Content-Type' => 'application/manifest+json',
            'Cache-Control' => 'public, max-age=3600',
        ], options: JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
