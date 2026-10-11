<?php

namespace App\Mail;

use App\Mail\Mailboxes\MailboxSettings;
use App\Models\Mailbox;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Mail;

/**
 * Mails that do not belong to a ticket (portal invitation and password, staff
 * notifications) go out through the default mailbox as well, so the SMTP
 * access is configured in one place (Admin → Postfächer). Without a mailbox
 * the mailer from MAIL_* in the .env is used.
 */
class SystemMailer
{
    public static function via(MailMessage $mail): MailMessage
    {
        $mailbox = Mailbox::default();

        if ($mailbox) {
            $mail->from($mailbox->email, $mailbox->name);
        }

        if ($override = config('servicedesk.mail.mailer')) {
            return $mail->mailer($override);
        }

        if (! $mailbox) {
            return $mail;
        }

        config(['mail.mailers.mailbox' => MailboxSettings::smtpConfigFor($mailbox)]);
        Mail::purge('mailbox'); // a long-running worker picks up changed mailbox settings

        return $mail->mailer('mailbox');
    }
}
