<?php

namespace App\Http\Controllers;

use App\Support\Branding;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Logos, app icons and favicon from the admin area. Public: they appear on login
 * pages, in mails and on home screens. URLs carry a version, so caching is final.
 */
class BrandingAssetController extends Controller
{
    public function __invoke(Branding $branding, string $asset = 'logo'): StreamedResponse
    {
        $path = $branding->path($asset);
        abort_unless($path, 404);

        $disk = Storage::disk('local');
        $type = str_ends_with($path, '.ico') ? 'image/x-icon' : ($disk->mimeType($path) ?: 'application/octet-stream');
        abort_unless(str_starts_with($type, 'image/') && $type !== 'image/svg+xml', 404);

        return $disk->response($path, null, [
            'Content-Type' => $type,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ]);
    }
}
