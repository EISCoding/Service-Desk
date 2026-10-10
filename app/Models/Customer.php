<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use App\Services\CustomerNumberGenerator;
use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    /** @use HasFactory<CustomerFactory> */
    use HasFactory, LogsActivity;

    protected $fillable = [
        'number', 'company', 'street', 'postal_code', 'city', 'country', 'email', 'phone', 'website', 'archived_at',
    ];

    protected $attributes = [
        'country' => 'DE',
    ];

    protected static function booted(): void
    {
        // Automatic customer number unless one was entered manually.
        static::creating(function (Customer $customer) {
            if (blank($customer->number)) {
                $customer->number = app(CustomerNumberGenerator::class)->next();
            }
        });
    }

    protected function casts(): array
    {
        return [
            'archived_at' => 'datetime',
        ];
    }

    #[Scope]
    protected function active(Builder $query): void
    {
        $query->whereNull('archived_at');
    }

    #[Scope]
    protected function archived(Builder $query): void
    {
        $query->whereNotNull('archived_at');
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    public function countryName(): string
    {
        return config("servicedesk.countries.{$this->country}", $this->country);
    }

    /**
     * Address lines for display, e.g. ["Hauptstraße 1", "12345 Berlin"] (+ country abroad).
     *
     * @return list<string>
     */
    public function addressLines(): array
    {
        return array_values(array_filter([
            $this->street,
            trim("{$this->postal_code} {$this->city}"),
            $this->country !== 'DE' ? $this->countryName() : null,
        ]));
    }
}
