<?php

namespace App\Livewire\Install;

use App\Enums\Role;
use App\Mail\Mailboxes\ConnectionTester;
use App\Models\Mailbox;
use App\Models\User;
use App\Support\AccentColor;
use App\Support\AppSettings;
use App\Support\EnvFile;
use App\Support\Installation;
use Database\Seeders\MailTemplateSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Throwable;

/**
 * Browser installer for a fresh deployment: system check, database,
 * company, first admin, mailbox. Afterwards /install is gone.
 */
#[Layout('layouts::installer')]
#[Title('Einrichtung')]
class Wizard extends Component
{
    use WithFileUploads;

    public const STEPS = [
        'welcome' => 'Prüfung',
        'database' => 'Datenbank',
        'company' => 'Firma',
        'admin' => 'Admin',
        'mailbox' => 'Postfach',
        'done' => 'Fertig',
    ];

    #[Locked]
    public string $step = 'welcome';

    /** Another browser is running the installer. */
    #[Locked]
    public bool $blocked = false;

    // Database
    public string $dbDriver = 'mysql';

    public string $dbHost = 'localhost';

    public string $dbPort = '3306';

    public string $dbDatabase = '';

    public string $dbUsername = '';

    public string $dbPassword = '';

    // Company
    public string $companyName = '';

    public string $appUrl = '';

    public string $accent = 'blue';

    public ?TemporaryUploadedFile $logo = null;

    // First admin
    public string $adminName = '';

    public string $adminEmail = '';

    public string $adminPassword = '';

    public string $adminPassword_confirmation = '';

    // Mailbox
    public string $mailboxName = 'Support';

    public string $mailboxEmail = '';

    public string $imapHost = '';

    public int $imapPort = 993;

    public string $imapEncryption = 'ssl';

    public string $imapUsername = '';

    public string $imapPassword = '';

    public string $smtpHost = '';

    public int $smtpPort = 587;

    public string $smtpEncryption = 'tls';

    public string $smtpUsername = '';

    public string $smtpPassword = '';

    /** @var array<string, array{ok: bool, message: string}> */
    public array $testResults = [];

    public function mount(): void
    {
        $token = session('install_token') ?? tap(Str::random(40), fn ($token) => session(['install_token' => $token]));
        $this->blocked = ! Installation::claim($token);

        $this->appUrl = rtrim(url('/'), '/');

        // Values from an existing .env (e.g. filled in by hand before).
        $connection = config('database.default');
        if (in_array($connection, ['mysql', 'mariadb'], true)) {
            $this->dbHost = (string) config("database.connections.{$connection}.host", 'localhost');
            $this->dbPort = (string) config("database.connections.{$connection}.port", '3306');
            $this->dbDatabase = (string) config("database.connections.{$connection}.database", '');
            $this->dbUsername = (string) config("database.connections.{$connection}.username", '');
        }
    }

