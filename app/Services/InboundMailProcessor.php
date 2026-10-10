<?php

namespace App\Services;

use App\Enums\MessageType;
use App\Enums\TicketSource;
use App\Enums\TicketStatus;
use App\Mail\Inbound\InboundMail;
use App\Models\Contact;
use App\Models\Mailbox;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Support\Html;
use Illuminate\Support\Facades\DB;

/**
 * Turns an incoming mail into a ticket message.
 */
class InboundMailProcessor
{
    public function __construct(
        private AttachmentStorage $attachments,
        private TicketStatusService $statuses,
    ) {}

    public function process(Mailbox $mailbox, InboundMail $mail): TicketMessage
    {
        // The same mail is never imported twice (e.g. after a failed "mark as read").
        if ($mail->messageId && $existing = TicketMessage::where('message_id', $mail->messageId)->first()) {
            return $existing;
        }

        return DB::transaction(function () use ($mailbox, $mail) {
            $contact = $this->contactFor($mail);
            $ticket = $this->findTicket($mail);

            // Replies to tickets closed longer than the reopen period start a new ticket.
            $previous = null;
            if ($ticket?->isClosed() && ! $this->canReopen($ticket)) {
                [$previous, $ticket] = [$ticket, null];
            }

            $isReply = $ticket !== null;
            $ticket ??= $this->newTicket($mailbox, $mail, $contact, $previous);

            // A ticket without customer gets the customer of a known sender.
            if (! $ticket->customer_id && $contact) {
                $ticket->customer()->associate($contact->customer_id);
                $ticket->contact()->associate($contact);
                $ticket->save();
            }

            $message = $this->addMessage($ticket, $mail, $contact);

            if ($isReply) {
                $this->statusAfterCustomerReply($ticket);
            }

            if ($previous) {
                $this->linkFollowUpTicket($previous, $ticket);
            }

            return $message;
        });
    }

    /**
     * 1) [#JJJJ-NNNNN] (or Ticket#JJJJ-NNNNN) in the subject,
     * 2) In-Reply-To / References pointing to a message of a ticket,
     * otherwise null (new ticket). Merged tickets lead to their target.
     */
    public function findTicket(InboundMail $mail): ?Ticket
    {
        $ticket = null;

        if (preg_match('/\[#(\d{4}-\d{5})\]|Ticket#(\d{4}-\d{5})/i', $mail->subject, $match)) {
            $ticket = Ticket::where('number', $match[1] ?: $match[2])->first();
        }

        if (! $ticket && $ids = $mail->referencedIds()) {
            $ticket = TicketMessage::whereIn('message_id', $ids)->latest('id')->first()?->ticket;
        }

        // Follow merges to the ticket that is still in use.
        for ($i = 0; $ticket?->merged_into_id && $i < 10; $i++) {
            $ticket = $ticket->mergedInto;
        }

        return $ticket;
    }

    /**
     * The contact with the sender's address; unknown senders stay without
     * customer ("Ohne Kunde") until someone assigns one.
     */
    public function contactFor(InboundMail $mail): ?Contact
    {
        return $mail->fromEmail !== '' ? Contact::where('email', $mail->fromEmail)->first() : null;
    }

    /**
     * Closed tickets can be reopened by the customer within the reopen period.
     */
    public function canReopen(Ticket $ticket): bool
    {
        return $ticket->closed_at === null
            || $ticket->closed_at->gte(now()->subDays((int) config('servicedesk.mail.reopen_days')));
    }

    /**
     * A customer reply puts closed (within the period) and "Warte auf Kunden"
     * tickets back to "In Arbeit"; other statuses stay.
     */
    private function statusAfterCustomerReply(Ticket $ticket): void
    {
        if (in_array($ticket->status, [TicketStatus::Closed, TicketStatus::WaitingCustomer], true)) {
            $this->statuses->change($ticket, TicketStatus::InProgress);
        }
    }

    private function linkFollowUpTicket(Ticket $previous, Ticket $ticket): void
    {
        $days = (int) config('servicedesk.mail.reopen_days');

        $this->note($ticket, "Folgeticket zu {$previous->reference()}: Die Antwort kam mehr als {$days} Tage nach dem Schließen.");
        $this->note($previous, "Neue Antwort nach Ablauf der Frist – weiter in {$ticket->reference()}.");
    }

    private function note(Ticket $ticket, string $text): void
    {
        $note = new TicketMessage(['type' => MessageType::Internal, 'body' => '<p>'.e($text).'</p>']);
        $note->ticket()->associate($ticket);
        $note->save();
    }

    private function newTicket(Mailbox $mailbox, InboundMail $mail, ?Contact $contact, ?Ticket $previous = null): Ticket
    {
        $ticket = new Ticket([
            'subject' => $mail->subject,
            'source' => TicketSource::Mail,
            'requester_email' => $mail->fromEmail ?: null,
            'requester_name' => $mail->fromName,
        ]);
        $ticket->mailbox()->associate($mailbox);
        // A follow-up ticket stays with the team and customer of the previous one.
        $ticket->group()->associate($previous ? $previous->group_id : $mailbox->default_group_id);
        $ticket->customer()->associate($contact?->customer_id ?? $previous?->customer_id);
        $ticket->contact()->associate($contact ?? $previous?->contact_id);
        $ticket->save();

        return $ticket;
    }

    private function addMessage(Ticket $ticket, InboundMail $mail, ?Contact $contact): TicketMessage
    {
        $message = new TicketMessage([
            'type' => MessageType::MailIn,
            'body' => $mail->html ?? Html::fromText((string) $mail->text),
            'message_id' => $mail->messageId,
            'in_reply_to' => $mail->inReplyTo,
            'from_email' => $mail->fromEmail ?: null,
            'from_name' => $mail->fromName,
            'to' => $mail->to,
            'cc' => $mail->cc,
        ]);
        $message->ticket()->associate($ticket);
        $message->author()->associate($contact);
        $message->created_at = $mail->date->isFuture() ? now() : $mail->date;
        $message->save();

        foreach ($mail->attachments as $attachment) {
            $this->attachments->storeContents($attachment->content, $attachment->name, $attachment->mimeType, $message, $attachment->contentId);
        }

        $ticket->touchActivity();

        return $message;
    }
}
