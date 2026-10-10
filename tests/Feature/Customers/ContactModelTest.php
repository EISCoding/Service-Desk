<?php

use App\Enums\Salutation;
use App\Models\Activity;
use App\Models\Contact;
use App\Models\Customer;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Auth;

it('belongs to a customer and builds the full name', function () {
    $contact = Contact::factory()->create(['salutation' => Salutation::Ms, 'first_name' => 'Anna', 'last_name' => 'Becker']);

    expect($contact->customer)->toBeInstanceOf(Customer::class)
        ->and($contact->fullName())->toBe('Anna Becker')
        ->and($contact->salutation->label())->toBe('Frau')
        ->and($contact->is_primary)->toBeFalse()
        ->and($contact->portal_access)->toBeFalse();
});

it('allows contacts without email, but email addresses are unique', function () {
    Contact::factory()->count(2)->create(['email' => null]);
    Contact::factory()->create(['email' => 'anna@example.com']);

    Contact::factory()->create(['email' => 'anna@example.com']);
})->throws(UniqueConstraintViolationException::class);

it('lists primary contacts first', function () {
    $customer = Customer::factory()->create();
    Contact::factory()->for($customer)->create(['last_name' => 'Albrecht']);
    Contact::factory()->for($customer)->primary()->create(['last_name' => 'Zander']);
    Contact::factory()->for($customer)->primary()->create(['last_name' => 'Meyer']);

    expect($customer->contacts->pluck('last_name')->all())->toBe(['Meyer', 'Zander', 'Albrecht']);
});

it('allows several primary contacts per customer', function () {
    $customer = Customer::factory()->create();
    Contact::factory()->for($customer)->primary()->count(2)->create();

    expect($customer->contacts()->where('is_primary', true)->count())->toBe(2);
});

it('is deleted together with its customer', function () {
    $contact = Contact::factory()->create();

    $contact->customer->delete();

    expect(Contact::find($contact->id))->toBeNull();
});

it('derives the portal status', function () {
    $contact = Contact::factory()->make();
    expect($contact->portalStatus())->toBeNull();

    $contact->portal_access = true;
    expect($contact->portalStatus())->toBe('not_invited');

    $contact->invited_at = now();
    expect($contact->portalStatus())->toBe('invited');

    $contact->password = 'Passwort2026xy';
    expect($contact->portalStatus())->toBe('active');
});

it('can authenticate against the contact guard', function () {
    Contact::factory()->withPortalAccess()->create(['email' => 'anna@example.com'])
        ->forceFill(['password' => 'Passwort2026xy'])->save();

    expect(Auth::guard('contact')->attempt(['email' => 'anna@example.com', 'password' => 'Passwort2026xy']))->toBeTrue()
        ->and(Auth::guard('web')->check())->toBeFalse();
});

it('logs changes into the history of its customer', function () {
    $contact = Contact::factory()->create(['last_name' => 'Alt']);
    $contact->update(['last_name' => 'Neu']);
    $contact->forceFill(['password' => 'Passwort2026xy'])->save();

    $history = Activity::forHistoryOf($contact->customer)->get();

    expect($history->where('subject_type', 'contact')->pluck('action')->all())->toEqualCanonicalizing(['created', 'updated', 'updated'])
        ->and($history->firstWhere(fn ($a) => isset($a->changes['password']))->changes['password']['new'])->toBe(Activity::MASKED)
        ->and($history->where('subject_type', 'customer')->pluck('action')->all())->toBe(['created']);
});
