<?php

namespace App\Enums;

/**
 * Default roles and their permissions. Role names are only used to seed and
 * assign roles – authorization checks always use permissions.
 */
enum Role: string
{
    case Admin = 'Admin';
    case Staff = 'Mitarbeiter';

    /**
     * @return list<Permission>
     */
    public function permissions(): array
    {
        return match ($this) {
            self::Admin => Permission::cases(),
            self::Staff => [
                Permission::TicketsViewOwn,
                Permission::TicketsViewGroup,
                Permission::TicketsEdit,
                Permission::NotesManage,
                Permission::CustomersManage,
                Permission::ContactsPortal,
            ],
        };
    }
}
