<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Mailbox;
use App\Models\User;

class MailboxPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::AdminMailboxes->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::AdminMailboxes->value);
    }

    public function update(User $user, Mailbox $mailbox): bool
    {
        return $user->can(Permission::AdminMailboxes->value);
    }

    /**
     * Mailboxes with tickets can only be deactivated.
     */
    public function delete(User $user, Mailbox $mailbox): bool
    {
        return $user->can(Permission::AdminMailboxes->value) && ! $mailbox->tickets()->exists();
    }
}
