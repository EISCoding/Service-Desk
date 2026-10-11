<?php

use Dotenv\Dotenv;

function envExample(): array
{
    return Dotenv::parse(file_get_contents(base_path('.env.example')));
}

it('lists every variable the application needs', function (string $key) {
    expect(envExample())->toHaveKey($key);
})->with([
    'APP_NAME', 'APP_ENV', 'APP_KEY', 'APP_DEBUG', 'APP_URL', 'APP_TIMEZONE', 'APP_LOCALE',
    'DB_CONNECTION', 'SESSION_DRIVER', 'SESSION_SECURE_COOKIE', 'CACHE_STORE',
    'QUEUE_CONNECTION', 'DB_QUEUE_RETRY_AFTER', 'FILESYSTEM_DISK',
    'MAIL_MAILER', 'MAIL_HOST', 'MAIL_PORT', 'MAIL_USERNAME', 'MAIL_PASSWORD',
    'MAIL_FROM_ADDRESS', 'MAIL_FROM_NAME',
    'SEED_ADMIN_NAME', 'SEED_ADMIN_EMAIL', 'SEED_ADMIN_PASSWORD',
]);

it('uses drivers that work on Plesk without extra services', function () {
    $env = envExample();

    expect($env['QUEUE_CONNECTION'])->toBe('database')
        ->and($env['CACHE_STORE'])->toBeIn(['database', 'file'])
        ->and($env['SESSION_DRIVER'])->toBeIn(['database', 'file'])
        ->and($env)->not->toHaveKey('REDIS_HOST');
});

it('defaults to German locale and Berlin time', function () {
    expect(envExample())
        ->APP_LOCALE->toBe('de')
        ->APP_TIMEZONE->toBe('Europe/Berlin');
});

it('keeps the queue retry window above the worker runtime', function () {
    // The scheduler starts the worker with --max-time=50; a job must not be retried while it still runs.
    expect((int) envExample()['DB_QUEUE_RETRY_AFTER'])->toBeGreaterThan(50);
});
