<?php

use App\Models\Contact;
use App\Models\User;
use App\Notifications\Portal\ResetPassword;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->contact = Contact::factory()->withPortalAccess()->create(['email' => 'anna@example.com', 'password' => 'Kundenportal2026']);
});

function portalLogin(string $email = 'anna@example.com', string $password = 'Kundenportal2026')
{
    return test()->post('/portal/login', ['email' => $email, 'password' => $password]);
}

describe('login', function () {
    it('shows the login page and sends guests there', function () {
        $this->get('/portal/login')->assertOk()->assertSee('Kundenportal')->assertSee('Passwort vergessen?');
        $this->get('/portal')->assertRedirect('/portal/login');
    });

    it('logs contacts in with the contact guard only', function () {
        portalLogin('Anna@Example.com')->assertRedirect('/portal');

        expect(Auth::guard('contact')->user()->is($this->contact))->toBeTrue()
            ->and(Auth::guard('web')->check())->toBeFalse();
        $this->get('/portal')->assertOk()->assertSee($this->contact->fullName());
        $this->get('/portal/login')->assertRedirect('/portal');
        $this->get('/intern/tickets')->assertRedirect('/intern/login');
    });

    it('rejects wrong passwords, contacts without portal access and archived customers', function () {
        portalLogin(password: 'falsch')->assertSessionHasErrors(['email' => 'E-Mail-Adresse oder Passwort ist nicht korrekt.']);

        $this->contact->update(['portal_access' => false]);
        portalLogin()->assertSessionHasErrors('email');

        $this->contact->update(['portal_access' => true]);
        $this->contact->customer->update(['archived_at' => now()]);
        portalLogin()->assertSessionHasErrors('email');

        expect(Auth::guard('contact')->check())->toBeFalse();
    });

    it('does not let staff log into the portal', function () {
        User::factory()->create(['email' => 'staff@example.com', 'password' => 'Kundenportal2026']);

        portalLogin('staff@example.com')->assertSessionHasErrors('email');
    });

    it('throttles after five failed attempts', function () {
        foreach (range(1, 5) as $i) {
            portalLogin(password: 'falsch');
        }

        portalLogin()->assertSessionHasErrors('email');
        expect(session('errors')->first('email'))->toContain('Zu viele Anmeldeversuche');
    });

    it('logs out a contact whose access was revoked', function () {
        portalLogin();
        // The guard keeps its user instance between test requests.
        Auth::guard('contact')->user()->update(['portal_access' => false]);

        $this->get('/portal')->assertRedirect('/portal/login');
        expect(Auth::guard('contact')->check())->toBeFalse();
    });

    it('logs out without ending a staff session in the same browser', function () {
        $staff = User::factory()->create();
        $this->actingAs($staff);
        portalLogin();

        $this->post('/portal/logout')->assertRedirect('/portal/login');

        expect(Auth::guard('contact')->check())->toBeFalse()
            ->and(Auth::guard('web')->id())->toBe($staff->id);
    });
});

describe('password reset', function () {
    beforeEach(fn () => Notification::fake());

    it('sends a reset link only to contacts with portal access', function () {
        $other = Contact::factory()->create(['email' => 'ohne@example.com']);

        $this->post('/portal/passwort-vergessen', ['email' => 'anna@example.com'])->assertSessionHas('status');
        $this->post('/portal/passwort-vergessen', ['email' => 'ohne@example.com'])->assertSessionHas('status');
        $this->post('/portal/passwort-vergessen', ['email' => 'unbekannt@example.com'])->assertSessionHas('status');

        Notification::assertSentTo($this->contact, ResetPassword::class, function (ResetPassword $notification) {
            $mail = $notification->toMail($this->contact);

            return str_contains($notification->url($this->contact), '/portal/passwort-zuruecksetzen/')
                && $mail->subject === 'Passwort für das Kundenportal zurücksetzen';
        });
        Notification::assertNotSentTo($other, ResetPassword::class);
    });

    it('resets the password with a valid token', function () {
        $this->post('/portal/passwort-vergessen', ['email' => 'anna@example.com']);
        $token = null;
        Notification::assertSentTo($this->contact, ResetPassword::class, function (ResetPassword $n) use (&$token) {
            $token = $n->token;

            return true;
        });

        $this->get("/portal/passwort-zuruecksetzen/{$token}?email=anna@example.com")->assertOk()->assertSee('Neues Passwort');

        $this->post('/portal/passwort-zuruecksetzen', [
            'token' => $token, 'email' => 'anna@example.com', 'password' => 'NeuesPasswort2026', 'password_confirmation' => 'NeuesPasswort2026',
        ])->assertRedirect('/portal/login')->assertSessionHas('status');

        expect(Hash::check('NeuesPasswort2026', $this->contact->refresh()->password))->toBeTrue();

        $this->post('/portal/passwort-zuruecksetzen', [
            'token' => 'falsch', 'email' => 'anna@example.com', 'password' => 'NochEinPasswort2026', 'password_confirmation' => 'NochEinPasswort2026',
        ])->assertSessionHasErrors('email');
    });
});
