<?php

namespace App\Models;

use App\Enums\NotificationEvent;
use App\Enums\Theme;
use App\Models\Concerns\LogsActivity;
use App\Support\Html;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'is_active', 'password', 'theme', 'signature', 'ticket_view', 'notification_settings'])]
#[Hidden(['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, LogsActivity, Notifiable, TwoFactorAuthenticatable;

    /**
     * Not written to the activity log (personal preferences and 2FA secrets).
     *
     * @var list<string>
     */
    protected array $activityIgnored = ['theme', 'signature', 'ticket_view', 'notification_settings', 'two_factor_secret', 'two_factor_recovery_codes'];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_active' => true,
        'theme' => 'system',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function signature(): Attribute
    {
        return Attribute::make(set: function (?string $value) {
            $html = Html::sanitize($value);

            return Html::toText($html) === '' ? null : $html;
        });
    }

    /**
     * Whether the user wants to be notified about the event on the channel (database | mail).
     */
    public function wantsNotification(NotificationEvent $event, string $channel): bool
    {
        return (bool) ($this->notification_settings[$event->value][$channel] ?? $event->defaultFor($channel));
    }

    /**
     * Reply body with the user's signature below it (if one is set).
     */
    public function withSignature(string $html): string
    {
        return $this->signature ? $html.'<div><br></div>'.$this->signature : $html;
    }

    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(Group::class);
    }

    /**
     * Ids of the user's groups (memoized per instance).
     *
     * @return list<int>
     */
    public function groupIds(): array
    {
        return once(fn () => $this->groups()->pluck('groups.id')->map(fn ($id) => (int) $id)->all());
    }

    /**
     * Whether the user has finished setting up two-factor authentication.
     */
    public function hasTwoFactorEnabled(): bool
    {
        return $this->two_factor_confirmed_at !== null;
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'is_active' => 'boolean',
            'password' => 'hashed',
            'theme' => Theme::class,
            'notification_settings' => 'array',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }
}
