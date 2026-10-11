<?php

use App\Models\Activity;
use App\Models\User;
use App\Services\ActivityLogger;

it('logs the creation of a model with the acting user', function () {
    $admin = User::factory()->create();
    $this->actingAs($admin);

    $user = User::factory()->create(['name' => 'Jonas Weber']);

    $activity = $user->activities()->first();

    expect($activity->action)->toBe('created')
        ->and($activity->causer->is($admin))->toBeTrue()
        ->and($activity->subject->is($user))->toBeTrue()
        ->and($activity->changes['name'])->toBe(['old' => null, 'new' => 'Jonas Weber'])
        ->and($activity->created_at)->not->toBeNull();
});

it('logs only the changed attributes with old and new values', function () {
    $user = User::factory()->create(['name' => 'Alt', 'email' => 'alt@example.com']);

    $user->update(['name' => 'Neu']);

    $activity = $user->activities()->first();

    expect($activity->action)->toBe('updated')
        ->and($activity->changes)->toBe(['name' => ['old' => 'Alt', 'new' => 'Neu']]);
});

it('masks hidden attributes such as the password', function () {
    $user = User::factory()->create();

    $user->update(['password' => 'GanzGeheim2026']);

    $changes = $user->activities()->first()->changes;

    expect($changes['password'])->toBe(['old' => Activity::MASKED, 'new' => Activity::MASKED])
        ->and(json_encode($user->activities()->get()))->not->toContain('GanzGeheim2026');
});

it('ignores personal preferences, timestamps and 2FA secrets', function () {
    $user = User::factory()->create();
    $count = Activity::count();

    $user->update(['theme' => 'dark']);
    $user->forceFill(['two_factor_secret' => encrypt('secret'), 'remember_token' => 'abc'])->save();

    expect(Activity::count())->toBe($count);

    $created = $user->activities()->where('action', 'created')->first()->changes;
    expect($created)->not->toHaveKeys(['id', 'created_at', 'updated_at', 'theme', 'remember_token']);
});

it('logs deletions with the last values', function () {
    $user = User::factory()->create(['name' => 'Weg']);
    $id = $user->id;

    $user->delete();

    $activity = Activity::where('subject_type', 'user')->where('subject_id', $id)->latest('id')->first();

    expect($activity->action)->toBe('deleted')
        ->and($activity->changes['name'])->toBe(['old' => 'Weg', 'new' => null]);
});

it('writes custom entries for events that are not attribute changes', function () {
    $user = User::factory()->create();

    $user->logActivity('roles_changed', ['roles' => ['old' => [], 'new' => ['Admin']]]);

    expect($user->activities()->first())
        ->action->toBe('roles_changed')
        ->changes->toBe(['roles' => ['old' => [], 'new' => ['Admin']]]);
});

it('has no causer for system actions', function () {
    $user = User::factory()->create();

    expect($user->activities()->first()->causer)->toBeNull();
});

it('can be paused, e.g. for imports', function () {
    $count = Activity::count();

    ActivityLogger::withoutLogging(fn () => User::factory()->count(3)->create());

    expect(Activity::count())->toBe($count);

    User::factory()->create();
    expect(Activity::count())->toBe($count + 1);
});

it('stores short type names for subjects and causers', function () {
    $this->actingAs($admin = User::factory()->create());
    $user = User::factory()->create();

    expect($user->activities()->first())
        ->subject_type->toBe('user')
        ->causer_type->toBe('user');
});

it('stores cast values', function () {
    $user = User::factory()->create();

    $user->update(['is_active' => false]);

    expect($user->activities()->first()->changes)->toBe(['is_active' => ['old' => true, 'new' => false]]);
});

it('masks encrypted attributes', function () {
    $model = new class extends User
    {
        protected function casts(): array
        {
            return [...parent::casts(), 'name' => 'encrypted'];
        }
    };

    expect((fn () => $this->activityValue('name', encrypt('Geheim')))->call($model))->toBe(Activity::MASKED)
        ->and((fn () => $this->activityValue('email', 'a@b.de'))->call($model))->toBe('a@b.de');
});
