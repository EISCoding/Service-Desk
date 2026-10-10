<?php

namespace App\Services;

use App\Enums\MessageType;
use App\Enums\TicketSource;
use App\Mail\Inbound\InboundMail;
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
    public function __construct(private AttachmentStorage $attachments) {}

    public function process(Mailbox $mailbox, InboundMail $mail): TicketMessage
    {
        // The same mail is never imported twice (e.g. after a failed "mark as read").
        if ($mail->messageId && $existing = TicketMessage::where('message_id', $mail->messageId)->first()) {
            return $existing;
        }

        return DB::transaction(function () use ($mailbox, $mail) {
            $ticket = $this->findTicket($mail) ?? $this->newTicket($mailbox, $mail);

            return $this->addMessage($ticket, $mail);
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

    private function newTicket(Mailbox $mailbox, InboundMail $mail): Ticket
    {
        $ticket = new Ticket([
            'subject' => $mail->subject,
            'source' => TicketSource::Mail,
            'requester_email' => $mail->fromEmail ?: null,
            'requester_name' => $mail->fromName,
        ]);
        $ticket->mailbox()->associate($mailbox);
        $ticket->group()->associate($mailbox->default_group_id);
        $ticket->save();

        return $ticket;
    }

    private function addMessage(Ticket $ticket, InboundMail $mail): TicketMessage
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
        $message->created_at = $mail->date->isFuture() ? now() : $mail->date;
        $message->save();

        foreach ($mail->attachments as $attachment) {
            $this->attachments->storeContents($attachment->content, $attachment->name, $attachment->mimeType, $message, $attachment->contentId);
        }

        $ticket->touchActivity();

        return $message;
    }
}
