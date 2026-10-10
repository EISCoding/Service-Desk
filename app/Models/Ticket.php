<?php

namespace App\Models;

use App\Enums\TicketPriority;
use App\Enums\TicketSource;
use App\Enums\TicketStatus;
use App\Models\Concerns\LogsActivity;
use Database\Factories\TicketFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Ticket extends Model
{
    /** @use HasFactory<TicketFactory> */
    use HasFactory, LogsActivity;

    protected $fillable = [
        'subject', 'status', 'priority', 'source', 'follow_up_at',
    ];

    /**
     * Bookkeeping columns that are not worth a log entry.
     *
     * @var list<string>
     */
    protected array $activityIgnored = ['last_activity_at', 'follow_up_due'];

    protected $attributes = [
        'status' => 'new',
        'priority' => 'normal',
        'source' => 'internal',
        'follow_up_due' => false,
    ];

    protected function casts(): array
    {
        return [
            'status' => TicketStatus::class,
            'priority' => TicketPriority::class,
            'source' => TicketSource::class,
            'follow_up_at' => 'date',
            'follow_up_due' => 'boolean',
            'closed_at' => 'datetime',
            'last_activity_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Ticket $ticket) {
            $ticket->last_activity_at ??= now();
        });
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function mergedInto(): BelongsTo
    {
        return $this->belongsTo(self::class, 'merged_into_id');
    }

    #[Scope]
    protected function open(Builder $query): void
    {
        $query->where('status', '!=', TicketStatus::Closed->value);
    }

    public function isClosed(): bool
    {
        return $this->status === TicketStatus::Closed;
    }

    /**
     * Record that something happened (sorting by latest activity).
     */
    public function touchActivity(): void
    {
        $this->forceFill(['last_activity_at' => now()])->saveQuietly();
    }
}
