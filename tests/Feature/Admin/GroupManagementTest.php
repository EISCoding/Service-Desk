<?php

use App\Livewire\Intern\Admin\Groups\Index;
use App\Models\Activity;
use App\Models\Group;
use App\Models\User;
use App\Support\Navigation;
use Livewire\Livewire;

describe('access', function () {
    it('is available to admins under /intern/admin/gruppen', function () {
        $this->actingAs(User::factory()->admin()->create())
            ->get('/intern/admin/gruppen')
            ->assertOk()
            ->assertSee('<title>Gruppen · ServiceDesk</title>', false)
            ->assertSeeLivewire(Index::class);
    });

    it('is available with the admin.groups permission alone', function () {
        $user = User::factory()->create();
        $user->givePermissionTo('admin.groups');

        $this->actingAs($user)->get('/intern/admin/gruppen')->assertOk();
        expect(collect(Navigation::admin($user))->pluck('label')->all())->toBe(['Gruppen']);
    });

    it('is forbidden for staff without the permission', function () {
        $staff = User::factory()->staff()->create();

        $this->actingAs($staff)->get('/intern/admin/gruppen')->assertForbidden();
        Livewire::actingAs($staff)->test(Index::class)->assertForbidden();
    });

    it('requires a login', function () {
        $this->get('/intern/admin/gruppen')->assertRedirect('/intern/login');
    });
});

describe('managing groups', function () {
    beforeEach(function () {
        $this->admin = User::factory()->admin()->create();
        $this->actingAs($this->admin);
    });

    it('lists groups with their member count', function () {
        $group = Group::factory()->create(['name' => 'Support']);
        $group->users()->attach(User::factory()->count(2)->create());
        Group::factory()->create(['name' => 'Technik']);

        Livewire::test(Index::class)
            ->assertSeeInOrder(['Support', '2', 'Technik', 'Keine']);
    });

    it('shows an empty state without groups', function () {
        Livewire::test(Index::class)->assertSee('Noch keine Gruppen');
    });

    it('filters by search term', function () {
        Group::factory()->create(['name' => 'Support']);
        Group::factory()->create(['name' => 'Technik']);

        Livewire::test(Index::class)
            ->set('search', 'tech')
            ->assertSee('Technik')
            ->assertDontSee('Support');
    });

    it('creates a group with members', function () {
        [$anna, $jonas] = User::factory()->count(2)->create();

        Livewire::test(Index::class)
            ->call('create')
            ->assertSet('showForm', true)
            ->set('name', 'Support')
            ->set('description', 'Erste Ebene')
            ->set('members', [(string) $anna->id, (string) $jonas->id])
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('showForm', false)
            ->assertDispatched('toast', message: 'Gruppe angelegt.');

        $group = Group::where('name', 'Support')->firstOrFail();
        expect($group->description)->toBe('Erste Ebene')
            ->and($group->users->pluck('id')->sort()->values()->all())->toBe([$anna->id, $jonas->id])
            ->and($anna->groups->pluck('name')->all())->toBe(['Support']);
    });

    it('logs creation and member changes', function () {
        $anna = User::factory()->create(['name' => 'Anna']);

        Livewire::test(Index::class)
            ->call('create')
            ->set('name', 'Support')
            ->set('members', [$anna->id])
            ->call('save');

        $group = Group::with('activities.causer')->where('name', 'Support')->firstOrFail();

        expect($group->activities->pluck('action')->all())->toEqualCanonicalizing(['created', 'members_changed'])
            ->and($group->activities->firstWhere('action', 'members_changed')->changes)
            ->toBe(['members' => ['old' => [], 'new' => ['Anna']]])
            ->and($group->activities->first()->causer->is($this->admin))->toBeTrue();
    });

    it('validates the input', function (array $input, string $field) {
        Group::factory()->create(['name' => 'Support']);

        Livewire::test(Index::class)
            ->call('create')
            ->set($input)
            ->call('save')
            ->assertHasErrors($field);
    })->with([
        'name missing' => [['name' => ''], 'name'],
        'name taken' => [['name' => 'Support'], 'name'],
        'name too long' => [['name' => str_repeat('x', 101)], 'name'],
        'unknown member' => [['name' => 'Neu', 'members' => [999]], 'members.0'],
    ]);

    it('edits a group and its members', function () {
        $group = Group::factory()->create(['name' => 'Support']);
        [$anna, $jonas] = User::factory()->count(2)->create();
        $group->users()->attach($anna);

        Livewire::test(Index::class)
            ->call('edit', $group->id)
            ->assertSet('name', 'Support')
            ->assertSet('members', [$anna->id])
            ->set('name', '1st Level Support')
            ->set('members', [$jonas->id])
            ->call('save')
            ->assertHasNoErrors();

        $group->refresh();
        expect($group->name)->toBe('1st Level Support')
            ->and($group->users->pluck('id')->all())->toBe([$jonas->id])
            ->and($group->activities->pluck('action'))->toContain('updated', 'members_changed');
    });

    it('keeps its own name when editing', function () {
        $group = Group::factory()->create(['name' => 'Support']);

        Livewire::test(Index::class)
            ->call('edit', $group->id)
            ->call('save')
            ->assertHasNoErrors();
    });

    it('does not log members when they did not change', function () {
        $group = Group::factory()->create();
        $group->users()->attach(User::factory()->create());

        Livewire::test(Index::class)->call('edit', $group->id)->call('save');

        expect($group->activities()->where('action', 'members_changed')->exists())->toBeFalse();
    });

    it('deletes a group but keeps its members', function () {
        $group = Group::factory()->create();
        $member = User::factory()->create();
        $group->users()->attach($member);

        Livewire::test(Index::class)
            ->call('delete', $group->id)
            ->assertDispatched('toast', message: 'Gruppe gelöscht.');

        expect(Group::find($group->id))->toBeNull()
            ->and($member->fresh())->not->toBeNull()
            ->and($member->groups()->count())->toBe(0)
            ->and(Activity::where('subject_type', 'group')->where('action', 'deleted')->exists())->toBeTrue();
    });
});
