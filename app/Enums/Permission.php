<?php

namespace App\Enums;

/**
 * All permissions of the application. Authorization in code always checks
 * these permissions (via policies/gates), never role names.
 */
enum Permission: string
{
    case TicketsViewOwn = 'tickets.view.own';
    case TicketsViewGroup = 'tickets.view.group';
    case TicketsViewAll = 'tickets.view.all';
    case TicketsEdit = 'tickets.edit';
    case TicketsDelete = 'tickets.delete';
    case TicketsMerge = 'tickets.merge';
    case NotesManage = 'notes.manage';
    case CustomersManage = 'customers.manage';
    case ContactsPortal = 'contacts.portal';
    case TemplatesManage = 'templates.manage';
    case AdminUsers = 'admin.users';
    case AdminGroups = 'admin.groups';
    case AdminMailboxes = 'admin.mailboxes';
    case AdminSettings = 'admin.settings';

    public function label(): string
    {
        return match ($this) {
            self::TicketsViewOwn => 'Eigene Tickets sehen',
            self::TicketsViewGroup => 'Tickets der eigenen Gruppen sehen',
            self::TicketsViewAll => 'Alle Tickets sehen',
            self::TicketsEdit => 'Tickets bearbeiten',
            self::TicketsDelete => 'Tickets löschen',
            self::TicketsMerge => 'Tickets zusammenführen',
            self::NotesManage => 'Interne Notizen verwalten',
            self::CustomersManage => 'Kunden verwalten',
            self::ContactsPortal => 'Portal-Zugänge verwalten',
            self::TemplatesManage => 'Vorlagen verwalten',
            self::AdminUsers => 'Benutzer verwalten',
            self::AdminGroups => 'Gruppen verwalten',
            self::AdminMailboxes => 'Postfächer verwalten',
            self::AdminSettings => 'Systemeinstellungen verwalten',
        };
    }

    /**
     * Permissions that grant access to the admin area (/intern/admin).
     *
     * @return list<self>
     */
    public static function admin(): array
    {
        return [self::AdminUsers, self::AdminGroups, self::AdminMailboxes, self::AdminSettings];
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
