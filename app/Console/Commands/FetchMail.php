<?php

namespace App\Console\Commands;

use App\Jobs\FetchMailbox;
use App\Models\Mailbox;
use Illuminate\Console\Command;

class FetchMail extends Command
{
    protected $signature = 'servicedesk:fetch-mail';

    protected $description = 'Dispatch a FetchMailbox job for every active mailbox';

    public function handle(): int
    {
        Mailbox::active()->each(fn (Mailbox $mailbox) => FetchMailbox::dispatch($mailbox));

        return self::SUCCESS;
    }
}
