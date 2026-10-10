<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Database\Factories\MailboxFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Support mailbox: fetched via IMAP, replies of its tickets are sent via its SMTP.
 * Passwords are stored encrypted (APP_KEY).
 */
class Mailbox extends Model
{
    /** @use HasFactory<MailboxFactory> */
    use HasFactory, LogsActivity;

    public const ENCRYPTIONS = ['ssl' => 'SSL/TLS', 'tls' => 'STARTTLS', 'none' => 'Keine'];

    protected $fillable = [
        'name', 'email',
        'imap_host', 'imap_port', 'imap_encryption', 'imap_username', 'imap_password', 'imap_folder',
        'processed_action', 'processed_folder',
        'smtp_host', 'smtp_port', 'smtp_encryption', 'smtp_username', 'smtp_password',
        'is_active', 'is_default',
    ];

    protected $hidden = ['imap_password', 'smtp_password'];

    /** @var list<string> */
    protected array $activityIgnored = ['last_fetched_at', 'last_error'];

    protected $attributes = [
        'imap_port' => 993,
        'imap_encryption' => 'ssl',
        'imap_folder' => 'INBOX',
        'processed_action' => 'mark_read',
        'smtp_port' => 587,
        'smtp_encryption' => 'tls',
        'is_active' => true,
        'is_default' => false,
    ];

    protected function casts(): array
    {
        return [
            'imap_password' => 'encrypted',
            'smtp_password' => 'encrypted',
            'imap_port' => 'integer',
            'smtp_port' => 'integer',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
            'last_fetched_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Only one default mailbox.
        static::saved(function (Mailbox $mailbox) {
            if ($mailbox->is_default && ($mailbox->wasRecentlyCreated || $mailbox->wasChanged('is_default'))) {
                static::whereKeyNot($mailbox->id)->where('is_default', true)->update(['is_default' => false]);
            }
        });
    }

    public function defaultGroup(): BelongsTo
    {
        return $this->belongsTo(Group::class, 'default_group_id');
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * Mailbox for tickets created internally or in the portal.
     */
    public static function default(): ?self
    {
        return static::query()->active()->orderByDesc('is_default')->orderBy('id')->first();
    }

    /**
     * Lower-case addresses of all mailboxes (never treated as customers or CC recipients).
     *
     * @return list<string>
     */
    public static function addresses(): array
    {
        return static::pluck('email')->map(fn (string $email) => mb_strtolower($email))->all();
    }
}
