<?php

/*
 * PLAN.md, Phase 1 – "Fertig wenn": Admin kann sich mit 2FA anmelden,
 * Benutzer und Gruppen anlegen, Darkmode funktioniert, Tests für Login und
 * Rechte laufen grün.
 */

use App\Enums\Theme;
use App\Livewire\Intern\Admin\Groups\Index as GroupIndex;
use App\Livewire\Intern\Admin\Users\Index as UserIndex;
use App\Models\Group;
use App\Models\User;
use Livewire\Livewire;
use PragmaRX\Google2FA\Google2FA;

it('lets an admin log in with 2FA, create a group and a user, and switch to dark mode', function () {
    $admin = User::factory()->admin()->withTwoFactor()->create(['email' => 'admin@example.com']);

    // Login with password, then the code from the authenticator app
    $this->post('/intern/login', ['email' => 'admin@example.com', 'password' => 'password'])
        ->assertRedirect('/intern/two-factor-challenge');
    $this->assertGuest();

    $code = app(Google2FA::class)->getCurrentOtp(decrypt($admin->two_factor_secret));
    $this->post('/intern/two-factor-challenge', ['code' => $code])->assertRedirect('/intern');
    $this->assertAuthenticatedAs($admin);

    // Group
    $this->get('/intern/admin/gruppen')->assertOk();
    Livewire::test(GroupIndex::class)
        ->call('create')
        ->set('name', 'Support')
        ->call('save')
        ->assertHasNoErrors();
    $group = Group::where('name', 'Support')->sole();

    // User in that group
    $this->get('/intern/admin/benutzer')->assertOk();
    Livewire::test(UserIndex::class)
        ->call('create')
        ->set('name', 'Jonas Weber')
        ->set('email', 'jonas@example.com')
        ->set('password', 'Startpasswort2026')
        ->set('groups', [$group->id])
        ->call('save')
        ->assertHasNoErrors();
    $jonas = User::where('email', 'jonas@example.com')->sole();
    expect($jonas->groups->pluck('name')->all())->toBe(['Support'])
        ->and($jonas->can('tickets.edit'))->toBeTrue()
        ->and($jonas->can('admin.users'))->toBeFalse();

    // Dark mode
    $this->putJson('/intern/darstellung', ['theme' => 'dark'])->assertNoContent();
    expect($admin->refresh()->theme)->toBe(Theme::Dark);
    $this->get('/intern/tickets')->assertSee('class="h-full dark"', false);
});
