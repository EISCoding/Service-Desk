<?php

use App\Models\MailTemplate;
use Spatie\Permission\Models\Role;

it('prepares the application in one run', function () {
    MailTemplate::query()->delete();

    $this->artisan('servicedesk:deploy', ['--no-optimize' => true])
        ->expectsOutputToContain('Fertig.')
        ->assertSuccessful();

    expect(MailTemplate::where('type', 'system')->count())->toBe(5)
        ->and(Role::where('name', 'Admin')->exists())->toBeTrue();
});

it('generates the application key only when it is missing', function () {
    $key = config('app.key');

    $this->artisan('servicedesk:deploy', ['--no-optimize' => true])->assertSuccessful();

    expect(config('app.key'))->toBe($key);
});
