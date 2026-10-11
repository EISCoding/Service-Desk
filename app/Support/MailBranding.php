<?php

namespace App\Support;

use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Storage;

/**
 * Company name, logo, accent color and texts for the HTML mail layout.
 */
class MailBranding
{
    public const DEFAULT_REPLY_HINT = 'Bitte antworten Sie direkt auf diese E-Mail, damit Ihre Nachricht dem Ticket zugeordnet wird.';

    /** Larger logos are linked instead of embedded, so every mail stays small. */
    private const MAX_EMBED_BYTES = 200 * 1024;

    public function __construct(private AppSettings $settings) {}

    public function name(): string
    {
        return (string) config('app.name');
    }

    /**
     * Darker accent variant: readable on white and with white text on buttons.
     */
    public function accent(): string
    {
        return AccentColor::PALETTE[$this->settings->get('accent')][1] ?? AccentColor::PALETTE['blue'][1];
    }

    public function replyHint(): string
    {
        return trim((string) $this->settings->get('mail_reply_hint')) ?: self::DEFAULT_REPLY_HINT;
    }

    public function footer(): ?string
    {
        return trim((string) $this->settings->get('mail_footer')) ?: null;
    }

    /**
     * Image source of the logo: embedded (cid) when sending, otherwise the public URL.
     */
    public function logo(?Message $message = null): ?string
    {
        $path = $this->settings->get('logo');
        $disk = Storage::disk('local');

        if (! $path || ! $disk->exists($path)) {
            return null;
        }

        if ($message && $disk->size($path) <= self::MAX_EMBED_BYTES) {
            return $message->embedData($disk->get($path), basename($path), $disk->mimeType($path) ?: 'image/png');
        }

        return $this->settings->logoUrl();
    }
}
