<?php

namespace App\Console\Commands;

use App\Mail\Inbound\InboundMailParser;
use App\Models\Mailbox;
use App\Services\InboundMailProcessor;
use Illuminate\Console\Command;

/**
 * Imports .eml files as if they had been fetched from a mailbox, e.g. for
 * local testing without a mail server or to re-import a single mail.
 */
class ImportMail extends Command
{
    protected $signature = 'servicedesk:import-mail
        {files* : Path(s) to .eml files}
        {--mailbox= : ID of the mailbox (default: the default mailbox)}';

    protected $description = 'Import .eml files into the ticket system';

    public function handle(InboundMailParser $parser, InboundMailProcessor $processor): int
    {
        $mailbox = $this->option('mailbox')
            ? Mailbox::find($this->option('mailbox'))
            : Mailbox::default() ?? Mailbox::orderBy('id')->first();

        if (! $mailbox) {
            $this->error('Kein Postfach gefunden. Lege zuerst unter /intern/admin/postfaecher eines an.');

            return self::FAILURE;
        }

        foreach ($this->argument('files') as $file) {
            if (! is_file($file)) {
                $this->error("Datei nicht gefunden: {$file}");

                return self::FAILURE;
            }

            $message = $processor->process($mailbox, $parser->fromFile($file));

            $this->line($message
                ? "{$file} → {$message->ticket->reference()}"
                : "{$file} übersprungen (Mail eines eigenen Postfachs)");
        }

        return self::SUCCESS;
    }
}
