<?php

use App\Livewire\Intern\Customers\CustomerForm;
use App\Livewire\Intern\Customers\Show;
use App\Models\Activity;
use App\Models\Contact;
use App\Models\Customer;
use App\Models\CustomerNote;
use App\Models\User;
use App\Policies\CustomerPolicy;
use Livewire\Livewire;

beforeEach(function () {
    $this->staff = User::factory()->staff()->create();
    $this->actingAs($this->staff);
});

describe('create', function () {
    it('creates a customer with an automatic number and opens it', function () {
        Livewire::test(CustomerForm::class)
            ->dispatch('create-customer')
            ->assertSet('show', true)
            ->assertSet('nextNumber', 'K-10001')
            ->set('company', 'Müller GmbH')
            ->set('street', 'Hauptstraße 1')
            ->set('postal_code', '10115')
            ->set('city', 'Berlin')
            ->set('email', 'Info@Mueller.de')
            ->set('website', 'www.mueller.de')
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('intern.customers.show', Customer::first()));

        $customer = Customer::sole();
        expect($customer->number)->toBe('K-10001')
            ->and($customer->email)->toBe('info@mueller.de')
            ->and($customer->website)->toBe('https://www.mueller.de')
            ->and($customer->phone)->toBeNull()
            ->and($customer->country)->toBe('DE')
            ->and(session('toast.message'))->toBe('Kunde K-10001 angelegt.')
            ->and($customer->activities()->first()->causer->is($this->staff))->toBeTrue();
    });

    it('accepts a manual customer number', function () {
        Livewire::test(CustomerForm::class)
            ->dispatch('create-customer')
            ->set('company', 'Alt AG')
            ->set('number', 'K-500')
            ->call('save')
            ->assertHasNoErrors();

        expect(Customer::sole()->number)->toBe('K-500');
    });

    it('validates the input', function (array $input, string $field) {
        Customer::factory()->create(['number' => 'K-10001']);

        Livewire::test(CustomerForm::class)
            ->dispatch('create-customer')
            ->set(array_merge(['company' => 'Firma'], $input))
            ->call('save')
            ->assertHasErrors($field);
    })->with([
        'company missing' => [['company' => ''], 'company'],
        'number taken' => [['number' => 'K-10001'], 'number'],
        'invalid email' => [['email' => 'keine-mail'], 'email'],
        'unknown country' => [['country' => 'XX'], 'country'],
        'invalid website' => [['website' => 'http://'], 'website'],
        'postal code too long' => [['postal_code' => str_repeat('1', 11)], 'postal_code'],
    ]);
});

describe('edit', function () {
    it('edits a customer and keeps its own number valid', function () {
        $customer = Customer::factory()->create(['company' => 'Alt GmbH']);

        Livewire::test(CustomerForm::class)
            ->dispatch('edit-customer', id: $customer->id)
            ->assertSet('company', 'Alt GmbH')
            ->assertSet('number', 'K-10001')
            ->set('company', 'Neu GmbH')
            ->call('save')
            ->assertHasNoErrors()
            ->assertDispatched('customer-saved')
            ->assertDispatched('toast', message: 'Kunde gespeichert.');

        expect($customer->refresh()->company)->toBe('Neu GmbH')
            ->and($customer->number)->toBe('K-10001');
    });

    it('logs the changed fields', function () {
        $customer = Customer::factory()->create(['city' => 'Berlin']);

        Livewire::test(CustomerForm::class)
            ->dispatch('edit-customer', id: $customer->id)
            ->set('city', 'Hamburg')
            ->call('save');

        expect($customer->activities()->first()->changes)->toBe(['city' => ['old' => 'Berlin', 'new' => 'Hamburg']]);
    });
});

describe('archive and delete', function () {
    it('archives and restores a customer', function () {
        $customer = Customer::factory()->create();

        Livewire::test(Show::class, ['customer' => $customer])
            ->call('archive')
            ->assertSee('Archiviert')
            ->assertSee('Wiederherstellen');
        expect($customer->refresh()->isArchived())->toBeTrue();

        Livewire::test(Show::class, ['customer' => $customer])->call('restore');
        expect($customer->refresh()->isArchived())->toBeFalse()
            ->and(Activity::forHistoryOf($customer)->where('action', 'updated')->count())->toBe(2);
    });

    it('deletes a customer without tickets including contacts and notes', function () {
        $customer = Customer::factory()->create();
        Contact::factory()->for($customer)->create();
        CustomerNote::factory()->for($customer)->create();

        Livewire::test(Show::class, ['customer' => $customer])
            ->call('delete')
            ->assertRedirect(route('intern.customers.index'));

        expect(Customer::count())->toBe(0)
            ->and(Contact::count())->toBe(0)
            ->and(CustomerNote::count())->toBe(0)
            ->and(Activity::where('subject_type', 'customer')->where('action', 'deleted')->exists())->toBeTrue();
    });

    it('does not delete customers with tickets', function () {
        $customer = Mockery::mock(Customer::class)->makePartial();
        $customer->shouldReceive('hasTickets')->andReturnTrue();

        $policy = new CustomerPolicy;

        expect($policy->delete($this->staff, $customer))->toBeFalse()
            ->and($policy->archive($this->staff, $customer))->toBeTrue();
    });
});

describe('permissions', function () {
    it('denies everything without customers.manage', function () {
        $customer = Customer::factory()->create();
        $this->actingAs(User::factory()->create());

        Livewire::test(CustomerForm::class)->dispatch('create-customer')->assertForbidden();
        Livewire::test(CustomerForm::class)->dispatch('edit-customer', id: $customer->id)->assertForbidden();
    });
});
