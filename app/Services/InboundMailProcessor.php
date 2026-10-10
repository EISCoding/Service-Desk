<?php

namespace App\Services;

use App\Enums\MessageType;
use App\Enums\SystemTemplate;
use App\Enums\TicketSource;
use App\Mail\Inbound\InboundMail;
use App\Mail\Inbound\LoopDetector;
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
        private LoopDetector $loops,
        private TemplateMailer $templates,
    ) {}

    /**
     * @return TicketMessage|null null when the mail was ignored (sent by one of our own mailboxes)
     */
    public function process(Mailbox $mailbox, InboundMail $mail): ?TicketMessage
    {
        if ($this->loops->isOwnMail($mail, Mailbox::addresses())) {
            return null;
        }

        $automatic = $this->loops->isAutomatic($mail);

        // The same mail is never imported twice (e.g. after a failed "mark as read").
        if ($mail->messageId && $existing = TicketMessage::where('message_id', $mail->messageId)->first()) {
            return $existing;
        }

        return DB::transaction(function () use ($mailbox, $mail, $automatic) {
            $contact = $this->contactFor($mail);
            $ticket = $this->findTicket($mail);

            // Replies to tickets closed longer than the reopen period start a new
            // ticket (automatic mails such as out-of-office notes never do).
            $previous = null;
            if ($ticket?->isClosed() && ! $this->canReopen($ticket) && ! $automatic) {
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

            $message = $this->addMessage($ticket, $mail, $contact, $automatic);

            if ($isReply && ! $automatic) {
                $this->statuses->afterCustomerReply($ticket);
            }

            if ($previous) {
                $days = (int) config('servicedesk.mail.reopen_days');
                $this->statuses->linkFollowUp($previous, $ticket, "Die Antwort kam mehr als {$days} Tage nach dem Schließen.");
            }

            // Confirmation only for known contacts, never in reply to automatic mails.
            if (! $isReply && $contact && ! $automatic) {
                $this->templates->send($ticket, SystemTemplate::TicketOpened);
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

    public function canReopen(Ticket $ticket): bool
    {
        return $ticket->canBeReopened();
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

    private function addMessage(Ticket $ticket, InboundMail $mail, ?Contact $contact, bool $automatic): TicketMessage
    {
        $message = new TicketMessage([
            'type' => MessageType::MailIn,
            'is_automatic' => $automatic,
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
