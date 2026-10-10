<?php

namespace App\Mail\Mailboxes;

use App\Models\Mailbox;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Support\Facades\Mail;

/**
 * Mailer that sends through the SMTP account of a mailbox.
 */
class MailboxMailerFactory
{
    public function make(Mailbox $mailbox): Mailer
    {
        return Mail::build(MailboxSettings::smtpConfigFor($mailbox));
    }
}
