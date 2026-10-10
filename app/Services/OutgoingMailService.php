<?php

namespace App\Services;

use App\Enums\MessageType;
use App\Jobs\SendTicketMessage;
use App\Models\Mailbox;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Creates outgoing ticket mails (replies and system templates) and queues them.
 */
class OutgoingMailService
{
    /**
     * Whether the ticket can be answered by mail (recipient and mailbox known).
     */
    public function canMail(Ticket $ticket): bool
    {
        return $ticket->recipient() !== null && ($ticket->mailbox_id || Mailbox::default());
    }

    /**
     * @param  list<array{email: string, name: ?string}>  $cc
     */
    public function queue(Ticket $ticket, string $body, ?User $author = null, array $cc = [], ?string $subject = null, ?string $templateKey = null): TicketMessage
    {
        if (! $ticket->mailbox_id) {
            $ticket->mailbox()->associate(Mailbox::default())->saveQuietly();
        }

        $message = new TicketMessage([
            'type' => MessageType::MailOut,
            'body' => $body,
            'to' => [$ticket->recipient()],
            'cc' => array_values($cc),
            'from_email' => $ticket->mailbox->email,
            'from_name' => $ticket->mailbox->name,
            'in_reply_to' => $ticket->lastIncomingMessageId(),
            'template_key' => $templateKey,
        ]);
        $message->subject = $subject;
        $message->message_id = $this->messageId($ticket);
        $message->mail_status = 'queued';
        $message->ticket()->associate($ticket);
        $message->author()->associate($author);
        $message->save();

        return $message;
    }

    /**
     * Send after the surrounding transaction (attachments are stored by then).
     */
    public function dispatch(TicketMessage $message): void
    {
        SendTicketMessage::dispatch($message)->afterCommit();
    }

    /**
     * CC proposal for a reply: recipients of the last customer mail without our
     * own mailboxes and without the main recipient.
     *
     * @return list<array{email: string, name: ?string}>
     */
    public function suggestedCc(Ticket $ticket): array
    {
        $last = $ticket->messages()->reorder()->where('type', MessageType::MailIn->value)->latest('id')->first();
        if (! $last) {
            return [];
        }

        $exclude = [...Mailbox::addresses(), strtolower($ticket->recipient()['email'] ?? '')];
        $candidates = [...($last->to ?? []), ...($last->cc ?? [])];
        if ($last->from_email) {
            $candidates[] = ['email' => $last->from_email, 'name' => $last->from_name];
        }

        return collect($candidates)
            ->filter(fn (array $r) => ! in_array(strtolower($r['email']), $exclude, true))
            ->unique(fn (array $r) => strtolower($r['email']))
            ->values()
            ->all();
    }

    public function messageId(Ticket $ticket): string
    {
        $domain = Str::after($ticket->mailbox->email, '@') ?: 'servicedesk.local';

        return 'sd.'.$ticket->number.'.'.Str::lower(Str::random(16)).'@'.$domain;
    }
}
