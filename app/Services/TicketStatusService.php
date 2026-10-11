<?php

namespace App\Services;

use App\Enums\MessageType;
use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

/**
 * Status rules of tickets, shared by the staff UI, mail import and portal:
 * - "Übernehmen" assigns the ticket and sets it to "In Arbeit".
 * - "Warte auf Intern/Extern" require a follow-up date (Wiedervorlage).
 * - Closing sets closed_at, any other status clears it.
 */
class TicketStatusService
{
    public function take(Ticket $ticket, User $user): void
    {
        $ticket->assignee()->associate($user);
        $this->change($ticket, TicketStatus::InProgress);
    }

    public function change(Ticket $ticket, TicketStatus $status, ?CarbonInterface $followUp = null): void
    {
        if ($status->requiresFollowUp() && ! $followUp) {
            throw new InvalidArgumentException("Status {$status->value} requires a follow-up date.");
        }

        $ticket->status = $status;
        $ticket->closed_at = $status === TicketStatus::Closed ? ($ticket->closed_at ?? now()) : null;

        $ticket->follow_up_at = $status->requiresFollowUp() ? $followUp->toDateString() : null;
        $ticket->follow_up_due = $status->requiresFollowUp() && $followUp->startOfDay()->lte(today());

        $ticket->save();
        $ticket->touchActivity();
    }

    /**
     * A customer reply (mail or portal) puts closed and "Warte auf Kunden"
     * tickets back to "In Arbeit"; other statuses stay.
     */
    public function afterCustomerReply(Ticket $ticket): void
    {
        if (in_array($ticket->status, [TicketStatus::Closed, TicketStatus::WaitingCustomer], true)) {
            $this->change($ticket, TicketStatus::InProgress);
        } else {
            $ticket->touchActivity();
        }
    }

    /**
     * Internal notes that connect a follow-up ticket with the closed one.
     */
    public function linkFollowUp(Ticket $previous, Ticket $ticket, string $reason): void
    {
        $this->note($ticket, "Folgeticket zu {$previous->reference()}: {$reason}");
        $this->note($previous, "Weiter in {$ticket->reference()}.");
    }

    private function note(Ticket $ticket, string $text): void
    {
        $note = new TicketMessage(['type' => MessageType::Internal, 'body' => '<p>'.e($text).'</p>']);
        $note->ticket()->associate($ticket);
        $note->save();
    }

    /**
     * Default follow-up: the second working day from today (weekends skipped).
     */
    public static function defaultFollowUp(): Carbon
    {
        return today()->addWeekdays(2);
    }
}
