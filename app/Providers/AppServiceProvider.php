<?php

namespace App\Providers;

use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Database\Eloquent\Model;
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
        // Catch N+1 queries and unfillable attributes during development and tests.
        Model::preventLazyLoading(! $this->app->isProduction());
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());

        // Password policy for staff (start passwords, password changes and resets).
        Password::defaults(fn () => Password::min(10)->letters()->numbers());

        // Re-apply the password confirmation to Livewire requests of protected pages.
        Livewire::addPersistentMiddleware([RequirePassword::class]);
    }
}
