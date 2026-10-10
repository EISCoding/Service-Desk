<?php

namespace App\Mail\Inbound;

use App\Models\Mailbox;

interface MailboxConnector
{
    /**
     * Unread mails of the mailbox's folder, oldest first.
     *
     * @return iterable<FetchedMail>
     */
    public function fetch(Mailbox $mailbox, int $limit): iterable;
}
