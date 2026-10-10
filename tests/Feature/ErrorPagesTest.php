<?php

use App\Models\Ticket;
use App\Models\User;

it('renders 404 in the design with a way back', function () {
    $this->get('/gibt-es-nicht')->assertNotFound()
        ->assertSee('<title>Seite nicht gefunden · ServiceDesk</title>', false)
        ->assertSee('Zur Startseite')
        ->assertSee('href="'.url('/intern').'"', false);

    $this->get('/portal/gibt-es-nicht')->assertNotFound()->assertSee('href="'.url('/portal').'"', false);
});

it('renders 403 in the design', function () {
    $this->actingAs(User::factory()->staff()->create())
        ->get('/intern/admin')
        ->assertForbidden()
        ->assertSee('Kein Zugriff')
        ->assertSee('Für diese Seite fehlen die nötigen Rechte.');
});

it('has pages for server errors, expired sessions and maintenance', function (int $code, string $title) {
    expect(view("errors.{$code}")->render())->toContain($title)->toContain((string) $code);
})->with([
    [419, 'Sitzung abgelaufen'],
    [429, 'Zu viele Anfragen'],
    [500, 'Etwas ist schiefgelaufen'],
    [503, 'Wartungsarbeiten'],
]);

it('shows empty states instead of blank lists', function () {
    $this->actingAs(User::factory()->admin()->create());

    $this->get('/intern/kunden')->assertSee('Noch keine Kunden');
    $this->get('/intern/tickets')->assertSee('Keine Tickets');
    expect(Ticket::count())->toBe(0);
});
