<?php

namespace App\Mail\Inbound;

/**
 * Recognizes automatically generated mails (auto-replies, out-of-office,
 * bounces, newsletters). They are imported, but never trigger automatic
 * replies or status changes – otherwise two systems could answer each other
 * forever.
 */
class LoopDetector
{
    private const SUBJECTS = [
        'abwesenheit', 'out of office', 'out-of-office', 'automatische antwort', 'autoreply', 'auto-reply',
        'automatic reply', 'undeliverable', 'unzustellbar', 'delivery status notification',
        'mail delivery failed', 'returned mail', 'nicht zustellbar',
    ];

    public function isAutomatic(InboundMail $mail): bool
    {
        $autoSubmitted = strtolower((string) $mail->header('auto-submitted'));
        if ($autoSubmitted !== '' && $autoSubmitted !== 'no') {
            return true;
        }

        if (filled($mail->header('x-auto-response-suppress'))
            || filled($mail->header('x-autoreply'))
            || filled($mail->header('x-autorespond'))
            || filled($mail->header('x-autoresponder'))
            || filled($mail->header('list-id'))
            || filled($mail->header('list-unsubscribe'))) {
            return true;
        }

        if (in_array(strtolower((string) $mail->header('precedence')), ['bulk', 'junk', 'list', 'auto_reply'], true)) {
            return true;
        }

        // Bounces have an empty return path.
        if (trim((string) $mail->header('return-path')) === '<>') {
            return true;
        }

        if (preg_match('/^(mailer-daemon|postmaster|no-?reply|do-?not-?reply)@/i', $mail->fromEmail)) {
            return true;
        }

        return collect(self::SUBJECTS)
            ->contains(fn (string $needle) => str_contains(mb_strtolower($mail->subject), $needle));
    }

    /**
     * Mails sent by one of our own mailboxes (or carrying our marker header)
     * are not imported at all.
     *
     * @param  list<string>  $ownAddresses
     */
    public function isOwnMail(InboundMail $mail, array $ownAddresses): bool
    {
        return in_array($mail->fromEmail, $ownAddresses, true) || filled($mail->header('x-servicedesk-ticket'));
    }
}
