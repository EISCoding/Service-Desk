<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Number;

/**
 * File attached to a ticket message. Stored privately; downloaded only via
 * AttachmentController after a permission check.
 */
class Attachment extends Model
{
    /** Types the browser may display inline; everything else is downloaded. */
    public const INLINE_TYPES = ['image/png', 'image/jpeg', 'image/gif', 'image/webp', 'application/pdf'];

    protected $fillable = ['disk', 'path', 'original_name', 'mime_type', 'size', 'content_id'];

    protected static function booted(): void
    {
        static::deleted(fn (Attachment $attachment) => Storage::disk($attachment->disk)->delete($attachment->path));
    }

    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }

    public function isImage(): bool
    {
        return in_array($this->mime_type, ['image/png', 'image/jpeg', 'image/gif', 'image/webp'], true);
    }

    public function canBeShownInline(): bool
    {
        return in_array($this->mime_type, self::INLINE_TYPES, true);
    }

    public function humanSize(): string
    {
        return Number::fileSize($this->size, precision: 1);
    }

    public function url(bool $inline = false): string
    {
        return route('intern.attachments.show', ['attachment' => $this, 'inline' => $inline ? 1 : null]);
    }
}