    /**
     * Requirements of the server; the optional ones only warn.
     *
     * @return list<array{label: string, ok: bool, hint: string, required: bool}>
     */
    #[Computed]
    public function checks(): array
    {
        $missing = array_values(array_filter(['mbstring', 'openssl', 'fileinfo', 'curl', 'ctype', 'tokenizer', 'dom'], fn ($ext) => ! extension_loaded($ext)));
        $disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));

        return [
            ['label' => 'PHP 8.3 oder neuer', 'ok' => version_compare(PHP_VERSION, '8.3.0', '>='), 'hint' => 'Installiert ist PHP '.PHP_VERSION.'. In Plesk unter „PHP-Einstellungen“ umstellen.', 'required' => true],
            ['label' => 'PHP-Erweiterungen', 'ok' => $missing === [], 'hint' => 'Fehlt: '.implode(', ', $missing), 'required' => true],
            ['label' => 'Datenbank-Treiber (MySQL/MariaDB)', 'ok' => extension_loaded('pdo_mysql') || extension_loaded('pdo_sqlite'), 'hint' => 'Die Erweiterung pdo_mysql in den PHP-Einstellungen aktivieren.', 'required' => true],
            ['label' => 'Schreibrechte für storage und bootstrap/cache', 'ok' => is_writable(storage_path()) && is_writable(base_path('bootstrap/cache')), 'hint' => 'In Plesk im Dateimanager „Berechtigungen reparieren“.', 'required' => true],
            ['label' => 'Konfigurationsdatei .env beschreibbar', 'ok' => EnvFile::writable(), 'hint' => 'Die Datei .env (oder der Ordner der Anwendung) muss für PHP beschreibbar sein.', 'required' => true],
            ['label' => 'Hintergrundprozesse (proc_open)', 'ok' => function_exists('proc_open') && ! in_array('proc_open', $disabled, true), 'hint' => 'proc_open aus „disable_functions“ entfernen, sonst laufen Mails und Benachrichtigungen nur beim Seitenaufruf.', 'required' => false],
        ];
    }

    public function canContinue(): bool
    {
        return collect($this->checks)->every(fn (array $check) => $check['ok'] || ! $check['required']);
    }

    public function start(): void
    {
        $this->guard();
        abort_unless($this->canContinue(), 422);

        // A database configured by hand (.env) is used as it is.
        $this->step = Installation::databaseReady() ? 'company' : 'database';
    }

    public function back(string $step): void
    {
        $this->guard();
        abort_unless(array_key_exists($step, self::STEPS) && $step !== 'done' && $this->step !== 'done', 422);
        $this->step = $step;
    }

    public function saveDatabase(): void
    {
        $this->guard();

        $this->validate([
            'dbDriver' => ['required', Rule::in(['mysql', 'sqlite'])],
            'dbHost' => ['required_if:dbDriver,mysql', 'nullable', 'string', 'max:255'],
            'dbPort' => ['required_if:dbDriver,mysql', 'nullable', 'integer', 'between:1,65535'],
            'dbDatabase' => ['required_if:dbDriver,mysql', 'nullable', 'string', 'max:64'],
            'dbUsername' => ['required_if:dbDriver,mysql', 'nullable', 'string', 'max:255'],
            'dbPassword' => ['nullable', 'string', 'max:255'],
        ], attributes: ['dbHost' => 'Server', 'dbPort' => 'Port', 'dbDatabase' => 'Datenbank', 'dbUsername' => 'Benutzer']);

        $env = $this->dbDriver === 'sqlite'
            ? ['DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => database_path('database.sqlite')]
            : ['DB_CONNECTION' => 'mysql', 'DB_HOST' => $this->dbHost, 'DB_PORT' => $this->dbPort, 'DB_DATABASE' => $this->dbDatabase, 'DB_USERNAME' => $this->dbUsername, 'DB_PASSWORD' => $this->dbPassword];

        if ($this->dbDriver === 'sqlite' && ! is_file($env['DB_DATABASE'])) {
            touch($env['DB_DATABASE']);
        }

        // Test on a separate connection; only a working one replaces the configured database.
        $connection = $env['DB_CONNECTION'];
        $settings = [
            ...config("database.connections.{$connection}"),
            ...array_filter([
                'host' => $env['DB_HOST'] ?? null,
                'port' => $env['DB_PORT'] ?? null,
                'database' => $env['DB_DATABASE'],
                'username' => $env['DB_USERNAME'] ?? null,
                'password' => $env['DB_PASSWORD'] ?? null,
            ], fn ($value) => $value !== null),
        ];

        config(['database.connections.installer' => $settings]);
        try {
            DB::connection('installer')->getPdo();
        } catch (Throwable $e) {
            $this->addError('dbHost', 'Keine Verbindung: '.Str::limit($e->getMessage(), 300));

            return;
        } finally {
            DB::purge('installer');
        }

        config(["database.connections.{$connection}" => $settings, 'database.default' => $connection]);
        DB::purge($connection);

        EnvFile::write($env);
        $this->prepareDatabase();
        $this->step = 'company';
    }

    public function saveCompany(AppSettings $settings): void
    {
        $this->guard();

        $this->validate([
            'companyName' => ['required', 'string', 'max:100'],
            'appUrl' => ['required', 'url:http,https', 'max:255'],
            'accent' => ['required', Rule::in(array_keys(AccentColor::PALETTE))],
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:1024'],
        ], attributes: ['companyName' => 'Firmenname', 'appUrl' => 'Adresse', 'accent' => 'Akzentfarbe', 'logo' => 'Logo']);

        $values = ['company_name' => trim($this->companyName), 'accent' => $this->accent];
        if ($this->logo) {
            $values['logo'] = $this->logo->storeAs('branding', 'logo.'.$this->logo->extension(), 'local');
            $values['logo_version'] = now()->timestamp;
        }

        $settings->set($values);
        // Not APP_NAME: the session cookie is named after it, so changing it would end this session.
        // The name lives in the settings and replaces config('app.name') at boot.
        EnvFile::write(['APP_URL' => rtrim($this->appUrl, '/')]);

        $this->step = User::exists() ? 'mailbox' : 'admin';
    }

    public function saveAdmin(): void
    {
        $this->guard();
        $this->adminEmail = Str::lower(trim($this->adminEmail));

        $data = $this->validate([
            'adminName' => ['required', 'string', 'max:255'],
            'adminEmail' => ['required', 'email', 'max:255', 'unique:users,email'],
            'adminPassword' => ['required', 'confirmed', Password::defaults()],
        ], attributes: ['adminName' => 'Name', 'adminEmail' => 'E-Mail-Adresse', 'adminPassword' => 'Passwort']);

        $user = User::create(['name' => $data['adminName'], 'email' => $data['adminEmail'], 'password' => $data['adminPassword']]);
        $user->assignRole(Role::Admin->value);

        $this->reset('adminPassword', 'adminPassword_confirmation');
        $this->mailboxEmail = $this->mailboxEmail ?: '';
        $this->step = 'mailbox';
    }

    public function updatedMailboxEmail(string $value): void
    {
        $this->imapUsername = $this->imapUsername ?: $value;
        $this->smtpUsername = $this->smtpUsername ?: $value;
    }

    public function updatedImapEncryption(string $value): void
    {
        $this->imapPort = $value === 'ssl' ? 993 : 143;
    }

    public function updatedSmtpEncryption(string $value): void
    {
        $this->smtpPort = match ($value) {
            'ssl' => 465,
            'tls' => 587,
            default => 25,
        };
    }

    public function testImap(ConnectionTester $tester): void
    {
        $this->guard();
        $this->validate(array_intersect_key($this->mailboxRules(), array_flip(['imapHost', 'imapPort', 'imapEncryption', 'imapUsername'])));

        $error = $tester->imap(['host' => $this->imapHost, 'port' => $this->imapPort, 'encryption' => $this->imapEncryption, 'username' => $this->imapUsername, 'password' => $this->imapPassword, 'folder' => 'INBOX']);
        $this->testResults['imap'] = ['ok' => $error === null, 'message' => $error ?? 'IMAP-Verbindung erfolgreich.'];
    }

    public function testSmtp(ConnectionTester $tester): void
    {
        $this->guard();
        $this->validate(array_intersect_key($this->mailboxRules(), array_flip(['smtpHost', 'smtpPort', 'smtpEncryption', 'smtpUsername'])));

        $error = $tester->smtp(['host' => $this->smtpHost, 'port' => $this->smtpPort, 'encryption' => $this->smtpEncryption, 'username' => $this->smtpUsername, 'password' => $this->smtpPassword]);
        $this->testResults['smtp'] = ['ok' => $error === null, 'message' => $error ?? 'SMTP-Verbindung erfolgreich.'];
    }

    public function saveMailbox(): void
    {
        $this->guard();
        $this->mailboxEmail = Str::lower(trim($this->mailboxEmail));
        $this->validate($this->mailboxRules(), attributes: [
            'mailboxName' => 'Name', 'mailboxEmail' => 'E-Mail-Adresse', 'imapHost' => 'IMAP-Server', 'imapPort' => 'IMAP-Port',
            'imapUsername' => 'IMAP-Benutzer', 'imapPassword' => 'IMAP-Passwort', 'smtpHost' => 'SMTP-Server', 'smtpPort' => 'SMTP-Port',
            'smtpUsername' => 'SMTP-Benutzer', 'smtpPassword' => 'SMTP-Passwort',
        ]);

        $mailbox = new Mailbox([
            'name' => trim($this->mailboxName), 'email' => $this->mailboxEmail,
            'imap_host' => $this->imapHost, 'imap_port' => $this->imapPort, 'imap_encryption' => $this->imapEncryption,
            'imap_username' => $this->imapUsername, 'imap_password' => $this->imapPassword, 'imap_folder' => 'INBOX',
            'smtp_host' => $this->smtpHost, 'smtp_port' => $this->smtpPort, 'smtp_encryption' => $this->smtpEncryption,
            'smtp_username' => $this->smtpUsername, 'smtp_password' => $this->smtpPassword,
        ]);
        $mailbox->forceFill(['is_active' => true, 'is_default' => true])->save();

        $this->finish();
    }

    public function skipMailbox(): void
    {
        $this->guard();
        $this->finish();
    }

    public function render()
    {
        return view('livewire.install.wizard', [
            'accents' => collect(AccentColor::PALETTE)->map(fn (array $color) => $color[0])->all(),
        ]);
    }

    /**
     * Migrations, roles and system templates (like servicedesk:deploy).
     */
    private function prepareDatabase(): void
    {
        Artisan::call('migrate', ['--force' => true]);
        Artisan::call('db:seed', ['--class' => RolesAndPermissionsSeeder::class, '--force' => true]);
        Artisan::call('db:seed', ['--class' => MailTemplateSeeder::class, '--force' => true]);
    }

    /**
     * Production settings, installer closed.
     */
    private function finish(): void
    {
        EnvFile::write([
            'APP_ENV' => 'production',
            'APP_DEBUG' => false,
            'LOG_LEVEL' => 'warning',
            'SESSION_DRIVER' => 'database',
            'CACHE_STORE' => 'database',
            'QUEUE_CONNECTION' => 'database',
            'MAIL_MAILER' => 'log',
            'MAILBOX_MAILER' => '',
        ]);

        Installation::markInstalled();
        Artisan::call('optimize:clear');

        $this->step = 'done';
    }

    /**
     * Only the browser that started the installer may continue.
     */
    private function guard(): void
    {
        abort_if($this->blocked || ! Installation::owns((string) session('install_token')), 403);
    }

    /**
     * @return array<string, list<mixed>>
     */
    private function mailboxRules(): array
    {
        return [
            'mailboxName' => ['required', 'string', 'max:100'],
            'mailboxEmail' => ['required', 'email', 'max:255'],
            'imapHost' => ['required', 'string', 'max:255'],
            'imapPort' => ['required', 'integer', 'between:1,65535'],
            'imapEncryption' => ['required', Rule::in(array_keys(Mailbox::ENCRYPTIONS))],
            'imapUsername' => ['required', 'string', 'max:255'],
            'imapPassword' => ['required', 'string', 'max:255'],
            'smtpHost' => ['required', 'string', 'max:255'],
            'smtpPort' => ['required', 'integer', 'between:1,65535'],
            'smtpEncryption' => ['required', Rule::in(array_keys(Mailbox::ENCRYPTIONS))],
            'smtpUsername' => ['required', 'string', 'max:255'],
            'smtpPassword' => ['required', 'string', 'max:255'],
        ];
    }
}
