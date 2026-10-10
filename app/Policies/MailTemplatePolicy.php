<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\MailTemplate;
use App\Models\Ticket;
use App\Models\User;

/**
 * Everyone working on tickets can read templates and use snippets;
 * templates.manage is needed to change them. System templates are never deleted.
 */
class MailTemplatePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::TemplatesManage->value) || $user->can('viewAny', Ticket::class);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::TemplatesManage->value);
    }

    public function update(User $user, MailTemplate $template): bool
    {
        return $user->can(Permission::TemplatesManage->value);
    }

    public function delete(User $user, MailTemplate $template): bool
    {
        return $user->can(Permission::TemplatesManage->value) && ! $template->isSystem();
    }
}
