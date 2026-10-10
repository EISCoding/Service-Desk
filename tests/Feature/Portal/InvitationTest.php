<?php

use App\Models\Activity;
use App\Models\Contact;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;

beforeEach(function () {
    $this->contact = Contact::factory()->withPortalAccess()->create(['email' => 'anna@example.com', 'first_name' => 'Anna', 'last_name' => 'Becker']);
    $this->token = Password::broker('contact_invitations')->createToken($this->contact);
});

it('shows the set password page in formal German', function () {
    $this->get("/portal/einladung/{$this->token}?email=anna@example.com")
        ->assertOk()
        ->assertSee('Willkommen im Kundenportal')
        ->assertSee('Bitte legen Sie Ihr Passwort fest.')
        ->assertSee('anna@example.com');
});

it('rejects unknown tokens and contacts without portal access', function () {
    $this->get('/portal/einladung/falsch?email=anna@example.com')
        ->assertOk()
        ->assertSee('ungültig oder abgelaufen')
        ->assertDontSee('Passwort festlegen</button>', false);

    $this->contact->update(['portal_access' => false]);
    $this->get("/portal/einladung/{$this->token}?email=anna@example.com")->assertSee('ungültig oder abgelaufen');
});

it('sets the password and logs it', function () {
    $this->post('/portal/einladung', [
        'token' => $this->token,
        'email' => 'anna@example.com',
        'password' => 'Kundenportal2026',
        'password_confirmation' => 'Kundenportal2026',
    ])->assertRedirect('/portal/willkommen');

    $this->contact->refresh();
    expect(Hash::check('Kundenportal2026', $this->contact->password))->toBeTrue()
        ->and($this->contact->portalStatus())->toBe('active')
        ->and(Auth::guard('contact')->attempt(['email' => 'anna@example.com', 'password' => 'Kundenportal2026']))->toBeTrue();

    $activity = Activity::forHistoryOf($this->contact->customer)->latest('id')->first();
    expect($activity->action)->toBe('portal_password_set')
        ->and($activity->causer->is($this->contact))->toBeTrue()
        ->and(json_encode($activity))->not->toContain('Kundenportal2026');

    $this->get('/portal/willkommen')->assertOk()->assertSee('Passwort gespeichert');
});

it('works only once', function () {
    $data = ['token' => $this->token, 'email' => 'anna@example.com', 'password' => 'Kundenportal2026', 'password_confirmation' => 'Kundenportal2026'];

    $this->post('/portal/einladung', $data)->assertRedirect('/portal/willkommen');
    $this->post('/portal/einladung', $data)->assertSessionHasErrors('email');
});

it('expires after 7 days', function () {
    $this->travel(8)->days();

    $this->post('/portal/einladung', [
        'token' => $this->token, 'email' => 'anna@example.com',
        'password' => 'Kundenportal2026', 'password_confirmation' => 'Kundenportal2026',
    ])->assertSessionHasErrors(['email' => 'Dieser Einladungslink ist ungültig oder abgelaufen. Bitte fordern Sie bei uns eine neue Einladung an.']);

    expect($this->contact->refresh()->password)->toBeNull();
});

it('enforces the password policy', function () {
    $this->post('/portal/einladung', [
        'token' => $this->token, 'email' => 'anna@example.com',
        'password' => 'kurz', 'password_confirmation' => 'kurz',
    ])->assertSessionHasErrors('password');
});

it('does not log in staff accounts with the same address', function () {
    $this->post('/portal/einladung', [
        'token' => $this->token, 'email' => 'anna@example.com',
        'password' => 'Kundenportal2026', 'password_confirmation' => 'Kundenportal2026',
    ]);

    expect(Auth::guard('web')->check())->toBeFalse();
});
