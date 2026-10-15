<?php

namespace App\Support;

use App\Enums\Permission;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\Route;

/**
 * Navigation of the staff area, shared by the desktop sidebar and the mobile tab bar.
 * Items are filtered by permission and only shown once their route exists.
 */
final class Navigation
{
    /**
     * @return list<array{label: string, route: string, icon: string, active: string}>
     */
    public static function main(User $user): array
    {
        return self::visible($user, [
            ['label' => 'Übersicht', 'route' => 'intern.dashboard', 'icon' => 'home', 'active' => 'intern.dashboard', 'ability' => ['viewAny', Ticket::class]],
            ['label' => 'Tickets', 'route' => 'intern.tickets.index', 'icon' => 'inbox-stack', 'active' => 'intern.tickets.*', 'ability' => ['viewAny', Ticket::class]],
            ['label' => 'Kunden', 'route' => 'intern.customers.index', 'icon' => 'building-office-2', 'active' => 'intern.customers.*', 'ability' => Permission::CustomersManage->value],
            ['label' => 'Termine', 'route' => 'intern.appointments.index', 'icon' => 'calendar-days', 'active' => 'intern.appointments.*', 'ability' => ['viewAny', Ticket::class]],
            ['label' => 'Vorlagen', 'route' => 'intern.templates.index', 'icon' => 'document-duplicate', 'active' => 'intern.templates.*', 'ability' => ['viewAny', Ticket::class]],
            ['label' => 'Admin', 'route' => 'intern.admin.index', 'icon' => 'cog-6-tooth', 'active' => 'intern.admin.*', 'ability' => 'access-admin'],
        ]);
    }

    /**
     * @return list<array{label: string, route: string, icon: string, active: string}>
     */
    public static function admin(User $user): array
    {
        return self::visible($user, [
            ['label' => 'Benutzer', 'route' => 'intern.admin.users.index', 'icon' => 'users', 'active' => 'intern.admin.users.*', 'ability' => Permission::AdminUsers->value],
            ['label' => 'Gruppen', 'route' => 'intern.admin.groups.index', 'icon' => 'user-group', 'active' => 'intern.admin.groups.*', 'ability' => Permission::AdminGroups->value],
            ['label' => 'Postfächer', 'route' => 'intern.admin.mailboxes.index', 'icon' => 'inbox', 'active' => 'intern.admin.mailboxes.*', 'ability' => Permission::AdminMailboxes->value],
            ['label' => 'Systemeinstellungen', 'route' => 'intern.admin.settings', 'icon' => 'adjustments-horizontal', 'active' => 'intern.admin.settings', 'ability' => Permission::AdminSettings->value],
            ['label' => 'Protokolle', 'route' => 'intern.admin.logs', 'icon' => 'document-magnifying-glass', 'active' => 'intern.admin.logs*', 'ability' => Permission::AdminLogs->value],
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<array{label: string, route: string, icon: string, active: string}>
     */
    private static function visible(User $user, array $items): array
    {
        $visible = array_filter($items, fn (array $item) => Route::has($item['route'])
            && (! isset($item['ability']) || $user->can(...(array) $item['ability'])));

        return array_values(array_map(fn (array $item) => array_diff_key($item, ['ability' => true]), $visible));
    }
}
