<?php

namespace Tests\Support;

use App\Mail\Inbound\FetchedMail;
use App\Mail\Inbound\InboundMail;
use App\Mail\Inbound\InboundMailParser;
use App\Mail\Inbound\MailboxConnector;
use App\Models\Mailbox;
use RuntimeException;

/**
 * Serves fixture .eml files (tests/Fixtures/mails) instead of an IMAP server.
 */
class FakeMailboxConnector implements MailboxConnector
{
    /** @var list<string> raw mails waiting to be fetched */
    public array $queue = [];

    /** @var list<string> subjects of mails marked as processed */
    public array $processed = [];

    public ?string $failWith = null;

    public function add(string $fixture, array $replace = []): self
    {
        $this->queue[] = strtr(file_get_contents(base_path("tests/Fixtures/mails/{$fixture}.eml")), $replace);

        return $this;
    }

    public function fetch(Mailbox $mailbox, int $limit): iterable
    {
        if ($this->failWith) {
            throw new RuntimeException($this->failWith);
        }

        $parser = new InboundMailParser;

        foreach (array_slice($this->queue, 0, $limit) as $raw) {
            $mail = $parser->fromString($raw);
            $connector = $this;

            yield new class($mail, $connector, $raw) implements FetchedMail
            {
                public function __construct(private InboundMail $mail, private FakeMailboxConnector $connector, private string $raw) {}

                public function mail(): InboundMail
                {
                    return $this->mail;
                }

                public function markProcessed(): void
                {
                    $this->connector->processed[] = $this->mail->subject;
                    $this->connector->queue = array_values(array_diff($this->connector->queue, [$this->raw]));
                }
            };
        }
    }
}
