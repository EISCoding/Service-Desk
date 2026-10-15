<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Web app manifest: "Zum Home-Bildschirm" opens the staff area (or the portal)
 * like an app; on iPhones this is also required for web push.
 */
class ManifestController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $portal = $request->query('start') === 'portal';

        return response()->json([
            'name' => config('app.name').($portal ? ' – Kundenportal' : ''),
            'short_name' => config('app.name'),
            'start_url' => $portal ? '/portal' : '/intern',
            'scope' => '/',
            'display' => 'standalone',
            'background_color' => '#f5f5f7',
            'theme_color' => '#f5f5f7',
            'lang' => 'de',
            'icons' => [
                ['src' => asset('icons/icon-192.png'), 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any maskable'],
                ['src' => asset('icons/icon-512.png'), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any maskable'],
            ],
        ], headers: ['Content-Type' => 'application/manifest+json']);
    }
}
