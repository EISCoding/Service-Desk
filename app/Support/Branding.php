<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Logos, app icons and favicon from the admin area ("Erscheinungsbild").
 * Uploads and generated icons live on the private local disk and are served by
 * BrandingAssetController; without uploads the bundled icons in public/icons apply.
 */
class Branding
{
    public const DIRECTORY = 'branding';

    public const DEFAULT_ICON_BACKGROUND = '#ffffff';

    /** Original app icon upload, kept to regenerate the icons. */
    private const SOURCE = self::DIRECTORY.'/app-icon-source';

    /** Files that can be served under /branding/{asset}. */
    public const ASSETS = [
        'logo-dark', 'favicon', 'badge-96.png',
        'icon-any-192.png', 'icon-any-512.png', 'icon-192.png', 'icon-512.png', 'apple-touch-icon.png',
    ];

    public function __construct(private AppSettings $settings) {}

    public function logoUrl(): ?string
    {
        return $this->settings->logoUrl();
    }

    /**
     * Logo for dark surfaces; falls back to the regular logo.
     */
    public function darkLogoUrl(): ?string
    {
        return $this->settings->get('logo_dark')
            ? route('branding.asset', ['asset' => 'logo-dark', 'v' => $this->settings->get('logo_version')])
            : $this->logoUrl();
    }

    public function hasDarkLogo(): bool
    {
        return (bool) $this->settings->get('logo_dark');
    }

    public function hasAppIcon(): bool
    {
        return (bool) $this->settings->get('app_icon');
    }

    public function iconBackground(): string
    {
        return $this->settings->get('app_icon_background') ?: self::DEFAULT_ICON_BACKGROUND;
    }

    /**
     * URL of an app icon (see IconGenerator::ICONS) or the push badge.
     * Relative URLs are used in the manifest, which must work behind proxies.
     */
    public function iconUrl(string $file, bool $absolute = true): string
    {
        $custom = $this->hasAppIcon() && ($file !== 'badge-96.png' || $this->settings->get('app_icon_badge'));

        if (! $custom) {
            return $absolute ? asset("icons/{$file}") : "/icons/{$file}";
        }

        return route('branding.asset', ['asset' => $file, 'v' => $this->settings->get('icon_version')], $absolute);
    }

    /**
     * Favicon: the uploaded one, else derived from the app icon, else the bundled icon.
     *
     * @return array{url: string, type: string}
     */
    public function favicon(): array
    {
        if ($path = $this->settings->get('favicon')) {
            return [
                'url' => route('branding.asset', ['asset' => 'favicon', 'v' => $this->settings->get('icon_version')]),
                'type' => str_ends_with($path, '.ico') ? 'image/x-icon' : 'image/png',
            ];
        }

        return ['url' => $this->iconUrl('icon-any-192.png'), 'type' => 'image/png'];
    }

    /**
     * Storage path of a servable asset, null when it does not exist.
     */
    public function path(string $asset): ?string
    {
        $path = match ($asset) {
            'logo' => $this->settings->get('logo'),
            'logo-dark' => $this->settings->get('logo_dark'),
            'favicon' => $this->settings->get('favicon'),
            default => in_array($asset, self::ASSETS, true) && $this->hasAppIcon() ? self::DIRECTORY."/icons/{$asset}" : null,
        };

        return $path && Storage::disk('local')->exists($path) ? $path : null;
    }

    public function storeLogo(UploadedFile $file, bool $dark = false): void
    {
        $this->removeLogo($dark);

        $path = $file->storeAs(self::DIRECTORY, ($dark ? 'logo-dark.' : 'logo.').$file->extension(), 'local');
        $this->settings->set([$dark ? 'logo_dark' : 'logo' => $path, 'logo_version' => now()->timestamp]);
    }

    public function removeLogo(bool $dark = false): void
    {
        $key = $dark ? 'logo_dark' : 'logo';

        if ($path = $this->settings->get($key)) {
            Storage::disk('local')->delete($path);
        }

        $this->settings->set([$key => null]);
    }

    /**
     * Stores the source image and generates all icon sizes from it.
     */
    public function storeAppIcon(UploadedFile $file, string $background): void
    {
        $this->generateIcons(file_get_contents($file->getRealPath()), $background);
    }

    /**
     * Regenerates the icons from the stored source, e.g. after changing the background color.
     */
    public function regenerateIcons(string $background): void
    {
        if ($source = $this->appIconSource()) {
            $this->generateIcons($source, $background);
        }
    }

    public function removeAppIcon(): void
    {
        Storage::disk('local')->deleteDirectory(self::DIRECTORY.'/icons');
        Storage::disk('local')->delete(self::SOURCE);

        $this->settings->set(['app_icon' => null, 'app_icon_badge' => null, 'icon_version' => now()->timestamp]);
    }

    public function appIconSourceUrl(): ?string
    {
        return $this->hasAppIcon() ? $this->iconUrl('icon-any-512.png') : null;
    }

    public function storeFavicon(UploadedFile $file): void
    {
        $this->removeFavicon();

        if ($file->extension() === 'ico') {
            $path = $file->storeAs(self::DIRECTORY, 'favicon.ico', 'local');
        } else {
            $path = self::DIRECTORY.'/favicon.png';
            Storage::disk('local')->put($path, app(IconGenerator::class)->favicon(file_get_contents($file->getRealPath())));
        }

        $this->settings->set(['favicon' => $path, 'icon_version' => now()->timestamp]);
    }

    public function removeFavicon(): void
    {
        if ($path = $this->settings->get('favicon')) {
            Storage::disk('local')->delete($path);
        }

        $this->settings->set(['favicon' => null, 'icon_version' => now()->timestamp]);
    }

    private function appIconSource(): ?string
    {
        return Storage::disk('local')->exists(self::SOURCE) ? Storage::disk('local')->get(self::SOURCE) : null;
    }

    private function generateIcons(string $image, string $background): void
    {
        $files = app(IconGenerator::class)->appIcons($image, $background);
        $disk = Storage::disk('local');

        $disk->deleteDirectory(self::DIRECTORY.'/icons');
        foreach ($files as $name => $png) {
            $disk->put(self::DIRECTORY."/icons/{$name}", $png);
        }
        // The original upload is kept to regenerate the icons, e.g. with another background color.
        $disk->put(self::SOURCE, $image);

        $this->settings->set([
            'app_icon' => true,
            'app_icon_badge' => isset($files['badge-96.png']),
            'app_icon_background' => $background,
            'icon_version' => now()->timestamp,
        ]);
    }
}
