<?php

namespace App\Console\Commands;

use App\Jobs\FetchMailbox;
use App\Models\Mailbox;
use Illuminate\Console\Command;
use Throwable;

class FetchMail extends Command
{
    protected $signature = 'servicedesk:fetch-mail
        {--now : Fetch in this process instead of queueing a job per mailbox}';

    protected $description = 'Fetch new mails of every active mailbox';

    public function handle(): int
    {
        Mailbox::active()->each(function (Mailbox $mailbox) {
            if (! $this->option('now')) {
                FetchMailbox::dispatch($mailbox);

                return;
            }

            // Errors are stored on the mailbox by the job; one broken mailbox must not stop the others.
            try {
                FetchMailbox::dispatchSync($mailbox);
            } catch (Throwable $e) {
                report($e);
            }
        });

        return self::SUCCESS;
    }
}
