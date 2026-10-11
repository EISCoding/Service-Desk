<?php

use App\Enums\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('creates an admin with options', function () {
    $this->artisan('servicedesk:create-admin', ['--name' => 'Erika Admin', '--email' => 'Erika@Firma.de', '--password' => 'Sicher2026Passwort'])
        ->expectsOutputToContain('Admin erika@firma.de angelegt')
        ->assertSuccessful();

    $user = User::sole();
    expect($user->hasRole(Role::Admin->value))->toBeTrue()
        ->and($user->is_active)->toBeTrue()
        ->and(Hash::check('Sicher2026Passwort', $user->password))->toBeTrue();
});

it('asks for missing values', function () {
    $this->artisan('servicedesk:create-admin')
        ->expectsQuestion('Name', 'Erika Admin')
        ->expectsQuestion('E-Mail-Adresse', 'erika@firma.de')
        ->expectsQuestion('Passwort (mindestens 10 Zeichen, Buchstaben und Zahlen)', 'Sicher2026Passwort')
        ->assertSuccessful();

    expect(User::sole()->email)->toBe('erika@firma.de');
});

it('rejects weak passwords and taken addresses', function () {
    User::factory()->create(['email' => 'erika@firma.de']);

    $this->artisan('servicedesk:create-admin', ['--name' => 'X', '--email' => 'erika@firma.de', '--password' => 'kurz'])
        ->assertFailed();

    expect(User::count())->toBe(1);
});
