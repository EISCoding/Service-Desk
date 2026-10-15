<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * State of the browser installer (/install):
 * - installed: marker file written at the end of the installer, or
 *   SERVICEDESK_INSTALLED=true, or an older installation that already has users
 * - in progress: lock file of the browser that started the installer
 */
final class Installation
{
    public static function installed(): bool
    {
        if (config('servicedesk.installed') || is_file(self::markerPath())) {
            return true;
        }

        // Installations from before the installer: there are already users.
        if (! is_file(self::lockPath()) && self::hasUsers()) {
            self::markInstalled();

            return true;
        }

        return false;
    }

    public static function markInstalled(): void
    {
        @mkdir(dirname(self::markerPath()), 0775, true);
        file_put_contents(self::markerPath(), now()->toIso8601String());
        @unlink(self::lockPath());
    }

    /**
     * The installer belongs to the browser that started it (for two hours).
     */
    public static function claim(string $token): bool
    {
        $path = self::lockPath();

        if (is_file($path) && filemtime($path) > time() - 7200 && trim((string) file_get_contents($path)) !== $token) {
            return false;
        }

        @mkdir(dirname($path), 0775, true);
        file_put_contents($path, $token);

        return true;
    }

    public static function owns(string $token): bool
    {
        return is_file(self::lockPath()) && trim((string) file_get_contents(self::lockPath())) === $token;
    }

    /**
     * Database reachable and migrated.
     */
    public static function databaseReady(): bool
    {
        try {
            return Schema::hasTable('migrations') && Schema::hasTable('users');
        } catch (Throwable) {
            return false;
        }
    }

    private static function hasUsers(): bool
    {
        try {
            return Schema::hasTable('users') && DB::table('users')->exists();
        } catch (Throwable) {
            return false;
        }
    }

    private static function markerPath(): string
    {
        return config('servicedesk.install.marker') ?? storage_path('app/installed');
    }

    private static function lockPath(): string
    {
        return config('servicedesk.install.lock') ?? storage_path('app/install.lock');
    }
}
