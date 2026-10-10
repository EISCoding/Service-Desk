<?php

namespace App\Models;

use App\Enums\MessageType;
use App\Support\Html;
use App\Support\QuotedText;
use Database\Factories\TicketMessageFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Collection;

/**
 * Entry in a ticket's conversation: public reply, internal note or mail.
 */
class TicketMessage extends Model
{
    /** @use HasFactory<TicketMessageFactory> */
    use HasFactory;

    protected $fillable = ['type', 'is_automatic', 'body', 'subject', 'message_id', 'in_reply_to', 'from_email', 'from_name', 'to', 'cc', 'template_key'];

    protected function casts(): array
    {
        return [
            'type' => MessageType::class,
            'is_automatic' => 'boolean',
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

    public function mailFailed(): bool
    {
        return $this->mail_status === 'failed';
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

    /**
     * Body for display: inline images (cid:) point to the attachment controller.
     */
    public function displayBody(): string
    {
        $html = $this->body;

        foreach ($this->attachments as $attachment) {
            if ($attachment->content_id) {
                $html = str_replace('cid:'.$attachment->content_id, e($attachment->url(inline: true)), $html);
            }
        }

        return $html;
    }

    /**
     * Mail bodies split into new text and collapsible quoted history.
     *
     * @return array{0: string, 1: ?string}
     */
    public function displayParts(): array
    {
        $html = $this->displayBody();

        return in_array($this->type, [MessageType::MailIn, MessageType::MailOut], true)
            ? QuotedText::split($html)
            : [$html, null];
    }

    /**
     * Attachments shown as chips (inline images that appear in the text are not repeated).
     *
     * @return Collection<int, Attachment>
     */
    public function listedAttachments(): Collection
    {
        return $this->attachments->reject(fn (Attachment $a) => $a->content_id && str_contains($this->body, 'cid:'.$a->content_id))->values();
    }

    public function plainText(): string
    {
        return Html::toText($this->body);
    }
}
