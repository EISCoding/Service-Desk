<?php

namespace App\Mail;

use App\Mail\Mailboxes\MailboxSettings;
use App\Models\Mailbox;
use Illuminate\Mail\Mailable;
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

        $mailer = self::mailer($mailbox);

        return $mailer ? $mail->mailer($mailer) : $mail;
    }

    /**
     * Sends a mailable right away (e.g. the test mail of the mail design).
     */
    public static function send(Mailable $mail, string $to): void
    {
        $mailbox = Mailbox::default();

        if ($mailbox) {
            $mail->from($mailbox->email, $mailbox->name);
        }

        Mail::mailer(self::mailer($mailbox))->to($to)->send($mail);
    }

    /**
     * Name of the mailer to use, null for the default from the .env.
     */
    private static function mailer(?Mailbox $mailbox): ?string
    {
        if ($override = config('servicedesk.mail.mailer')) {
            return $override;
        }

        if (! $mailbox) {
            return null;
        }

        config(['mail.mailers.mailbox' => MailboxSettings::smtpConfigFor($mailbox)]);
        Mail::purge('mailbox'); // a long-running worker picks up changed mailbox settings

        return 'mailbox';
    }
}
