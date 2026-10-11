<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\User;

/**
 * User management (admin.users). Admins cannot lock themselves out: they can
 * neither deactivate themselves nor change their own roles.
 */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::AdminUsers->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::AdminUsers->value);
    }

    public function update(User $user, User $model): bool
    {
        return $user->can(Permission::AdminUsers->value);
    }

    public function changeRoles(User $user, User $model): bool
    {
        return $user->can(Permission::AdminUsers->value) && ! $user->is($model);
    }

    public function deactivate(User $user, User $model): bool
    {
        return $user->can(Permission::AdminUsers->value) && ! $user->is($model);
    }

    public function resetTwoFactor(User $user, User $model): bool
    {
        // Own 2FA is managed on the security page.
        return $user->can(Permission::AdminUsers->value) && ! $user->is($model);
    }
}
