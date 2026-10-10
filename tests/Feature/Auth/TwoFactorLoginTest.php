<?php

use App\Models\User;
use PragmaRX\Google2FA\Google2FA;

function currentOtp(User $user): string
{
    return app(Google2FA::class)->getCurrentOtp(decrypt($user->two_factor_secret));
}

it('logs users without two-factor authentication in directly', function () {
    $user = User::factory()->create();

    $this->post('/intern/login', ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect('/intern');

    $this->assertAuthenticatedAs($user);
});

it('asks users with two-factor authentication for a code', function () {
    $user = User::factory()->withTwoFactor()->create();

    $this->post('/intern/login', ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect('/intern/two-factor-challenge');

    $this->assertGuest();
    $this->get('/intern/two-factor-challenge')->assertOk()->assertSee('Bestätigungscode');
});

it('completes the login with a valid code from the app', function () {
    $user = User::factory()->withTwoFactor()->create();

    $this->post('/intern/login', ['email' => $user->email, 'password' => 'password']);
    $this->post('/intern/two-factor-challenge', ['code' => currentOtp($user)])
        ->assertRedirect('/intern');

    $this->assertAuthenticatedAs($user);
});

it('rejects an invalid code', function () {
    $user = User::factory()->withTwoFactor()->create();

    $this->post('/intern/login', ['email' => $user->email, 'password' => 'password']);
    $this->post('/intern/two-factor-challenge', ['code' => '000000'])
        ->assertSessionHasErrors(['code' => 'Der Bestätigungscode ist ungültig.']);

    $this->assertGuest();
});

it('accepts a recovery code once', function () {
    $user = User::factory()->withTwoFactor()->create();

    $this->post('/intern/login', ['email' => $user->email, 'password' => 'password']);
    $this->post('/intern/two-factor-challenge', ['recovery_code' => 'recovery-code-1'])
        ->assertRedirect('/intern');

    $this->assertAuthenticatedAs($user);
    expect($user->refresh()->recoveryCodes())->not->toContain('recovery-code-1');
});

it('does not ask for a code while the setup is not confirmed', function () {
    $user = User::factory()->withTwoFactor()->create(['two_factor_confirmed_at' => null]);

    $this->post('/intern/login', ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect('/intern');

    $this->assertAuthenticatedAs($user);
});
