<?php

use App\Enums\Salutation;
use App\Livewire\Intern\Customers\Contacts;
use App\Models\Contact;
use App\Models\Customer;
use App\Models\User;
use App\Notifications\Portal\ContactInvitation;
use Illuminate\Support\Facades\Notification;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

beforeEach(function () {
    Notification::fake();
    $this->staff = User::factory()->staff()->create();
    $this->customer = Customer::factory()->create();
    $this->actingAs($this->staff);
});

function contactsTab(): Testable
{
    return Livewire::test(Contacts::class, ['customer' => test()->customer]);
}

it('creates a contact; the first one becomes primary', function () {
    contactsTab()
        ->call('create')
        ->assertSet('is_primary', true)
        ->set('salutation', 'frau')
        ->set('first_name', 'Anna')
        ->set('last_name', 'Becker')
        ->set('email', 'Anna.Becker@Example.com')
        ->set('position', 'IT-Leitung')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('showForm', false)
        ->assertDispatched('contacts-changed')
        ->assertSee('Anna Becker');

    $contact = $this->customer->contacts()->sole();
    expect($contact->salutation)->toBe(Salutation::Ms)
        ->and($contact->email)->toBe('anna.becker@example.com')
        ->and($contact->is_primary)->toBeTrue()
        ->and($contact->portal_access)->toBeFalse()
        ->and($contact->phone)->toBeNull();

    contactsTab()->call('create')->assertSet('is_primary', false);
});

it('allows contacts without email when they have no portal access', function () {
    contactsTab()->call('create')->set('last_name', 'Hausmeister')->call('save')->assertHasNoErrors();

    expect($this->customer->contacts()->sole()->email)->toBeNull();
});

it('requires an email address for portal access', function () {
    contactsTab()
        ->call('create')
        ->set('last_name', 'Becker')
        ->set('portal_access', true)
        ->call('save')
        ->assertHasErrors(['email' => 'required']);
});

it('validates the input', function (array $input, string $field) {
    Contact::factory()->create(['email' => 'vergeben@example.com']);

    contactsTab()->call('create')->set(array_merge(['last_name' => 'Becker'], $input))->call('save')->assertHasErrors($field);
})->with([
    'last name missing' => [['last_name' => ''], 'last_name'],
    'email taken' => [['email' => 'vergeben@example.com'], 'email'],
    'email taken (case)' => [['email' => 'Vergeben@Example.com'], 'email'],
    'invalid email' => [['email' => 'nope'], 'email'],
    'unknown salutation' => [['salutation' => 'dr'], 'salutation'],
]);

it('edits a contact', function () {
    $contact = Contact::factory()->for($this->customer)->create(['last_name' => 'Alt', 'email' => 'alt@example.com']);

    contactsTab()
        ->call('edit', $contact->id)
        ->assertSet('last_name', 'Alt')
        ->assertSet('email', 'alt@example.com')
        ->set('last_name', 'Neu')
        ->call('save')
        ->assertHasNoErrors();

    expect($contact->refresh()->last_name)->toBe('Neu');
});

it('deletes a contact', function () {
    $contact = Contact::factory()->for($this->customer)->create();

    contactsTab()->call('delete', $contact->id)->assertDispatched('toast', message: 'Ansprechpartner gelöscht.');

    expect(Contact::find($contact->id))->toBeNull();
});

it('cannot address contacts of other customers', function () {
    $foreign = Contact::factory()->create();

    contactsTab()->call('edit', $foreign->id)->assertNotFound();
    contactsTab()->call('delete', $foreign->id)->assertNotFound();
    contactsTab()->call('invite', $foreign->id)->assertNotFound();
});

describe('portal invitation', function () {
    it('invites right away when portal access is granted', function () {
        contactsTab()
            ->call('create')
            ->set('last_name', 'Becker')
            ->set('email', 'anna@example.com')
            ->set('portal_access', true)
            ->call('save')
            ->assertHasNoErrors()
            ->assertDispatched('toast', message: 'Ansprechpartner angelegt und eingeladen.');

        $contact = Contact::sole();
        expect($contact->portal_access)->toBeTrue()
            ->and($contact->invited_at)->not->toBeNull()
            ->and($contact->portalStatus())->toBe('invited')
            ->and($contact->activities()->first()->action)->toBe('portal_invited');

        Notification::assertSentTo($contact, ContactInvitation::class, function (ContactInvitation $notification) use ($contact) {
            $mail = $notification->toMail($contact);

            return str_contains($notification->url($contact), '/portal/einladung/')
                && $mail->subject === 'Ihr Zugang zum Kundenportal von ServiceDesk'
                && $mail->greeting === $contact->formalGreeting().',';
        });
    });

    it('can grant portal access without sending the invitation yet', function () {
        contactsTab()
            ->call('create')
            ->set('last_name', 'Becker')
            ->set('email', 'anna@example.com')
            ->set('portal_access', true)
            ->set('sendInvitation', false)
            ->call('save');

        expect(Contact::sole()->portalStatus())->toBe('not_invited');
        Notification::assertNothingSent();
    });

    it('sends and re-sends invitations from the list', function () {
        $contact = Contact::factory()->for($this->customer)->withPortalAccess()->create();

        contactsTab()->call('invite', $contact->id)->assertDispatched('toast', message: "Einladung an {$contact->email} verschickt.");
        contactsTab()->call('invite', $contact->id);

        Notification::assertSentToTimes($contact, ContactInvitation::class, 2);
    });

    it('does not invite contacts without portal access', function () {
        $contact = Contact::factory()->for($this->customer)->create();

        contactsTab()->call('invite', $contact->id)->assertDispatched('toast', type: 'error');

        Notification::assertNothingSent();
    });

    it('uses a formal greeting', function () {
        expect(Contact::factory()->make(['salutation' => Salutation::Mr, 'last_name' => 'Weber'])->formalGreeting())->toBe('Sehr geehrter Herr Weber')
            ->and(Contact::factory()->make(['salutation' => Salutation::Ms, 'last_name' => 'Becker'])->formalGreeting())->toBe('Sehr geehrte Frau Becker')
            ->and(Contact::factory()->make(['salutation' => null, 'first_name' => 'Kim', 'last_name' => 'Lee'])->formalGreeting())->toBe('Guten Tag Kim Lee');
    });
});

describe('permissions', function () {
    it('needs contacts.portal to grant portal access or invite', function () {
        $user = User::factory()->create();
        $user->givePermissionTo('customers.manage');
        $this->actingAs($user);
        $contact = Contact::factory()->for($this->customer)->create(['email' => 'anna@example.com']);

        contactsTab()
            ->assertDontSee('Ins Portal einladen')
            ->call('edit', $contact->id)
            ->assertDontSee('Portal-Zugang')
            ->set('portal_access', true)
            ->call('save')
            ->assertHasNoErrors();

        expect($contact->refresh()->portal_access)->toBeFalse();

        $contact->update(['portal_access' => true]);
        contactsTab()->call('invite', $contact->id)->assertForbidden();
    });

    it('needs customers.manage to manage contacts', function () {
        $user = User::factory()->create();
        $user->givePermissionTo('contacts.portal');
        $this->actingAs($user);

        contactsTab()->call('create')->assertForbidden();
    });
});
