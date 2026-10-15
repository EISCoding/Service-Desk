<?php

namespace App\Console\Commands;

use BladeUI\Icons\Factory as IconFactory;
use Database\Seeders\MailTemplateSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Console\Command;
use Illuminate\Encryption\Encrypter;

/**
 * Everything deploy.sh does after composer install, as one artisan command.
 * For Plesk without SSH it runs as a scheduled task of type "Run a PHP script"
 * (script: httpdocs/artisan, arguments: servicedesk:deploy).
 */
class Deploy extends Command
{
    protected $signature = 'servicedesk:deploy
        {--no-optimize : Do not build the config, route, view and event caches}';

    protected $description = 'Prepare the application after an update (key, migrations, roles, templates, caches)';

    public function handle(): int
    {
        if (! $this->hasValidKey()) {
            $this->components->task('Generate the application key', fn () => $this->writeNewKey());
        }

        $this->components->info('Database migrations');
        if ($this->call('migrate', ['--force' => true]) !== 0) {
            return self::FAILURE;
        }

        $this->components->task('Roles and permissions', fn () => $this->callSilently('db:seed', ['--class' => RolesAndPermissionsSeeder::class, '--force' => true]) === 0);
        $this->components->task('System templates', fn () => $this->callSilently('db:seed', ['--class' => MailTemplateSeeder::class, '--force' => true]) === 0);

        foreach (['storage/app/private', 'storage/framework/cache', 'storage/framework/sessions', 'storage/framework/views', 'storage/logs'] as $directory) {
            if (! is_dir(base_path($directory))) {
                mkdir(base_path($directory), 0775, true);
            }
        }

        // Caches of the previous version must not leak into the new one: an icon manifest
        // without newly added icon sets, for example, breaks compiling the views.
        $this->components->task('Clear old caches', function () {
            foreach (['icons:clear', 'view:clear', 'route:clear', 'event:clear', 'config:clear'] as $command) {
                if ($this->getApplication()->has($command)) {
                    $this->callSilently($command);
                }
            }
        });

        if (! $this->option('no-optimize')) {
            $this->components->task('Caches', function () {
                // This process registered the icon components while booting, possibly from the
                // old manifest: rebuild it and register them again before the views are compiled.
                if ($this->getApplication()->has('icons:cache') && class_exists(IconFactory::class)) {
                    $this->callSilently('icons:cache');
                    app(IconFactory::class)->registerComponents();
                }

                return $this->callSilently('optimize') === 0;
            });
        }

        // A worker started by the scheduler finishes its current job and exits.
        $this->callSilently('queue:restart');

        $this->components->info('Fertig.');

        return self::SUCCESS;
    }

    private function hasValidKey(): bool
    {
        $key = (string) config('app.key');
        $raw = str_starts_with($key, 'base64:') ? base64_decode(substr($key, 7), true) : $key;

        return is_string($raw) && Encrypter::supported($raw, config('app.cipher'));
    }

    /**
     * Replaces a missing or broken APP_KEY in .env (whatever it contains, even
     * with a cached config) and uses it for the rest of this run.
     */
    private function writeNewKey(): bool
    {
        $key = 'base64:'.base64_encode(Encrypter::generateKey(config('app.cipher')));
        $path = $this->laravel->environmentFilePath();
        $env = is_file($path) ? file_get_contents($path) : '';

        $env = preg_match('/^APP_KEY=.*$/m', $env)
            ? preg_replace('/^APP_KEY=.*$/m', 'APP_KEY='.$key, $env, 1)
            : rtrim($env).PHP_EOL.'APP_KEY='.$key.PHP_EOL;

        file_put_contents($path, $env);
        config(['app.key' => $key]);

        return true;
    }
}
