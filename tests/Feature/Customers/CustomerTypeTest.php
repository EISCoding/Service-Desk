<?php

use App\Enums\CustomerType;
use App\Enums\Salutation;
use App\Livewire\Intern\Customers\Contacts;
use App\Livewire\Intern\Customers\CustomerForm;
use App\Livewire\Intern\Customers\Index;
use App\Livewire\Portal\Tickets\Index as PortalIndex;
use App\Models\Contact;
use App\Models\Customer;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->staff = User::factory()->staff()->create();
    $this->actingAs($this->staff);
});

it('creates a private person who is their own main contact', function () {
    Livewire::test(CustomerForm::class)
        ->dispatch('create-customer')
        ->set('type', 'private')
        ->assertSee('Vorname')
        ->assertDontSee('Website')
        ->set('salutation', 'frau')
        ->set('first_name', 'Erika')
        ->set('last_name', 'Mustermann')
        ->set('email', 'Erika@Example.com')
        ->set('phone', '0711 123')
        ->set('city', 'Stuttgart')
        ->call('save')
        ->assertHasNoErrors();

    $customer = Customer::sole();
    $person = $customer->person();

    expect($customer)
        ->type->toBe(CustomerType::Private)
        ->company->toBe('Erika Mustermann')
        ->email->toBe('erika@example.com')
        ->and($person)
        ->salutation->toBe(Salutation::Ms)
        ->first_name->toBe('Erika')
        ->email->toBe('erika@example.com')
        ->phone->toBe('0711 123')
        ->is_primary->toBeTrue();
});

it('requires the last name of a private person and the name of a company', function () {
    Livewire::test(CustomerForm::class)
        ->dispatch('create-customer')
        ->set('type', 'private')
        ->call('save')
        ->assertHasErrors(['last_name' => 'required'])
        ->assertHasNoErrors('company')
        ->set('type', 'company')
        ->call('save')
        ->assertHasErrors(['company' => 'required']);
});

it('refuses an email address that belongs to another contact', function () {
    Contact::factory()->create(['email' => 'erika@example.com']);

    Livewire::test(CustomerForm::class)
        ->dispatch('create-customer')
        ->set('type', 'private')
        ->set('last_name', 'Mustermann')
        ->set('email', 'erika@example.com')
        ->call('save')
        ->assertHasErrors(['email' => 'unique']);
});

it('edits a private person and keeps the contact in sync', function () {
    $customer = Customer::factory()->private()->create();
    $person = Contact::factory()->for($customer)->create(['first_name' => 'Erika', 'last_name' => 'Muster', 'is_primary' => true]);

    Livewire::test(CustomerForm::class)
        ->dispatch('edit-customer', id: $customer->id)
        ->assertSet('type', 'private')
        ->assertSet('first_name', 'Erika')
        ->set('last_name', 'Mustermann')
        ->call('save')
        ->assertHasNoErrors();

    expect($customer->fresh()->company)->toBe('Erika Mustermann')
        ->and($person->fresh()->last_name)->toBe('Mustermann')
        ->and($customer->contacts()->count())->toBe(1);

    // Renaming the contact directly renames the customer too.
    $person->fresh()->update(['first_name' => 'Erika M.']);
    expect($customer->fresh()->company)->toBe('Erika M. Mustermann');
});

it('does not add further contacts to private persons', function () {
    $customer = Customer::factory()->private()->create();

    Livewire::test(Contacts::class, ['customer' => $customer])
        ->assertSee('Bei Privatpersonen ist die Person selbst der Ansprechpartner.')
        ->assertDontSee('wire:click="create"', false)
        ->call('create')
        ->assertForbidden();
});

it('filters the customer list by type', function () {
    Customer::factory()->create(['company' => 'Schmidt AG']);
    Customer::factory()->private()->create(['company' => 'Erika Mustermann']);

    Livewire::test(Index::class)
        ->assertSee(['Schmidt AG', 'Erika Mustermann', 'Privat'])
        ->set('type', 'private')
        ->assertSee('Erika Mustermann')
        ->assertDontSee('Schmidt AG')
        ->set('type', 'company')
        ->assertSee('Schmidt AG')
        ->assertDontSee('Erika Mustermann');
});

it('shows private persons a personal portal without company wording', function () {
    $customer = Customer::factory()->private()->create(['company' => 'Erika Mustermann']);
    $person = Contact::factory()->for($customer)->withPortalAccess()->create(['first_name' => 'Erika', 'last_name' => 'Mustermann', 'is_primary' => true]);

    Livewire::actingAs($person, 'contact')->test(PortalIndex::class)
        ->assertSee('Ihre Anfragen')
        ->assertDontSee('Anfragen von')
        ->assertDontSee('Ganze Firma');
});
