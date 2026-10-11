<?php

namespace App\Mail\Inbound;

use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Throwable;
use Webklex\PHPIMAP\Address;
use Webklex\PHPIMAP\Attachment;
use Webklex\PHPIMAP\Message;

/**
 * Turns a webklex message (from IMAP or from an .eml file) into an InboundMail.
 */
class InboundMailParser
{
    /** Headers needed for loop protection and threading. */
    private const HEADERS = [
        'auto-submitted', 'x-auto-response-suppress', 'precedence', 'x-autoreply', 'x-autorespond',
        'x-autoresponder', 'list-id', 'list-unsubscribe', 'return-path', 'x-loop', 'x-servicedesk-ticket',
    ];

    public function fromString(string $raw): InboundMail
    {
        return $this->fromMessage(Message::fromString($raw));
    }

    public function fromFile(string $path): InboundMail
    {
        return $this->fromMessage(Message::fromFile($path));
    }

    public function fromMessage(Message $message): InboundMail
    {
        $from = $message->getFrom()->first();
        $rawHeader = (string) $message->getHeader()->raw;

        return new InboundMail(
            messageId: self::normalizeId((string) $message->getMessageId()->first()),
            inReplyTo: self::normalizeId((string) $message->getInReplyTo()->first()),
            references: $this->ids($message->getReferences()->toArray()),
            subject: Str::limit(trim(self::decode((string) $message->getSubject()->first())), 250, '') ?: '(ohne Betreff)',
            fromEmail: Str::lower(trim((string) ($from?->mail ?? ''))),
            fromName: ($name = trim(self::decode((string) ($from?->personal ?? '')), " \"'")) !== '' ? $name : null,
            to: $this->addresses($message->getTo()->all()),
            cc: $this->addresses($message->getCc()->all()),
            date: $this->date($message),
            html: $message->hasHTMLBody() ? $message->getHTMLBody() : null,
            text: $message->hasTextBody() ? $message->getTextBody() : null,
            headers: $this->headers($rawHeader),
            attachments: $message->getAttachments()->map(fn (Attachment $attachment) => new InboundAttachment(
                name: self::decode((string) ($attachment->getName() ?: 'anhang')),
                mimeType: (string) ($attachment->getMimeType() ?: 'application/octet-stream'),
                content: (string) $attachment->getContent(),
                contentId: self::normalizeId((string) $attachment->getContentId()),
                inline: strtolower((string) $attachment->getDisposition()) === 'inline',
            ))->values()->all(),
        );
    }

    /**
     * "<abc@host>" → "abc@host"
     */
    public static function normalizeId(?string $id): ?string
    {
        $id = trim((string) $id, " \t\r\n<>");

        return $id !== '' ? $id : null;
    }

    /**
     * Decodes MIME encoded words (=?UTF-8?Q?…?=).
     */
    public static function decode(string $value): string
    {
        if (! str_contains($value, '=?')) {
            return $value;
        }

        $decoded = @iconv_mime_decode($value, ICONV_MIME_DECODE_CONTINUE_ON_ERROR, 'UTF-8');

        return $decoded !== false ? $decoded : mb_decode_mimeheader($value);
    }

    /**
     * @param  array<int, mixed>  $values
     * @return list<string>
     */
    private function ids(array $values): array
    {
        $ids = [];

        foreach ($values as $value) {
            preg_match_all('/<([^>]+)>/', (string) $value, $matches);
            $ids = [...$ids, ...($matches[1] ?: [self::normalizeId((string) $value)])];
        }

        return array_values(array_unique(array_filter($ids)));
    }

    /**
     * @param  array<int, Address>  $addresses
     * @return list<array{email: string, name: ?string}>
     */
    private function addresses(array $addresses): array
    {
        return array_values(array_filter(array_map(fn (Address $address) => $address->mail ? [
            'email' => Str::lower(trim($address->mail)),
            'name' => ($name = trim(self::decode((string) $address->personal), " \"'")) !== '' ? $name : null,
        ] : null, $addresses)));
    }

    private function date(Message $message): CarbonImmutable
    {
        try {
            $date = $message->getDate()->first();

            return $date ? CarbonImmutable::parse((string) $date) : CarbonImmutable::now();
        } catch (Throwable) {
            return CarbonImmutable::now();
        }
    }

    /**
     * @return array<string, string>
     */
    private function headers(string $raw): array
    {
        // Unfold continuation lines first.
        $raw = preg_replace("/\r?\n[ \t]+/", ' ', $raw);
        $headers = [];

        foreach (preg_split("/\r?\n/", (string) $raw) as $line) {
            if (preg_match('/^([A-Za-z0-9-]+):\s*(.*)$/', $line, $match)) {
                $name = strtolower($match[1]);
                if (in_array($name, self::HEADERS, true) && ! isset($headers[$name])) {
                    $headers[$name] = trim($match[2]);
                }
            }
        }

        return $headers;
    }
}
