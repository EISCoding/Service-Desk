<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Customer;
use App\Models\User;

class CustomerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::CustomersManage->value);
    }

    public function view(User $user, Customer $customer): bool
    {
        return $user->can(Permission::CustomersManage->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::CustomersManage->value);
    }

    public function update(User $user, Customer $customer): bool
    {
        return $user->can(Permission::CustomersManage->value);
    }

    public function archive(User $user, Customer $customer): bool
    {
        return $user->can(Permission::CustomersManage->value);
    }

    /**
     * Customers with tickets can only be archived, not deleted.
     */
    public function delete(User $user, Customer $customer): bool
    {
        return $user->can(Permission::CustomersManage->value) && ! $customer->hasTickets();
    }
}
