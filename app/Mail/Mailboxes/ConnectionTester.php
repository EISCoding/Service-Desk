<?php

namespace App\Mail\Mailboxes;

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Throwable;

/**
 * "Verbindung testen" in the mailbox admin. Returns null on success or a
 * short error message.
 */
class ConnectionTester
{
    /**
     * @param  array{host: string, port: int, encryption: string, username: string, password: string, folder: string}  $imap
     */
    public function imap(array $imap): ?string
    {
        try {
            $client = MailboxSettings::imapClient($imap);
            $client->connect();

            $folder = $client->getFolderByPath($imap['folder']);
            $client->disconnect();

            return $folder ? null : "Ordner „{$imap['folder']}“ nicht gefunden.";
        } catch (Throwable $e) {
            return $this->message($e);
        }
    }

    /**
     * @param  array{host: string, port: int, encryption: string, username: string, password: string}  $smtp
     */
    public function smtp(array $smtp): ?string
    {
        try {
            /** @var EsmtpTransport $transport */
            $transport = Mail::build(MailboxSettings::smtpConfig($smtp))->getSymfonyTransport();
            $transport->start();
            $transport->stop();

            return null;
        } catch (Throwable $e) {
            return $this->message($e);
        }
    }

    private function message(Throwable $e): string
    {
        return Str::limit(trim(preg_replace('/\s+/', ' ', $e->getMessage())) ?: class_basename($e), 300);
    }
}
