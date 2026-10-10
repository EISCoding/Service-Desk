<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Group;
use App\Models\User;

class GroupPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::AdminGroups->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::AdminGroups->value);
    }

    public function update(User $user, Group $group): bool
    {
        return $user->can(Permission::AdminGroups->value);
    }

    public function delete(User $user, Group $group): bool
    {
        return $user->can(Permission::AdminGroups->value);
    }
}
