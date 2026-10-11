<?php

namespace App\Http\Controllers;

use App\Support\AppSettings;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Company logo from the system settings (public: also shown on login pages).
 */
class BrandingLogoController extends Controller
{
    public function __invoke(AppSettings $settings): StreamedResponse
    {
        $path = $settings->get('logo');
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, null, [
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ]);
    }
}
