<?php

namespace App\Providers;

use App\Actions\Fortify\ResetUserPassword;
use App\Http\Responses\FailedPasswordResetLinkRequestResponse;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Actions\RedirectIfTwoFactorAuthenticatable;
use Laravel\Fortify\Contracts\FailedPasswordResetLinkRequestResponse as FailedPasswordResetLinkRequestResponseContract;
use Laravel\Fortify\Fortify;

/**
 * Staff authentication for /intern (guard "web"). The customer portal gets its
 * own guard and login in Phase 5.
 */
class FortifyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(FailedPasswordResetLinkRequestResponseContract::class, FailedPasswordResetLinkRequestResponse::class);
    }

    public function boot(): void
    {
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::redirectUserForTwoFactorAuthenticationUsing(RedirectIfTwoFactorAuthenticatable::class);

        // Deactivated users cannot log in (checked after the password, so the
        // message does not reveal anything to someone without the password).
        Fortify::authenticateUsing(function (Request $request) {
            $user = User::where('email', Str::lower($request->input('email')))->first();

            if (! $user || ! Hash::check($request->input('password'), $user->password)) {
                return null;
            }

            if (! $user->is_active) {
                throw ValidationException::withMessages(['email' => __('auth.inactive')]);
            }

            return $user;
        });

        Fortify::loginView(fn () => view('auth.login'));
        Fortify::requestPasswordResetLinkView(fn () => view('auth.forgot-password'));
        Fortify::resetPasswordView(fn (Request $request) => view('auth.reset-password', ['request' => $request]));
        Fortify::twoFactorChallengeView(fn () => view('auth.two-factor-challenge'));
        Fortify::confirmPasswordView(fn () => view('auth.confirm-password'));

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });

        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });
    }
}
