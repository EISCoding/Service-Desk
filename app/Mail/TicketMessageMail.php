<?php

namespace App\Mail;

use App\Models\Attachment as AttachmentModel;
use App\Models\TicketMessage;
use App\Support\Html;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Symfony\Component\Mime\Email;

/**
 * An outgoing ticket message (reply or system template) as e-mail.
 */
class TicketMessageMail extends Mailable
{
    use Queueable;

    public function __construct(public TicketMessage $ticketMessage) {}

    public function envelope(): Envelope
    {
        $ticket = $this->ticketMessage->ticket;
        $mailbox = $ticket->mailbox;

        return new Envelope(
            from: new Address($mailbox->email, $mailbox->name),
            to: array_map(fn (array $r) => new Address($r['email'], $r['name'] ?? null), $this->ticketMessage->to ?? []),
            cc: array_map(fn (array $r) => new Address($r['email'], $r['name'] ?? null), $this->ticketMessage->cc ?? []),
            subject: $ticket->mailSubject($this->ticketMessage->subject),
            using: [function (Email $email) {
                // Threading: replies of the customer land in the same ticket.
                if ($this->ticketMessage->in_reply_to) {
                    $email->getHeaders()->addIdHeader('In-Reply-To', $this->ticketMessage->in_reply_to);
                }
            }],
        );
    }

    public function headers(): Headers
    {
        $ticket = $this->ticketMessage->ticket;

        return new Headers(
            messageId: $this->ticketMessage->message_id,
            references: $ticket->mailReferences($this->ticketMessage),
            text: array_filter([
                // Our own marker: such mails are never imported again (loop protection).
                'X-ServiceDesk-Ticket' => $ticket->number,
                // System template mails are automatic; replies of staff are not.
                'Auto-Submitted' => $this->ticketMessage->template_key ? 'auto-generated' : null,
            ]),
        );
    }

    public function content(): Content
    {
        return new Content(
            html: 'mail.ticket-message',
            text: 'mail.ticket-message-text',
            with: [
                'body' => $this->ticketMessage->body,
                'text' => Html::toText($this->ticketMessage->body),
                'ticket' => $this->ticketMessage->ticket,
            ],
        );
    }

    /**
     * @return list<Attachment>
     */
    public function attachments(): array
    {
        return $this->ticketMessage->attachments
            ->map(fn (AttachmentModel $a) => Attachment::fromStorageDisk($a->disk, $a->path)->as($a->original_name)->withMime($a->mime_type))
            ->all();
    }
}
