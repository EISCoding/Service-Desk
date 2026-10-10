<?php

namespace App\Mail\Inbound;

use App\Mail\Mailboxes\MailboxSettings;
use App\Models\Mailbox;
use RuntimeException;
use Webklex\PHPIMAP\Message;

/**
 * Fetches unread mails via IMAP (webklex/php-imap, no PHP imap extension needed).
 */
class ImapMailboxConnector implements MailboxConnector
{
    public function __construct(private InboundMailParser $parser) {}

    public function fetch(Mailbox $mailbox, int $limit): iterable
    {
        $client = MailboxSettings::imapClientFor($mailbox);
        $client->connect();

        try {
            $folder = $client->getFolderByPath($mailbox->imap_folder)
                ?? throw new RuntimeException("Ordner „{$mailbox->imap_folder}“ nicht gefunden.");

            $messages = $folder->query()->unseen()->leaveUnread()->setFetchOrder('asc')->limit($limit)->get();

            foreach ($messages as $message) {
                yield $this->wrap($mailbox, $message);
            }
        } finally {
            $client->disconnect();
        }
    }

    private function wrap(Mailbox $mailbox, Message $message): FetchedMail
    {
        return new ImapFetchedMail($mailbox, $message, $this->parser->fromMessage($message));
    }
}
