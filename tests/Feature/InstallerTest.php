<?php

use App\Enums\Role;
use App\Livewire\Install\Wizard;
use App\Models\Mailbox;
use App\Models\User;
use App\Providers\InstallerServiceProvider;
use App\Support\AppSettings;
use App\Support\EnvFile;
use App\Support\Installation;
use Dotenv\Dotenv;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;

beforeEach(function () {
    $this->dir = sys_get_temp_dir().'/servicedesk-install-'.uniqid();
    mkdir($this->dir);
    config([
        'servicedesk.installed' => false,
        'servicedesk.install.marker' => $this->dir.'/installed',
        'servicedesk.install.lock' => $this->dir.'/install.lock',
    ]);
    $this->envPath = app()->environmentPath();
    app()->useEnvironmentPath($this->dir);
});

afterEach(function () {
    app()->useEnvironmentPath($this->envPath);
    File::deleteDirectory($this->dir);
});

function env_values(): array
{
    return Dotenv::parse(file_get_contents(EnvFile::path()));
}

it('sends every page of a fresh installation to the installer', function () {
    $this->get('/intern')->assertRedirect('/install');
    $this->get('/portal/login')->assertRedirect('/install');
    $this->get('/install')->assertOk()->assertSee('ServiceDesk einrichten');
    $this->get('/up')->assertOk();
});

it('treats installations with users as installed and closes the installer', function () {
    User::factory()->create();

    $this->get('/install')->assertNotFound();
    expect(Installation::installed())->toBeTrue()
        ->and(file_exists($this->dir.'/installed'))->toBeTrue();
});

it('runs through company, admin and mailbox and closes itself', function () {
    $component = Livewire::test(Wizard::class)
        ->assertSee('Willkommen')
        ->assertSee('PHP 8.3 oder neuer')
        ->call('start')
        // The test database is already migrated: the database step is skipped.
        ->assertSet('step', 'company')
        ->set('companyName', 'Musterfirma GmbH')
        ->set('appUrl', 'https://support.musterfirma.de/')
        ->set('accent', 'green')
        ->call('saveCompany')
        ->assertHasNoErrors()
        ->assertSet('step', 'admin');

    expect(app(AppSettings::class)->get('company_name'))->toBe('Musterfirma GmbH')
        ->and(env_values())->toMatchArray(['APP_URL' => 'https://support.musterfirma.de'])
        // The session cookie is named after APP_NAME: changing it would end the installer session.
        ->and(env_values()['APP_NAME'])->toBe('ServiceDesk');

    $component->set('adminName', 'Anna Admin')
        ->set('adminEmail', 'Anna@Musterfirma.de')
        ->set('adminPassword', 'Sicher2026Passwort')
        ->set('adminPassword_confirmation', 'Sicher2026Passwort')
        ->call('saveAdmin')
        ->assertHasNoErrors()
        ->assertSet('step', 'mailbox');

    expect(User::sole())->email->toBe('anna@musterfirma.de')
        ->and(User::sole()->hasRole(Role::Admin->value))->toBeTrue()
        // Still installing: users exist, but the installer stays open for this browser.
        ->and(Installation::installed())->toBeFalse();

    $component->set('mailboxEmail', 'support@musterfirma.de')
        ->set('imapHost', 'imap.musterfirma.de')
        ->set('imapUsername', 'support@musterfirma.de')
        ->set('imapPassword', 'geheim')
        ->set('smtpHost', 'smtp.musterfirma.de')
        ->set('smtpUsername', 'support@musterfirma.de')
        ->set('smtpPassword', 'geheim')
        ->call('saveMailbox')
        ->assertHasNoErrors()
        ->assertSet('step', 'done')
        ->assertSee('schedule:run');

    expect(Mailbox::sole())->is_default->toBeTrue()->is_active->toBeTrue()
        ->and(Installation::installed())->toBeTrue()
        ->and(file_exists($this->dir.'/install.lock'))->toBeFalse()
        ->and(env_values())->toMatchArray(['APP_ENV' => 'production', 'APP_DEBUG' => 'false', 'MAILBOX_MAILER' => '', 'SESSION_DRIVER' => 'database']);
});

it('validates the admin account and allows skipping the mailbox', function () {
    $component = Livewire::test(Wizard::class)->call('start')
        ->set('companyName', 'Musterfirma')->call('saveCompany')
        ->set('adminName', 'Anna')->set('adminEmail', 'keine-mail')->set('adminPassword', 'kurz')->set('adminPassword_confirmation', 'anders')
        ->call('saveAdmin')
        ->assertHasErrors(['adminEmail', 'adminPassword']);

    $component->set('adminEmail', 'anna@example.com')->set('adminPassword', 'Sicher2026Passwort')->set('adminPassword_confirmation', 'Sicher2026Passwort')
        ->call('saveAdmin')
        ->call('skipMailbox')
        ->assertSet('step', 'done');

    expect(Mailbox::count())->toBe(0)->and(Installation::installed())->toBeTrue();
});

it('reports a database that cannot be reached', function () {
    Livewire::test(Wizard::class)
        ->call('back', 'database')
        ->set('dbDriver', 'mysql')
        ->set('dbHost', '127.0.0.1')
        ->set('dbPort', '1')
        ->set('dbDatabase', 'servicedesk')
        ->set('dbUsername', 'servicedesk')
        ->call('saveDatabase')
        ->assertHasErrors('dbHost')
        ->assertSet('step', 'database');

    // The working test database is still in use.
    expect(config('database.default'))->toBe('sqlite');
});

it('belongs to the browser that started it', function () {
    file_put_contents($this->dir.'/install.lock', 'token-of-another-browser');

    Livewire::test(Wizard::class)
        ->assertSee('läuft bereits in einem anderen Browser')
        ->call('start')
        ->assertForbidden();
});

it('creates the app key and file sessions before the installation', function () {
    config(['app.key' => '', 'session.driver' => 'database']);

    InstallerServiceProvider::prepare();

    expect(config('app.key'))->toStartWith('base64:')
        ->and(config('session.driver'))->toBe('file')
        ->and(env_values()['APP_KEY'])->toBe(config('app.key'));
});

it('writes values the way dotenv reads them', function () {
    EnvFile::write(['DB_PASSWORD' => 'p@ss $word#1', 'APP_NAME' => 'Müller & Söhne', 'QUOTE' => "it's \"so\" \$x", 'EMPTY' => '', 'FLAG' => false]);
    EnvFile::write(['DB_PASSWORD' => 'neu']);

    expect(env_values())->toMatchArray([
        'DB_PASSWORD' => 'neu',
        'APP_NAME' => 'Müller & Söhne',
        'QUOTE' => "it's \"so\" \$x",
        'EMPTY' => '',
        'FLAG' => 'false',
    ])->and(preg_match_all('/^DB_PASSWORD=/m', file_get_contents(EnvFile::path())))->toBe(1);
});
