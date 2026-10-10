<?php

use App\Livewire\Intern\Settings\Security;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use PragmaRX\Google2FA\Google2FA;

it('requires a recently confirmed password', function () {
    $this->actingAs(User::factory()->create())
        ->get('/intern/einstellungen/sicherheit')
        ->assertRedirect('/intern/user/confirm-password');
});

it('shows the security page after confirming the password', function () {
    $this->actingAs(User::factory()->create())
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get('/intern/einstellungen/sicherheit')
        ->assertOk()
        ->assertSee('<title>Sicherheit · ServiceDesk</title>', false)
        ->assertSee('Zwei-Faktor-Authentifizierung');
});

it('is not reachable for guests', function () {
    $this->get('/intern/einstellungen/sicherheit')->assertRedirect('/intern/login');
});

it('sets up two-factor authentication in two steps', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire::test(Security::class)
        ->assertSee('Aktivieren')
        ->call('enableTwoFactor')
        ->assertSee('QR-Code');

    $user->refresh();
    expect($user->two_factor_secret)->not->toBeNull()
        ->and($user->hasTwoFactorEnabled())->toBeFalse();

    $code = app(Google2FA::class)->getCurrentOtp(decrypt($user->two_factor_secret));

    Livewire::test(Security::class)
        ->set('code', $code)
        ->call('confirmTwoFactor')
        ->assertHasNoErrors()
        ->assertSet('showingRecoveryCodes', true)
        ->assertDispatched('toast');

    expect($user->refresh()->hasTwoFactorEnabled())->toBeTrue()
        ->and($user->recoveryCodes())->toHaveCount(8);
});

it('rejects a wrong confirmation code', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire::test(Security::class)
        ->call('enableTwoFactor')
        ->set('code', '000000')
        ->call('confirmTwoFactor')
        ->assertHasErrors('code');

    expect($user->refresh()->hasTwoFactorEnabled())->toBeFalse();
});

it('can cancel the setup', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire::test(Security::class)
        ->call('enableTwoFactor')
        ->call('cancelTwoFactorSetup');

    expect($user->refresh()->two_factor_secret)->toBeNull();
});

it('regenerates recovery codes', function () {
    $user = User::factory()->withTwoFactor()->create();
    $this->actingAs($user);

    Livewire::test(Security::class)->call('regenerateRecoveryCodes');

    expect($user->refresh()->recoveryCodes())->not->toContain('recovery-code-1')->toHaveCount(8);
});

it('disables two-factor authentication', function () {
    $user = User::factory()->withTwoFactor()->create();
    $this->actingAs($user);

    Livewire::test(Security::class)
        ->assertSee('Aktiv')
        ->call('disableTwoFactor');

    $user->refresh();
    expect($user->hasTwoFactorEnabled())->toBeFalse()
        ->and($user->two_factor_secret)->toBeNull();
});

it('changes the password', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire::test(Security::class)
        ->set('current_password', 'password')
        ->set('password', 'NeuesPasswort2026')
        ->set('password_confirmation', 'NeuesPasswort2026')
        ->call('updatePassword')
        ->assertHasNoErrors()
        ->assertSet('password', '');

    expect(Hash::check('NeuesPasswort2026', $user->refresh()->password))->toBeTrue();
});

it('validates the password change', function (array $input, string $field) {
    $this->actingAs(User::factory()->create());

    Livewire::test(Security::class)
        ->set($input)
        ->call('updatePassword')
        ->assertHasErrors($field);
})->with([
    'wrong current password' => [['current_password' => 'falsch', 'password' => 'NeuesPasswort2026', 'password_confirmation' => 'NeuesPasswort2026'], 'current_password'],
    'too short' => [['current_password' => 'password', 'password' => 'kurz1', 'password_confirmation' => 'kurz1'], 'password'],
    'no number' => [['current_password' => 'password', 'password' => 'nurbuchstaben', 'password_confirmation' => 'nurbuchstaben'], 'password'],
    'not confirmed' => [['current_password' => 'password', 'password' => 'NeuesPasswort2026', 'password_confirmation' => 'Anderes2026xx'], 'password'],
]);
