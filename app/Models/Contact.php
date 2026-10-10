<?php

namespace App\Models;

use App\Enums\Salutation;
use App\Models\Concerns\LogsActivity;
use Database\Factories\ContactFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Contact person of a customer. Logs into the customer portal (guard "contact")
 * when portal access is enabled and a password has been set via invitation.
 */
class Contact extends Authenticatable
{
    /** @use HasFactory<ContactFactory> */
    use HasFactory, LogsActivity, Notifiable;

    protected $fillable = [
        'salutation', 'first_name', 'last_name', 'email', 'phone', 'position', 'is_primary', 'portal_access',
    ];

    protected $hidden = ['password', 'remember_token'];

    /** @var list<string> */
    protected array $activityIgnored = ['invited_at'];

    protected $attributes = [
        'is_primary' => false,
        'portal_access' => false,
    ];

    protected function casts(): array
    {
        return [
            'salutation' => Salutation::class,
            'is_primary' => 'boolean',
            'portal_access' => 'boolean',
            'password' => 'hashed',
            'invited_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function activityParent(): ?Model
    {
        // Explicit query instead of lazy loading (contacts are often handled in collections).
        return $this->relationLoaded('customer') ? $this->customer : $this->customer()->first();
    }

    public function fullName(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    /**
     * Formal German greeting, e.g. "Sehr geehrte Frau Becker".
     */
    public function formalGreeting(): string
    {
        return match ($this->salutation) {
            Salutation::Mr => "Sehr geehrter Herr {$this->last_name}",
            Salutation::Ms => "Sehr geehrte Frau {$this->last_name}",
            default => "Guten Tag {$this->fullName()}",
        };
    }

    /**
     * Portal state for display: active (password set), invited, or not invited yet.
     */
    public function portalStatus(): ?string
    {
        return match (true) {
            ! $this->portal_access => null,
            $this->password !== null => 'active',
            $this->invited_at !== null => 'invited',
            default => 'not_invited',
        };
    }
}
