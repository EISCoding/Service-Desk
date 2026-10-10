<?php

namespace App\Models;

use App\Enums\MessageType;
use App\Support\Html;
use Database\Factories\TicketMessageFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Entry in a ticket's conversation: public reply, internal note or mail.
 */
class TicketMessage extends Model
{
    /** @use HasFactory<TicketMessageFactory> */
    use HasFactory;

    protected $fillable = ['type', 'body', 'message_id', 'in_reply_to', 'from_email', 'from_name', 'to', 'cc', 'template_key'];

    protected function casts(): array
    {
        return [
            'type' => MessageType::class,
            'to' => 'array',
            'cc' => 'array',
            'sent_at' => 'datetime',
        ];
    }

    /**
     * The body is always stored sanitized.
     */
    protected function body(): Attribute
    {
        return Attribute::make(set: fn (?string $value) => Html::sanitize($value));
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    /**
     * Staff user or contact.
     */
    public function author(): MorphTo
    {
        return $this->morphTo();
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function isInternal(): bool
    {
        return $this->type === MessageType::Internal;
    }

    public function authorName(): string
    {
        return match (true) {
            $this->author instanceof User => $this->author->name,
            $this->author instanceof Contact => $this->author->fullName(),
            default => $this->from_name ?: ($this->from_email ?: 'Unbekannt'),
        };
    }

    public function plainText(): string
    {
        return Html::toText($this->body);
    }
}
