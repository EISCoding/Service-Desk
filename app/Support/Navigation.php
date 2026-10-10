<?php

namespace App\Support;

use App\Enums\Permission;
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
            ['label' => 'Tickets', 'route' => 'intern.tickets.index', 'icon' => 'inbox-stack', 'active' => 'intern.tickets.*'],
            ['label' => 'Kunden', 'route' => 'intern.customers.index', 'icon' => 'building-office-2', 'active' => 'intern.customers.*', 'ability' => Permission::CustomersManage->value],
            ['label' => 'Termine', 'route' => 'intern.appointments.index', 'icon' => 'calendar-days', 'active' => 'intern.appointments.*'],
            ['label' => 'Vorlagen', 'route' => 'intern.templates.index', 'icon' => 'document-duplicate', 'active' => 'intern.templates.*'],
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
        ]);
    }

    /**
     * @param  list<array<string, string>>  $items
     * @return list<array{label: string, route: string, icon: string, active: string}>
     */
    private static function visible(User $user, array $items): array
    {
        $visible = array_filter($items, fn (array $item) => Route::has($item['route'])
            && (! isset($item['ability']) || $user->can($item['ability'])));

        return array_values(array_map(fn (array $item) => array_diff_key($item, ['ability' => true]), $visible));
    }
}
