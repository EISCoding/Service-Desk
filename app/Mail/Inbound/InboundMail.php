<?php

namespace App\Mail\Inbound;

use Carbon\CarbonImmutable;

/**
 * A parsed incoming mail, independent of how it was fetched (IMAP or .eml file).
 * Message ids are stored without angle brackets.
 */
final readonly class InboundMail
{
    /**
     * @param  list<string>  $references
     * @param  list<array{email: string, name: ?string}>  $to
     * @param  list<array{email: string, name: ?string}>  $cc
     * @param  array<string, string>  $headers  lower-case header name => value
     * @param  list<InboundAttachment>  $attachments
     */
    public function __construct(
        public ?string $messageId,
        public ?string $inReplyTo,
        public array $references,
        public string $subject,
        public string $fromEmail,
        public ?string $fromName,
        public array $to,
        public array $cc,
        public CarbonImmutable $date,
        public ?string $html,
        public ?string $text,
        public array $headers,
        public array $attachments,
    ) {}

    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }

    /**
     * Message ids this mail refers to (In-Reply-To and References).
     *
     * @return list<string>
     */
    public function referencedIds(): array
    {
        return array_values(array_unique(array_filter([$this->inReplyTo, ...$this->references])));
    }
}
