<?php

namespace App\Jobs;

use App\Mail\Inbound\MailboxConnector;
use App\Models\Mailbox;
use App\Services\InboundMailProcessor;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Str;
use Throwable;

/**
 * Fetches the unread mails of one mailbox and turns them into tickets.
 * Dispatched every minute by `servicedesk:fetch-mail`; never runs twice at
 * the same time for the same mailbox.
 */
class FetchMailbox implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 55;

    /** A pending job blocks further dispatches for up to 5 minutes. */
    public int $uniqueFor = 300;

    public function __construct(public Mailbox $mailbox) {}

    public function uniqueId(): string
    {
        return (string) $this->mailbox->id;
    }

    /**
     * @return list<object>
     */
    public function middleware(): array
    {
        return [(new WithoutOverlapping('mailbox-'.$this->mailbox->id))->expireAfter(300)->dontRelease()];
    }

    public function handle(MailboxConnector $connector, InboundMailProcessor $processor): void
    {
        if (! $this->mailbox->is_active) {
            return;
        }

        $errors = [];

        try {
            foreach ($connector->fetch($this->mailbox, (int) config('servicedesk.mail.fetch_limit')) as $fetched) {
                try {
                    $processor->process($this->mailbox, $fetched->mail());
                    $fetched->markProcessed();
                } catch (Throwable $e) {
                    // The mail stays unread and is retried with the next run.
                    report($e);
                    $errors[] = 'Mail „'.Str::limit($fetched->mail()->subject, 60).'“: '.$e->getMessage();
                }
            }
        } catch (Throwable $e) {
            $errors[] = $e->getMessage();
        }

        $this->mailbox->forceFill([
            'last_fetched_at' => now(),
            'last_error' => $errors ? Str::limit(implode(' | ', $errors), 1000) : null,
        ])->saveQuietly();
    }
}
