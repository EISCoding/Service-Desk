<?php

namespace App\Services;

use App\Enums\TicketStatus;
use App\Models\Ticket;
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
     * Default follow-up: the second working day from today (weekends skipped).
     */
    public static function defaultFollowUp(): Carbon
    {
        return today()->addWeekdays(2);
    }
}
