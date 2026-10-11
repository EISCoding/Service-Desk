<?php

namespace App\Jobs;

use App\Enums\TicketStatus;
use App\Models\Ticket;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Daily: flags tickets whose follow-up date (Wiedervorlage) has come, so they
 * stand out in the ticket list. Flags of tickets that are no longer waiting
 * are cleared.
 */
class MarkDueFollowUps implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        $waiting = array_map(fn (TicketStatus $status) => $status->value, array_filter(
            TicketStatus::cases(), fn (TicketStatus $status) => $status->requiresFollowUp(),
        ));

        Ticket::query()
            ->whereIn('status', $waiting)
            ->whereNotNull('follow_up_at')
            ->whereDate('follow_up_at', '<=', today())
            ->where('follow_up_due', false)
            ->update(['follow_up_due' => true]);

        Ticket::query()
            ->where('follow_up_due', true)
            ->where(fn ($query) => $query->whereNotIn('status', $waiting)->orWhereNull('follow_up_at'))
            ->update(['follow_up_due' => false]);
    }
}
