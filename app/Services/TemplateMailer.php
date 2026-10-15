<?php

namespace App\Services;

use App\Enums\SystemTemplate;
use App\Models\Appointment;
use App\Models\MailTemplate;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use App\Support\Placeholders;

/**
 * Sends active system templates as outgoing ticket mails.
 */
class TemplateMailer
{
    public function __construct(private OutgoingMailService $mail) {}

    /**
     * Template active and the ticket has a recipient and a mailbox.
     */
    public function canSend(Ticket $ticket, SystemTemplate $template): bool
    {
        return $this->mail->canMail($ticket) && MailTemplate::system($template) !== null;
    }

    /**
     * @param  User|null  $author  staff member who triggered the mail (null for automatic mails)
     * @param  User|null  $staff  value of {mitarbeiter.name}, defaults to the author
     */
    public function send(Ticket $ticket, SystemTemplate $template, ?User $author = null, ?Appointment $appointment = null, ?User $staff = null): ?TicketMessage
    {
        $mailTemplate = MailTemplate::system($template);

        if (! $mailTemplate || ! $this->mail->canMail($ticket)) {
            return null;
        }

        $placeholders = new Placeholders($ticket, appointment: $appointment, staff: $staff ?? $author);

        $message = $this->mail->queue(
            $ticket,
            $placeholders->html($mailTemplate->body),
            $author,
            // Same audience as the replies: whoever is in CC gets status mails too.
            cc: $this->mail->suggestedCc($ticket),
            subject: $placeholders->text($mailTemplate->subject),
            templateKey: $template->value,
        );
        $this->mail->dispatch($message);

        return $message;
    }
}
