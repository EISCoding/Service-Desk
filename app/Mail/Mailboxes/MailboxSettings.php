<?php

namespace App\Mail\Mailboxes;

use App\Models\Mailbox;
use Webklex\PHPIMAP\Client;
use Webklex\PHPIMAP\ClientManager;

/**
 * Turns mailbox settings into an IMAP client (webklex) and a Laravel SMTP
 * mailer configuration. Used for fetching, sending and connection tests.
 *
 * Encryption: ssl = implicit TLS, tls = STARTTLS (required), none = plain.
 */
final class MailboxSettings
{
    /**
     * @param  array{host: string, port: int, encryption: string, username: string, password: string}  $imap
     */
    public static function imapClient(array $imap): Client
    {
        return (new ClientManager)->make([
            'host' => $imap['host'],
            'port' => (int) $imap['port'],
            'encryption' => match ($imap['encryption']) {
                'ssl' => 'ssl',
                'tls' => 'starttls',
                default => false,
            },
            'validate_cert' => true,
            'username' => $imap['username'],
            'password' => $imap['password'],
            'protocol' => 'imap',
            'timeout' => 15,
        ]);
    }

    public static function imapClientFor(Mailbox $mailbox): Client
    {
        return self::imapClient([
            'host' => $mailbox->imap_host, 'port' => $mailbox->imap_port, 'encryption' => $mailbox->imap_encryption,
            'username' => $mailbox->imap_username, 'password' => $mailbox->imap_password,
        ]);
    }

    /**
     * Configuration for Mail::build().
     *
     * @param  array{host: string, port: int, encryption: string, username: string, password: string}  $smtp
     * @return array<string, mixed>
     */
    public static function smtpConfig(array $smtp): array
    {
        return [
            'transport' => 'smtp',
            'scheme' => $smtp['encryption'] === 'ssl' ? 'smtps' : 'smtp',
            'host' => $smtp['host'],
            'port' => (int) $smtp['port'],
            'username' => $smtp['username'],
            'password' => $smtp['password'],
            'auto_tls' => $smtp['encryption'] !== 'none',
            'require_tls' => $smtp['encryption'] === 'tls',
            'timeout' => 15,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function smtpConfigFor(Mailbox $mailbox): array
    {
        return self::smtpConfig([
            'host' => $mailbox->smtp_host, 'port' => $mailbox->smtp_port, 'encryption' => $mailbox->smtp_encryption,
            'username' => $mailbox->smtp_username, 'password' => $mailbox->smtp_password,
        ]);
    }
}
