<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Contact;
use App\Models\User;

class ContactPolicy
{
    public function create(User $user): bool
    {
        return $user->can(Permission::CustomersManage->value);
    }

    public function update(User $user, Contact $contact): bool
    {
        return $user->can(Permission::CustomersManage->value);
    }

    public function delete(User $user, Contact $contact): bool
    {
        return $user->can(Permission::CustomersManage->value);
    }

    /**
     * Grant/revoke portal access and send invitations.
     */
    public function managePortal(User $user, Contact $contact): bool
    {
        return $user->can(Permission::ContactsPortal->value);
    }
}
