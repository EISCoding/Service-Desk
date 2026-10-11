<?php

use App\Enums\Theme;
use App\Livewire\Portal\Profile;
use App\Models\Contact;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

beforeEach(function () {
    $this->contact = Contact::factory()->withPortalAccess()->create(['email' => 'anna@example.com', 'password' => 'Kundenportal2026', 'phone' => null]);
    $this->actingAs($this->contact, 'contact');
});

it('shows the profile page', function () {
    $this->get('/portal/profil')->assertOk()
        ->assertSee('<title>Profil · ServiceDesk</title>', false)
        ->assertSee(['Ihre Angaben', 'anna@example.com', 'Passwort ändern', 'Darstellung']);
});

it('updates name and phone and logs the change', function () {
    Livewire::test(Profile::class)
        ->set('salutation', 'frau')
        ->set('first_name', 'Anna')
        ->set('last_name', 'Berg')
        ->set('phone', '+49 30 123456')
        ->call('saveProfile')
        ->assertHasNoErrors()
        ->assertDispatched('toast', message: 'Ihre Angaben wurden gespeichert.');

    expect($this->contact->refresh())
        ->last_name->toBe('Berg')->phone->toBe('+49 30 123456')->email->toBe('anna@example.com')
        ->and($this->contact->activities()->latest('id')->first()->causer->is($this->contact))->toBeTrue();

    Livewire::test(Profile::class)->set('last_name', '')->call('saveProfile')->assertHasErrors('last_name');
});

it('changes the password only with the current one', function () {
    Livewire::test(Profile::class)
        ->set('current_password', 'falsch')->set('password', 'NeuesPasswort2026')->set('password_confirmation', 'NeuesPasswort2026')
        ->call('savePassword')
        ->assertHasErrors('current_password');

    Livewire::test(Profile::class)
        ->set('current_password', 'Kundenportal2026')->set('password', 'NeuesPasswort2026')->set('password_confirmation', 'NeuesPasswort2026')
        ->call('savePassword')
        ->assertHasNoErrors()
        ->assertSet('password', '');

    expect(Hash::check('NeuesPasswort2026', $this->contact->refresh()->password))->toBeTrue();
});

it('stores the appearance for the contact', function () {
    $this->putJson('/portal/darstellung', ['theme' => 'dark'])->assertNoContent();

    expect($this->contact->refresh()->theme)->toBe(Theme::Dark);
    $this->get('/portal')->assertSee('data-theme="dark"', false)->assertSee('portal\\/darstellung', false);
});
