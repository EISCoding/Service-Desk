<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Services\ActivityLogger;
use Illuminate\Contracts\Auth\PasswordBroker;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;

/**
 * "Passwort festlegen" page behind the link of the portal invitation.
 */
class InvitationController extends Controller
{
    public function show(Request $request, string $token): View
    {
        $contact = $this->contact((string) $request->query('email'));

        return view('portal.invitation', [
            'token' => $token,
            'email' => (string) $request->query('email'),
            'valid' => $contact !== null && $this->broker()->tokenExists($contact, $token),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'confirmed', PasswordRule::defaults()],
        ]);

        if (! $this->contact($request->input('email'))) {
            return $this->invalid($request);
        }

        $status = $this->broker()->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (Contact $contact, string $password) {
                ActivityLogger::withoutLogging(fn () => $contact->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                ])->save());

                app(ActivityLogger::class)->log($contact, 'portal_password_set', causer: $contact, parent: $contact->customer);
            },
        );

        return $status === Password::PASSWORD_RESET
            ? redirect()->route('portal.login')->with('status', 'Ihr Zugang ist eingerichtet. Bitte melden Sie sich mit Ihrer E-Mail-Adresse und dem neuen Passwort an.')
            : $this->invalid($request);
    }

    private function invalid(Request $request): RedirectResponse
    {
        return back()
            ->withInput($request->only('email'))
            ->withErrors(['email' => 'Dieser Einladungslink ist ungültig oder abgelaufen. Bitte fordern Sie bei uns eine neue Einladung an.']);
    }

    /**
     * Only contacts with portal access can accept an invitation.
     */
    private function contact(string $email): ?Contact
    {
        return Contact::where('email', Str::lower($email))->where('portal_access', true)->first();
    }

    private function broker(): PasswordBroker
    {
        return Password::broker('contact_invitations');
    }
}
