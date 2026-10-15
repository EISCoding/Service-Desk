<?php

use App\Livewire\Intern\Admin\Users\Index;
use App\Models\Group;
use App\Models\User;
use App\Support\Navigation;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

describe('access', function () {
    it('is available to admins under /intern/admin/benutzer', function () {
        $this->actingAs(User::factory()->admin()->create())
            ->get('/intern/admin/benutzer')
            ->assertOk()
            ->assertSee('<title>Benutzer · ServiceDesk</title>', false)
            ->assertSeeLivewire(Index::class);
    });

    it('is forbidden for staff', function () {
        $staff = User::factory()->staff()->create();

        $this->actingAs($staff)->get('/intern/admin/benutzer')->assertForbidden();
        Livewire::actingAs($staff)->test(Index::class)->assertForbidden();
        expect(Navigation::admin($staff))->toBe([]);
    });

    it('is shown in the admin navigation', function () {
        expect(collect(Navigation::admin(User::factory()->admin()->create()))->pluck('label')->all())
            ->toBe(['Benutzer', 'Gruppen', 'Postfächer', 'Erscheinungsbild', 'Systemeinstellungen', 'Protokolle']);
    });
});

describe('managing users', function () {
    beforeEach(function () {
        $this->admin = User::factory()->admin()->create(['name' => 'Ada Admin']);
        $this->actingAs($this->admin);
    });

    it('lists active users with roles, groups and status', function () {
        $anna = User::factory()->staff()->create(['name' => 'Anna Becker']);
        $anna->groups()->attach(Group::factory()->create(['name' => 'Support']));
        User::factory()->inactive()->create(['name' => 'Otto Inaktiv']);

        Livewire::test(Index::class)
            ->assertSee('Anna Becker')
            ->assertSee('Mitarbeiter')
            ->assertSee('Support')
            ->assertDontSee('Otto Inaktiv')
            ->set('status', 'inactive')
            ->assertSee('Otto Inaktiv')
            ->assertDontSee('Anna Becker')
            ->set('status', 'all')
            ->assertSee('Otto Inaktiv')
            ->assertSee('Anna Becker');
    });

    it('searches by name and email', function () {
        User::factory()->create(['name' => 'Anna Becker', 'email' => 'anna@example.com']);
        User::factory()->create(['name' => 'Jonas Weber', 'email' => 'jonas@firma.de']);

        Livewire::test(Index::class)
            ->set('search', 'firma.de')
            ->assertSee('Jonas Weber')
            ->assertDontSee('Anna Becker');
    });

    it('sorts by name in both directions', function () {
        User::factory()->create(['name' => 'Zora']);
        User::factory()->create(['name' => 'Bert']);

        Livewire::test(Index::class)
            ->assertSeeInOrder(['Ada Admin', 'Bert', 'Zora'])
            ->call('sort', 'name')
            ->assertSeeInOrder(['Zora', 'Bert', 'Ada Admin']);
    });

    it('creates a user with start password, role and groups', function () {
        $support = Group::factory()->create(['name' => 'Support']);

        Livewire::test(Index::class)
            ->call('create')
            ->assertSet('roles', ['Mitarbeiter'])
            ->set('name', 'Jonas Weber')
            ->set('email', 'Jonas@Example.com')
            ->set('password', 'Startpasswort2026')
            ->set('groups', [(string) $support->id])
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('showForm', false)
            ->assertDispatched('toast', message: 'Benutzer angelegt.');

        $user = User::where('email', 'jonas@example.com')->firstOrFail();

        expect($user->name)->toBe('Jonas Weber')
            ->and($user->is_active)->toBeTrue()
            ->and(Hash::check('Startpasswort2026', $user->password))->toBeTrue()
            ->and($user->getRoleNames()->all())->toBe(['Mitarbeiter'])
            ->and($user->groups->pluck('name')->all())->toBe(['Support'])
            ->and($user->activities->pluck('action')->all())->toContain('created', 'roles_changed', 'groups_changed');
    });

    it('lets the new user log in with the start password', function () {
        Livewire::test(Index::class)
            ->call('create')
            ->set('name', 'Jonas Weber')
            ->set('email', 'jonas@example.com')
            ->set('password', 'Startpasswort2026')
            ->call('save');

        auth()->logout();

        $this->post('/intern/login', ['email' => 'jonas@example.com', 'password' => 'Startpasswort2026'])
            ->assertRedirect('/intern');
    });

    it('generates a start password that meets the policy', function () {
        $component = Livewire::test(Index::class)->call('create')->call('generatePassword');

        $password = $component->get('password');

        expect(strlen($password))->toBe(14)
            ->and($password)->toMatch('/[a-zA-Z]/')
            ->and($password)->toMatch('/\d/');
    });

    it('validates the input', function (array $input, string $field) {
        User::factory()->create(['email' => 'vergeben@example.com']);

        Livewire::test(Index::class)
            ->call('create')
            ->set(array_merge(['name' => 'Neu', 'email' => 'neu@example.com', 'password' => 'Startpasswort2026'], $input))
            ->call('save')
            ->assertHasErrors($field);
    })->with([
        'name missing' => [['name' => ''], 'name'],
        'invalid email' => [['email' => 'keine-mail'], 'email'],
        'email taken' => [['email' => 'vergeben@example.com'], 'email'],
        'password missing' => [['password' => ''], 'password'],
        'password too weak' => [['password' => 'kurz'], 'password'],
        'unknown role' => [['roles' => ['Superheld']], 'roles.0'],
        'unknown group' => [['groups' => [999]], 'groups.0'],
    ]);

    it('edits a user without touching the password', function () {
        $user = User::factory()->staff()->create(['name' => 'Alt']);
        $hash = $user->password;

        Livewire::test(Index::class)
            ->call('edit', $user->id)
            ->assertSet('name', 'Alt')
            ->assertSet('roles', ['Mitarbeiter'])
            ->set('name', 'Neu')
            ->call('save')
            ->assertHasNoErrors();

        expect($user->refresh()->name)->toBe('Neu')
            ->and($user->password)->toBe($hash);
    });

    it('sets a new password when editing', function () {
        $user = User::factory()->create();

        Livewire::test(Index::class)
            ->call('edit', $user->id)
            ->set('password', 'NeuesPasswort2026')
            ->call('save');

        expect(Hash::check('NeuesPasswort2026', $user->refresh()->password))->toBeTrue();
    });

    it('changes roles of other users and logs it', function () {
        $user = User::factory()->staff()->create();

        Livewire::test(Index::class)
            ->call('edit', $user->id)
            ->set('roles', ['Admin'])
            ->call('save');

        expect($user->refresh()->getRoleNames()->all())->toBe(['Admin'])
            ->and($user->can('admin.users'))->toBeTrue()
            ->and($user->activities()->where('action', 'roles_changed')->first()->changes)
            ->toBe(['roles' => ['old' => ['Mitarbeiter'], 'new' => ['Admin']]]);
    });

    it('does not let admins change their own roles', function () {
        Livewire::test(Index::class)
            ->call('edit', $this->admin->id)
            ->assertSee('Die eigenen Rollen können nicht geändert werden.')
            ->set('roles', [])
            ->call('save')
            ->assertHasNoErrors();

        expect($this->admin->refresh()->getRoleNames()->all())->toBe(['Admin']);
    });

    it('deactivates and reactivates a user', function () {
        $user = User::factory()->create(['name' => 'Jonas']);

        Livewire::test(Index::class)
            ->call('toggleActive', $user->id)
            ->assertDispatched('toast', message: 'Jonas wurde deaktiviert.');

        expect($user->refresh()->is_active)->toBeFalse()
            ->and($user->activities()->first()->changes)->toBe(['is_active' => ['old' => true, 'new' => false]]);

        Livewire::test(Index::class)->call('toggleActive', $user->id);
        expect($user->refresh()->is_active)->toBeTrue();
    });

    it('deactivates via the edit form', function () {
        $user = User::factory()->create();

        Livewire::test(Index::class)
            ->call('edit', $user->id)
            ->set('isActive', false)
            ->call('save');

        expect($user->refresh()->is_active)->toBeFalse();
    });

    it('does not let admins deactivate themselves', function () {
        Livewire::test(Index::class)
            ->call('toggleActive', $this->admin->id)
            ->assertForbidden();

        Livewire::test(Index::class)
            ->call('edit', $this->admin->id)
            ->set('isActive', false)
            ->call('save');

        expect($this->admin->refresh()->is_active)->toBeTrue();
    });

    it('resets two-factor authentication of another user', function () {
        $user = User::factory()->withTwoFactor()->create();

        Livewire::test(Index::class)
            ->assertSee('2FA')
            ->call('resetTwoFactor', $user->id);

        $user->refresh();
        expect($user->hasTwoFactorEnabled())->toBeFalse()
            ->and($user->two_factor_secret)->toBeNull()
            ->and($user->activities()->first()->action)->toBe('two_factor_reset');
    });

    it('does not reset its own two-factor authentication here', function () {
        $this->admin->forceFill(['two_factor_secret' => encrypt('x'), 'two_factor_confirmed_at' => now()])->save();

        Livewire::test(Index::class)
            ->call('resetTwoFactor', $this->admin->id)
            ->assertForbidden();
    });
});

