<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

it('shows the forgot password page', function () {
    $this->get('/intern/forgot-password')->assertOk()->assertSee('Link anfordern');
});

it('sends a reset link that points into the staff area', function () {
    Notification::fake();
    $user = User::factory()->create();

    $this->post('/intern/forgot-password', ['email' => $user->email])
        ->assertSessionHas('status');

    Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user) {
        $url = $notification->toMail($user)->actionUrl;

        return str_contains($url, '/intern/reset-password/');
    });
});

it('does not reveal whether an email address exists', function () {
    Notification::fake();

    $this->post('/intern/forgot-password', ['email' => 'niemand@example.com'])
        ->assertSessionHasNoErrors()
        ->assertSessionHas('status');

    Notification::assertNothingSent();
});

it('shows the reset form for a token', function () {
    $this->get('/intern/reset-password/token?email=anna@example.com')
        ->assertOk()
        ->assertSee('Neues Passwort festlegen')
        ->assertSee('anna@example.com');
});

it('resets the password with a valid token', function () {
    $user = User::factory()->create();
    $token = Password::createToken($user);

    $this->post('/intern/reset-password', [
        'token' => $token,
        'email' => $user->email,
        'password' => 'NeuesPasswort2026',
        'password_confirmation' => 'NeuesPasswort2026',
    ])->assertRedirect('/intern/login');

    expect(Hash::check('NeuesPasswort2026', $user->refresh()->password))->toBeTrue();
});

it('enforces the password policy on reset', function () {
    $user = User::factory()->create();

    $this->post('/intern/reset-password', [
        'token' => Password::createToken($user),
        'email' => $user->email,
        'password' => 'kurz',
        'password_confirmation' => 'kurz',
    ])->assertSessionHasErrors('password');

    expect(Hash::check('password', $user->refresh()->password))->toBeTrue();
});
