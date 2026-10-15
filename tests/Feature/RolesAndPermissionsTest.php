<?php

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\Contact;
use App\Models\Customer;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Spatie\Permission\Models\Permission as PermissionModel;
use Spatie\Permission\Models\Role as RoleModel;

it('defines exactly the permissions from the plan', function () {
    expect(Permission::values())->toEqualCanonicalizing([
        'tickets.view.own', 'tickets.view.group', 'tickets.view.all', 'tickets.edit', 'tickets.delete',
        'tickets.merge', 'notes.manage', 'customers.manage', 'contacts.portal', 'templates.manage',
        'admin.users', 'admin.groups', 'admin.mailboxes', 'admin.settings', 'admin.logs',
    ]);
});

it('has a German label for every permission', function () {
    foreach (Permission::cases() as $permission) {
        expect($permission->label())->not->toBeEmpty()->not->toBe($permission->value);
    }
});

it('seeds all permissions and both roles', function () {
    expect(PermissionModel::pluck('name')->all())->toEqualCanonicalizing(Permission::values())
        ->and(RoleModel::pluck('name')->all())->toEqualCanonicalizing(['Admin', 'Mitarbeiter']);
});

it('gives the Admin role every permission', function () {
    expect(RoleModel::findByName('Admin')->permissions->pluck('name')->all())
        ->toEqualCanonicalizing(Permission::values());
});

it('gives the Mitarbeiter role the agreed permissions only', function () {
    expect(RoleModel::findByName('Mitarbeiter')->permissions->pluck('name')->all())
        ->toEqualCanonicalizing([
            'tickets.view.own', 'tickets.view.group', 'tickets.edit',
            'notes.manage', 'customers.manage', 'contacts.portal',
        ]);
});

it('can run the seeder repeatedly without duplicates and restores the defaults', function () {
    RoleModel::findByName('Mitarbeiter')->givePermissionTo('admin.users');

    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(RolesAndPermissionsSeeder::class);

    expect(PermissionModel::count())->toBe(count(Permission::cases()))
        ->and(RoleModel::count())->toBe(2)
        ->and(RoleModel::findByName('Mitarbeiter')->hasPermissionTo('admin.users'))->toBeFalse();
});

it('lets admins do everything', function () {
    $admin = User::factory()->admin()->create();

    foreach (Permission::cases() as $permission) {
        expect($admin->can($permission->value))->toBeTrue();
    }
});

it('keeps Mitarbeiter out of the admin permissions', function () {
    $staff = User::factory()->staff()->create();

    expect($staff->can(Permission::TicketsEdit->value))->toBeTrue();

    foreach ([...Permission::admin(), Permission::TicketsViewAll, Permission::TicketsDelete, Permission::TicketsMerge, Permission::TemplatesManage] as $permission) {
        expect($staff->can($permission->value))->toBeFalse();
    }
});

it('denies everything to users without a role', function () {
    $user = User::factory()->create();

    foreach (Permission::cases() as $permission) {
        expect($user->can($permission->value))->toBeFalse();
    }
});

it('accepts the permission enum in gate checks', function () {
    expect(User::factory()->admin()->create()->can(Permission::AdminUsers))->toBeTrue()
        ->and(User::factory()->staff()->create()->can(Permission::AdminUsers))->toBeFalse();
});

it('creates the local admin only in the local environment', function () {
    $this->seed(DatabaseSeeder::class);
    expect(User::where('email', config('servicedesk.seed_admin.email'))->exists())->toBeFalse();

    app()->detectEnvironment(fn () => 'local');
    $this->seed(DatabaseSeeder::class);

    $admin = User::where('email', config('servicedesk.seed_admin.email'))->first();
    expect($admin)->not->toBeNull()
        ->and($admin->hasRole(Role::Admin->value))->toBeTrue();
});

it('seeds demo customers only in the local environment', function () {
    $this->seed(DatabaseSeeder::class);
    expect(Customer::count())->toBe(0);

    app()->detectEnvironment(fn () => 'local');
    $this->seed(DatabaseSeeder::class);
    expect(Customer::count())->toBe(13)
        ->and(Contact::count())->toBeGreaterThanOrEqual(12);
});
