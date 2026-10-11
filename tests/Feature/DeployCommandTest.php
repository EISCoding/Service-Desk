<?php

use App\Enums\SystemTemplate;
use App\Models\MailTemplate;
use Spatie\Permission\Models\Role;

it('prepares the application in one run', function () {
    MailTemplate::query()->delete();

    $this->artisan('servicedesk:deploy', ['--no-optimize' => true])
        ->expectsOutputToContain('Fertig.')
        ->assertSuccessful();

    expect(MailTemplate::where('type', 'system')->count())->toBe(count(SystemTemplate::cases()))
        ->and(Role::where('name', 'Admin')->exists())->toBeTrue();
});

it('generates the application key only when it is missing', function () {
    $key = config('app.key');

    $this->artisan('servicedesk:deploy', ['--no-optimize' => true])->assertSuccessful();

    expect(config('app.key'))->toBe($key);
});

it('replaces a missing or broken application key in .env', function (string $broken) {
    $dir = sys_get_temp_dir().'/deploy-env-'.uniqid();
    mkdir($dir);
    file_put_contents("{$dir}/.env", "APP_NAME=ServiceDesk\nAPP_KEY={$broken}\nAPP_DEBUG=false\n");
    app()->useEnvironmentPath($dir);
    config(['app.key' => $broken]);

    $this->artisan('servicedesk:deploy', ['--no-optimize' => true])->assertSuccessful();

    preg_match('/^APP_KEY=base64:(.+)$/m', file_get_contents("{$dir}/.env"), $match);
    expect(strlen(base64_decode($match[1])))->toBe(32)
        ->and(substr_count(file_get_contents("{$dir}/.env"), 'APP_KEY='))->toBe(1)
        ->and(file_get_contents("{$dir}/.env"))->toContain('APP_DEBUG=false');

    unlink("{$dir}/.env");
    rmdir($dir);
})->with(['empty' => '', 'prefix only' => 'base64:', 'placeholder' => 'base64:...', 'too short' => 'abc']);
