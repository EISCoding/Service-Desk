<?php

use App\Models\User;
use App\Support\Navigation;

it('shows sidebar, search and user menu in the staff area', function () {
    $user = User::factory()->staff()->create(['name' => 'Anna Becker', 'email' => 'anna@example.com']);

    $this->actingAs($user)
        ->get('/intern/tickets')
        ->assertOk()
        ->assertSee('<title>Tickets · ServiceDesk</title>', false)
        ->assertSeeInOrder(['Tickets', 'Kunden', 'Termine', 'Vorlagen'])
        ->assertSee('role="search"', false)
        ->assertSee('Anna Becker')
        ->assertSee('anna@example.com')
        ->assertSee('Abmelden')
        ->assertSee('role="radiogroup"', false);
});

it('marks the current page in the navigation', function () {
    $this->actingAs(User::factory()->staff()->create())
        ->get('/intern/kunden')
        ->assertOk()
        ->assertSee('aria-current="page"', false);
});

it('renders placeholder pages for upcoming areas', function (string $url, string $title) {
    $this->actingAs(User::factory()->staff()->create())
        ->get($url)
        ->assertOk()
        ->assertSee($title);
})->with([
    ['/intern/tickets', 'Tickets'],
    ['/intern/kunden', 'Kunden'],
]);

it('hides the admin navigation from staff', function () {
    $staff = User::factory()->staff()->create();

    expect(collect(Navigation::main($staff))->pluck('label'))->not->toContain('Admin');

    $this->actingAs($staff)
        ->get('/intern/tickets')
        ->assertDontSee(route('intern.admin.index'));
});

it('shows the admin navigation to admins', function () {
    $admin = User::factory()->admin()->create();

    expect(collect(Navigation::main($admin))->pluck('label'))->toContain('Admin');

    $this->actingAs($admin)
        ->get('/intern/tickets')
        ->assertSee(route('intern.admin.index'));
});

it('shows the admin entry to users with a single admin permission', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('admin.groups');

    expect(collect(Navigation::main($user))->pluck('label'))->toContain('Admin');
});

it('protects the admin area', function () {
    $this->actingAs(User::factory()->staff()->create())
        ->get('/intern/admin')
        ->assertForbidden();

    $this->actingAs(User::factory()->admin()->create())
        ->get('/intern/admin')
        ->assertOk()
        ->assertSee('Admin');
});

it('requires a login for every staff page', function (string $url) {
    $this->get($url)->assertRedirect('/intern/login');
})->with(['/intern/tickets', '/intern/kunden', '/intern/termine', '/intern/vorlagen', '/intern/admin']);
