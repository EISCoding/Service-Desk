<?php

namespace App\Providers;

use App\Enums\Permission;
use App\Http\Middleware\EnsureUserIsActive;
use App\Models\Customer;
use App\Models\Group;
use App\Models\User;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Short, stable type names in polymorphic columns (activity log, roles).
        Relation::enforceMorphMap([
            'user' => User::class,
            'group' => Group::class,
            'customer' => Customer::class,
        ]);

        // Catch N+1 queries and unfillable attributes during development and tests.
        Model::preventLazyLoading(! $this->app->isProduction());
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());

        // Password policy for staff (start passwords, password changes and resets).
        Password::defaults(fn () => Password::min(10)->letters()->numbers());

        // Access to /intern/admin: any of the admin.* permissions.
        Gate::define('access-admin', fn (User $user) => $user->hasAnyPermission(
            array_map(fn (Permission $permission) => $permission->value, Permission::admin())
        ));

        // Re-apply these route middlewares to Livewire requests of the protected pages.
        Livewire::addPersistentMiddleware([RequirePassword::class, EnsureUserIsActive::class]);
    }
}
