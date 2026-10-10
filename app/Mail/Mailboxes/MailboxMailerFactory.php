<?php

namespace App\Mail\Mailboxes;

use App\Models\Mailbox;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Support\Facades\Mail;

/**
 * Mailer that sends through the SMTP account of a mailbox
 * (or the mailer from MAILBOX_MAILER, e.g. "log" for local development).
 */
class MailboxMailerFactory
{
    public function make(Mailbox $mailbox): Mailer
    {
        if ($mailer = config('servicedesk.mail.mailer')) {
            return Mail::mailer($mailer);
        }

        return Mail::build(MailboxSettings::smtpConfigFor($mailbox));
    }
}
