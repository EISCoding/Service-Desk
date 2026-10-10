<?php

namespace App\Support;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use PDOException;

/**
 * System settings from the admin area, read once per request (container singleton).
 * Settings that replace config values are applied at boot, so the rest of the
 * code keeps using config('app.name') and config('servicedesk.mail.reopen_days').
 */
class AppSettings
{
    public const DEFAULTS = [
        'company_name' => null,
        'logo' => null,         // path on the local disk
        'logo_version' => null, // cache busting for the logo URL
        'accent' => 'blue',
        'reopen_days' => null,
    ];

    /** @var array<string, mixed>|null */
    private ?array $values = null;

    public function get(string $key): mixed
    {
        return $this->all()[$key] ?? self::DEFAULTS[$key] ?? null;
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        if ($this->values === null) {
            try {
                $this->values = DB::table('settings')->pluck('value', 'key')
                    ->map(fn (?string $value) => $value === null ? null : json_decode($value, true))
                    ->all();
            } catch (QueryException|PDOException) {
                $this->values = []; // not migrated yet (fresh install, early artisan commands)
            }
        }

        return [...self::DEFAULTS, ...$this->values];
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function set(array $values): void
    {
        foreach ($values as $key => $value) {
            DB::table('settings')->updateOrInsert(['key' => $key], ['value' => json_encode($value), 'updated_at' => now(), 'created_at' => now()]);
        }

        $this->values = null;
        $this->apply();
    }

    public function apply(): void
    {
        if ($name = $this->get('company_name')) {
            config(['app.name' => $name]);
        }

        if ($days = $this->get('reopen_days')) {
            config(['servicedesk.mail.reopen_days' => (int) $days]);
        }
    }

    public function logoUrl(): ?string
    {
        return $this->get('logo') ? route('branding.logo', ['v' => $this->get('logo_version')]) : null;
    }
}
