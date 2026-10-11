<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Services\ActivityLogger;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Contracts\Auth\PasswordBroker;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;

/**
 * "Passwort vergessen" for contacts with portal access (broker "contacts", 60 minutes).
 */
class PasswordResetController extends Controller
{
    public function create(): View
    {
        return view('portal.auth.forgot-password');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        $this->broker()->sendResetLink(['email' => Str::lower(trim($request->input('email'))), 'portal_access' => true]);

        // Same answer whether the address exists or not.
        return back()->with('status', 'Falls zu dieser Adresse ein Portal-Zugang besteht, haben wir Ihnen einen Link zum Zurücksetzen geschickt.');
    }

    public function edit(Request $request, string $token): View
    {
        return view('portal.auth.reset-password', ['token' => $token, 'email' => (string) $request->query('email')]);
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'confirmed', PasswordRule::defaults()],
        ]);

        $status = $this->broker()->reset(
            [...$request->only('password', 'password_confirmation', 'token'), 'email' => Str::lower($request->input('email')), 'portal_access' => true],
            function (Contact $contact, string $password) {
                ActivityLogger::withoutLogging(fn () => $contact->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                ])->save());

                app(ActivityLogger::class)->log($contact, 'portal_password_reset', causer: $contact, parent: $contact->customer);
                event(new PasswordReset($contact));
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            return back()->withInput($request->only('email'))
                ->withErrors(['email' => 'Dieser Link ist ungültig oder abgelaufen. Bitte fordern Sie einen neuen an.']);
        }

        return redirect()->route('portal.login')->with('status', 'Ihr Passwort wurde geändert. Sie können sich jetzt anmelden.');
    }

    private function broker(): PasswordBroker
    {
        return Password::broker('contacts');
    }
}
