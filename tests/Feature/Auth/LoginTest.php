<?php

use App\Models\User;

it('shows the staff login under /intern/login', function () {
    $this->get('/intern/login')
        ->assertOk()
        ->assertSee('Anmelden')
        ->assertSee('Passwort vergessen?');
});

it('redirects guests from the staff area to the login', function () {
    $this->get('/intern')->assertRedirect('/intern/login');
});

it('logs staff in and sends them to /intern', function () {
    $user = User::factory()->staff()->create(['email' => 'anna@example.com']);

    $this->post('/intern/login', ['email' => 'anna@example.com', 'password' => 'password'])
        ->assertRedirect('/intern');

    $this->assertAuthenticatedAs($user);
    $this->get('/intern')->assertRedirect(route('intern.tickets.index'));
    $this->get('/intern/tickets')->assertOk()->assertSee($user->name);
});

it('accepts the email address case-insensitively', function () {
    $user = User::factory()->create(['email' => 'anna@example.com']);

    $this->post('/intern/login', ['email' => 'Anna@Example.com', 'password' => 'password']);

    $this->assertAuthenticatedAs($user);
});

it('rejects a wrong password with a German message', function () {
    User::factory()->create(['email' => 'anna@example.com']);

    $this->from('/intern/login')
        ->post('/intern/login', ['email' => 'anna@example.com', 'password' => 'falsch'])
        ->assertRedirect('/intern/login')
        ->assertSessionHasErrors(['email' => 'Diese Zugangsdaten sind uns nicht bekannt.']);

    $this->assertGuest();
});

it('throttles repeated failed logins', function () {
    User::factory()->create(['email' => 'anna@example.com']);

    foreach (range(1, 5) as $attempt) {
        $this->post('/intern/login', ['email' => 'anna@example.com', 'password' => 'falsch']);
    }

    $this->post('/intern/login', ['email' => 'anna@example.com', 'password' => 'password'])
        ->assertStatus(429);

    $this->assertGuest();
});

it('logs out and returns to the login page', function () {
    $this->actingAs(User::factory()->create())
        ->post('/intern/logout')
        ->assertRedirect('/intern/login');

    $this->assertGuest();
});

it('has no self registration', function () {
    $this->get('/intern/register')->assertNotFound();
    $this->post('/intern/register', [])->assertNotFound();
});
