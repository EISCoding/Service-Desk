<?php

namespace App\Providers;

use App\Support\EnvFile;
use App\Support\Installation;
use Illuminate\Support\ServiceProvider;
use Throwable;

/**
 * Before the installer has run there may be no database and no app key:
 * sessions and cache use files, jobs run right away, and a missing APP_KEY
 * is created and saved in the .env.
 */
class InstallerServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if (! $this->app->runningInConsole() && ! Installation::installed()) {
            self::prepare();
        }
    }

    public static function prepare(): void
    {
        config(['session.driver' => 'file', 'cache.default' => 'file', 'queue.default' => 'sync']);

        if (blank(config('app.key'))) {
            $key = 'base64:'.base64_encode(random_bytes(32));
            config(['app.key' => $key]);

            try {
                EnvFile::write(['APP_KEY' => $key]);
            } catch (Throwable) {
                // Shown as a failed check on the first installer page.
            }
        }
    }
}
