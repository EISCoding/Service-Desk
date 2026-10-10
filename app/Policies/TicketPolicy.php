<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Ticket;
use App\Models\User;

/**
 * Visibility:
 * - tickets.view.all:   every ticket
 * - tickets.view.own:   tickets assigned to the user
 * - tickets.view.group: tickets of the user's groups and tickets without group (shared pool)
 * Every other action additionally requires that the ticket is visible.
 */
class TicketPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission([
            Permission::TicketsViewOwn->value, Permission::TicketsViewGroup->value, Permission::TicketsViewAll->value,
        ]);
    }

    public function view(User $user, Ticket $ticket): bool
    {
        if ($user->can(Permission::TicketsViewAll->value)) {
            return true;
        }

        if ($user->can(Permission::TicketsViewOwn->value) && $ticket->assignee_id === $user->id) {
            return true;
        }

        return $user->can(Permission::TicketsViewGroup->value)
            && ($ticket->group_id === null || in_array($ticket->group_id, $user->groupIds(), true));
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::TicketsEdit->value);
    }

    public function update(User $user, Ticket $ticket): bool
    {
        return $user->can(Permission::TicketsEdit->value) && $this->view($user, $ticket);
    }

    public function reply(User $user, Ticket $ticket): bool
    {
        return $this->update($user, $ticket);
    }

    public function addNote(User $user, Ticket $ticket): bool
    {
        return $user->can(Permission::NotesManage->value) && $this->view($user, $ticket);
    }

    public function delete(User $user, Ticket $ticket): bool
    {
        return $user->can(Permission::TicketsDelete->value) && $this->view($user, $ticket);
    }

    public function merge(User $user, Ticket $ticket): bool
    {
        return $user->can(Permission::TicketsMerge->value) && $this->view($user, $ticket);
    }
}
