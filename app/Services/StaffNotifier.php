<?php

namespace App\Services;

use App\Enums\NotificationEvent;
use App\Enums\Permission;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use App\Notifications\Staff\TicketNotification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

/**
 * Decides who is notified about ticket events. Only active users who may see
 * the ticket are notified, never the person who caused the event.
 */
class StaffNotifier
{
    /**
     * Members of the ticket's group; tickets without group go to everyone
     * with access to group tickets (like their visibility).
     */
    public function newTicket(Ticket $ticket, ?User $causer = null): void
    {
        $this->send($this->teamOf($ticket), $causer, new TicketNotification(
            NotificationEvent::NewTicket,
            $ticket,
            $ticket->group ? "Neues Ticket in {$ticket->group->name}" : 'Neues Ticket ohne Gruppe',
            $ticket->customer?->company ?? $ticket->requester_email ?? '',
        ));
    }

    public function assigned(Ticket $ticket, User $assignee, ?User $causer = null): void
    {
        $this->send(new Collection([$assignee]), $causer, new TicketNotification(
            NotificationEvent::Assigned,
            $ticket,
            $causer ? "{$causer->name} hat dir ein Ticket zugewiesen" : 'Dir wurde ein Ticket zugewiesen',
        ));
    }

    /**
     * The assignee, without assignee the team of the ticket.
     */
    public function customerReply(Ticket $ticket, TicketMessage $message): void
    {
        $recipients = $ticket->assignee ? new Collection([$ticket->assignee]) : $this->teamOf($ticket);

        $this->send($recipients, null, new TicketNotification(
            NotificationEvent::CustomerReply,
            $ticket,
            'Antwort von '.$message->authorName(),
            Str::limit($message->plainText(), 300),
        ));
    }

    /**
     * @return Collection<int, User>
     */
    private function teamOf(Ticket $ticket): Collection
    {
        return $ticket->group_id
            ? $ticket->group->users()->get()
            : User::permission(Permission::TicketsViewGroup->value)->get();
    }

    /**
     * @param  Collection<int, User>  $users
     */
    private function send(Collection $users, ?User $causer, TicketNotification $notification): void
    {
        $recipients = $users
            ->filter(fn (User $user) => $user->is_active && ! $user->is($causer) && $user->can('view', $notification->ticket))
            ->unique('id');

        Notification::send($recipients, $notification);
    }
}
