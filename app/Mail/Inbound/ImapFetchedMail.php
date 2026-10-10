<?php

namespace App\Mail\Inbound;

use App\Models\Mailbox;
use Webklex\PHPIMAP\Message;

/**
 * A mail fetched via IMAP. After successful processing it is marked as read
 * or moved to the processed folder, depending on the mailbox setting.
 */
final class ImapFetchedMail implements FetchedMail
{
    public function __construct(
        private Mailbox $mailbox,
        private Message $message,
        private InboundMail $mail,
    ) {}

    public function mail(): InboundMail
    {
        return $this->mail;
    }

    public function markProcessed(): void
    {
        if ($this->mailbox->processed_action === 'move' && filled($this->mailbox->processed_folder)) {
            $this->message->move($this->mailbox->processed_folder);

            return;
        }

        $this->message->setFlag('Seen');
    }
}
