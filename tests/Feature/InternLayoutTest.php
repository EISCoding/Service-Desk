<?php

use App\Livewire\Intern\SidebarViews;
use App\Livewire\Intern\Tickets\CreateTicket;
use App\Models\Activity;
use App\Models\Ticket;
use App\Models\User;
use App\Support\Navigation;
use Livewire\Livewire;

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

it('collapses the sidebar to icons and remembers it per user', function () {
    $user = User::factory()->staff()->create();

    $this->actingAs($user)->get('/intern')->assertSee('data-sidebar="expanded"', false)->assertSee('Einklappen');

    $this->putJson('/intern/seitenleiste', ['collapsed' => true])->assertNoContent();
    expect($user->fresh()->sidebar_collapsed)->toBeTrue();

    // Labels stay available to screen readers; the tooltip text is on each entry.
    $this->get('/intern')->assertSee('data-sidebar="collapsed"', false)->assertSee('data-tip="Tickets"', false);

    $this->putJson('/intern/seitenleiste', ['collapsed' => 'vielleicht'])->assertUnprocessable();
    $this->putJson('/intern/seitenleiste', ['collapsed' => false])->assertNoContent();
    expect($user->fresh()->sidebar_collapsed)->toBeFalse();
});

it('does not log the sidebar preference as an activity', function () {
    $user = User::factory()->staff()->create();

    $before = Activity::count();

    $this->actingAs($user)->putJson('/intern/seitenleiste', ['collapsed' => true]);

    expect(Activity::count())->toBe($before);
});

it('shows ticket views with live counts in the sidebar', function () {
    $user = User::factory()->staff()->create();
    Ticket::factory()->count(2)->create(['assignee_id' => $user->id]);
    Ticket::factory()->create(['assignee_id' => null]);

    $this->actingAs($user);

    Livewire::test(SidebarViews::class)
        ->assertSee(['Meine offenen', 'Neue Kundenantworten', 'Wiedervorlage fällig', 'Nicht zugeordnet', 'Alle offenen'])
        ->assertSee(route('intern.tickets.index', ['ansicht' => 'mine']), false)
        ->assertSet('counts.mine', 2)
        ->assertSet('counts.unassigned', 1)
        ->assertSet('counts.all', 3);

    // The ticket list opens with the matching view.
    $this->get('/intern/tickets?ansicht=all')->assertOk()->assertSee('aria-current="page"', false);
});

it('offers "Neues Ticket" on every staff page', function () {
    $this->actingAs(User::factory()->staff()->create());

    $this->get('/intern/kunden')->assertOk()->assertSee('Neues Ticket')->assertSeeLivewire(CreateTicket::class);
    $this->get('/intern/termine')->assertOk()->assertSeeLivewire(CreateTicket::class);
});
