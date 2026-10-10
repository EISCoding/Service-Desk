<?php

namespace App\Services;

use App\Enums\MessageType;
use App\Enums\TicketStatus;
use App\Models\Appointment;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Merges a ticket into another one: messages (with attachments) and
 * appointments move to the target, the source is closed and points to it.
 */
class TicketMerger
{
    public function __construct(private TicketStatusService $statuses) {}

    public function merge(Ticket $source, Ticket $target, User $user): void
    {
        if ($source->is($target)) {
            throw new InvalidArgumentException('A ticket cannot be merged into itself.');
        }

        if ($source->merged_into_id || $target->merged_into_id) {
            throw new InvalidArgumentException('Merged tickets cannot be merged again.');
        }

        DB::transaction(function () use ($source, $target, $user) {
            TicketMessage::where('ticket_id', $source->id)->update(['ticket_id' => $target->id]);
            Appointment::where('ticket_id', $source->id)->update(['ticket_id' => $target->id]);

            // A ticket without customer takes over the source's customer.
            if (! $target->customer_id && $source->customer_id) {
                $target->customer_id = $source->customer_id;
                $target->contact_id = $source->contact_id;
                $target->save();
            }

            $this->note($target, $user, "{$source->reference()} „{$source->subject}“ wurde in dieses Ticket zusammengeführt.");
            $this->note($source, $user, "Zusammengeführt in {$target->reference()} „{$target->subject}“.");

            $source->mergedInto()->associate($target);
            $this->statuses->change($source, TicketStatus::Closed);

            $source->logActivity('merged_into', ['ticket' => ['old' => null, 'new' => $target->number]]);
            $target->logActivity('merged_from', ['ticket' => ['old' => null, 'new' => $source->number]]);
            $target->touchActivity();
        });
    }

    private function note(Ticket $ticket, User $user, string $text): void
    {
        $message = new TicketMessage(['type' => MessageType::Internal, 'body' => '<p>'.e($text).'</p>']);
        $message->ticket()->associate($ticket);
        $message->author()->associate($user);
        $message->save();
    }
}