describe('deactivated users', function () {
    it('cannot log in', function () {
        User::factory()->inactive()->create(['email' => 'otto@example.com']);

        $this->post('/intern/login', ['email' => 'otto@example.com', 'password' => 'password'])
            ->assertSessionHasErrors(['email' => 'Dieses Benutzerkonto ist deaktiviert.']);

        $this->assertGuest();
    });

    it('still get the generic message with a wrong password', function () {
        User::factory()->inactive()->create(['email' => 'otto@example.com']);

        $this->post('/intern/login', ['email' => 'otto@example.com', 'password' => 'falsch'])
            ->assertSessionHasErrors(['email' => 'Diese Zugangsdaten sind uns nicht bekannt.']);
    });

    it('cannot log in with two-factor authentication either', function () {
        User::factory()->inactive()->withTwoFactor()->create(['email' => 'otto@example.com']);

        $this->post('/intern/login', ['email' => 'otto@example.com', 'password' => 'password'])
            ->assertSessionHasErrors('email');

        expect(session('login.id'))->toBeNull();
    });

    it('are logged out on their next request', function () {
        $user = User::factory()->staff()->create();
        $this->actingAs($user)->get('/intern/tickets')->assertOk();

        $user->update(['is_active' => false]);

        $this->get('/intern/tickets')
            ->assertRedirect('/intern/login')
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    });
});
