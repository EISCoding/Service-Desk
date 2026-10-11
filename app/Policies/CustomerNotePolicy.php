<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\CustomerNote;
use App\Models\User;

/**
 * Everyone who manages customers can add and pin notes; only the author can
 * edit or delete a note.
 */
class CustomerNotePolicy
{
    public function create(User $user): bool
    {
        return $user->can(Permission::CustomersManage->value);
    }

    public function pin(User $user, CustomerNote $note): bool
    {
        return $user->can(Permission::CustomersManage->value);
    }

    public function update(User $user, CustomerNote $note): bool
    {
        return $user->can(Permission::CustomersManage->value) && $note->user_id === $user->id;
    }

    public function delete(User $user, CustomerNote $note): bool
    {
        return $this->update($user, $note);
    }
}
