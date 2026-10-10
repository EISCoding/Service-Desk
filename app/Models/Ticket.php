<?php

namespace App\Models;

use App\Enums\Permission;
use App\Enums\TicketPriority;
use App\Enums\TicketSource;
use App\Enums\TicketStatus;
use App\Models\Concerns\LogsActivity;
use App\Services\TicketNumberGenerator;
use Database\Factories\TicketFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ticket extends Model
{
    /** @use HasFactory<TicketFactory> */
    use HasFactory, LogsActivity;

    protected $fillable = [
        'subject', 'status', 'priority', 'source', 'follow_up_at', 'requester_email', 'requester_name',
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
        // Messages are removed by the database (cascade); their files need Eloquent.
        static::deleting(function (Ticket $ticket) {
            Attachment::query()
                ->where('attachable_type', 'ticket_message')
                ->whereIn('attachable_id', $ticket->messages()->select('id'))
                ->get()
                ->each->delete();
        });

        static::creating(function (Ticket $ticket) {
            $ticket->number ??= app(TicketNumberGenerator::class)->next();
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

    public function mailbox(): BelongsTo
    {
        return $this->belongsTo(Mailbox::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    /**
     * Conversation in chronological order.
     */
    public function messages(): HasMany
    {
        return $this->hasMany(TicketMessage::class)->oldest()->oldest('id');
    }

    /**
     * On-site appointments (VOS), earliest first.
     */
    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class)->orderBy('starts_at');
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

    /**
     * Tickets the user may see (same rules as TicketPolicy::view).
     */
    #[Scope]
    protected function visibleTo(Builder $query, User $user): void
    {
        if ($user->can(Permission::TicketsViewAll->value)) {
            return;
        }

        $own = $user->can(Permission::TicketsViewOwn->value);
        $group = $user->can(Permission::TicketsViewGroup->value);

        $query->where(function (Builder $query) use ($user, $own, $group) {
            $query->whereRaw('1 = 0');

            if ($own) {
                $query->orWhere('assignee_id', $user->id);
            }

            if ($group) {
                $query->orWhereNull('group_id')->orWhereIn('group_id', $user->groupIds());
            }
        });
    }

    /**
     * How tickets are named everywhere in the UI: "Ticket#2026-00123".
     */
    public function reference(): string
    {
        return self::formatReference($this->number);
    }

    public static function formatReference(string $number): string
    {
        return 'Ticket#'.$number;
    }

    /**
     * Search input may contain the reference ("Ticket#2026-00123"); return the bare term.
     */
    public static function normalizeSearch(string $search): string
    {
        return trim(preg_replace('/^\s*ticket\s*#\s*/i', '', $search));
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
