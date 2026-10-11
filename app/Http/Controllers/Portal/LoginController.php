<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Login and logout of contacts (guard "contact").
 */
class LoginController extends Controller
{
    private const MAX_ATTEMPTS = 5;

    public function create(): View
    {
        return view('portal.auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);
        $credentials['email'] = Str::lower(trim($credentials['email']));

        $key = 'portal-login:'.$credentials['email'].'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            throw ValidationException::withMessages([
                'email' => 'Zu viele Anmeldeversuche. Bitte versuchen Sie es in '.RateLimiter::availableIn($key).' Sekunden erneut.',
            ]);
        }

        $loggedIn = Auth::guard('contact')->attemptWhen(
            $credentials,
            fn (Contact $contact) => $contact->canUsePortal(),
            $request->boolean('remember'),
        );

        if (! $loggedIn) {
            RateLimiter::hit($key);

            throw ValidationException::withMessages(['email' => 'E-Mail-Adresse oder Passwort ist nicht korrekt.']);
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();

        return redirect()->intended(route('portal.home'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('contact')->logout();

        // Staff may be logged in in the same browser (shared session): keep their login.
        if (! Auth::guard('web')->check()) {
            $request->session()->invalidate();
        }
        $request->session()->regenerateToken();

        return redirect()->route('portal.login');
    }
}
