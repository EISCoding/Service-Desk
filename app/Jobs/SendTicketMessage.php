<?php

namespace App\Jobs;

use App\Mail\Mailboxes\MailboxMailerFactory;
use App\Mail\TicketMessageMail;
use App\Models\TicketMessage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Sends an outgoing ticket message through the SMTP account of the ticket's mailbox.
 */
class SendTicketMessage implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [60, 300];

    public function __construct(public TicketMessage $message) {}

    public function handle(MailboxMailerFactory $mailers): void
    {
        $message = $this->message->fresh(['ticket.mailbox', 'attachments']);

        if (! $message || $message->mail_status === 'sent') {
            return;
        }

        $mailbox = $message->ticket->mailbox ?? throw new RuntimeException('Dem Ticket ist kein Postfach zugeordnet.');

        $mailers->make($mailbox)->send(new TicketMessageMail($message));

        $message->forceFill(['mail_status' => 'sent', 'sent_at' => now(), 'mail_error' => null])->save();
    }

    public function failed(Throwable $e): void
    {
        $this->message->forceFill([
            'mail_status' => 'failed',
            'mail_error' => Str::limit($e->getMessage(), 500),
        ])->save();
    }
}
