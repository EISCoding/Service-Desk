<?php

use App\Livewire\Intern\Customers\Index;
use App\Models\Contact;
use App\Models\Customer;
use App\Models\User;
use App\Support\Navigation;
use Livewire\Livewire;

it('is available to staff with customers.manage under /intern/kunden', function () {
    $staff = User::factory()->staff()->create();

    $this->actingAs($staff)->get('/intern/kunden')
        ->assertOk()
        ->assertSee('<title>Kunden · ServiceDesk</title>', false)
        ->assertSeeLivewire(Index::class);

    expect(collect(Navigation::main($staff))->pluck('label'))->toContain('Kunden');
});

it('is hidden and forbidden without customers.manage', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/intern/kunden')->assertForbidden();
    expect(collect(Navigation::main($user))->pluck('label'))->not->toContain('Kunden');
});

describe('list', function () {
    beforeEach(fn () => $this->actingAs(User::factory()->staff()->create()));

    it('shows active customers with number, place and contact count', function () {
        $customer = Customer::factory()->create(['company' => 'Müller GmbH', 'postal_code' => '10115', 'city' => 'Berlin']);
        Contact::factory()->for($customer)->count(2)->create();
        Customer::factory()->archived()->create(['company' => 'Alt AG']);

        Livewire::test(Index::class)
            ->assertSeeInOrder(['K-10001', 'Müller GmbH', '10115 Berlin', '2'])
            ->assertDontSee('Alt AG');
    });

    it('filters archived and all customers', function () {
        Customer::factory()->create(['company' => 'Aktiv GmbH']);
        Customer::factory()->archived()->create(['company' => 'Alt AG']);

        Livewire::test(Index::class)
            ->set('status', 'archived')
            ->assertSee('Alt AG')
            ->assertDontSee('Aktiv GmbH')
            ->set('status', 'all')
            ->assertSee('Alt AG')
            ->assertSee('Aktiv GmbH');
    });

    it('searches company, number, city and contacts', function (string $term) {
        $match = Customer::factory()->create(['company' => 'Müller GmbH', 'number' => 'K-20000', 'city' => 'Kassel']);
        Contact::factory()->for($match)->create(['last_name' => 'Schmidtke', 'email' => 'schmidtke@mueller.de']);
        Customer::factory()->create(['company' => 'Andere AG', 'city' => 'Bonn']);

        Livewire::test(Index::class)
            ->set('search', $term)
            ->assertSee('Müller GmbH')
            ->assertDontSee('Andere AG');
    })->with(['müller', 'K-20000', 'Kassel', 'Schmidtke', 'schmidtke@mueller']);

    it('sorts by company, number and city', function () {
        Customer::factory()->create(['company' => 'Zeta', 'city' => 'Aachen']);
        Customer::factory()->create(['company' => 'Alpha', 'city' => 'Zwickau']);

        Livewire::test(Index::class)
            ->assertSeeInOrder(['Alpha', 'Zeta'])
            ->call('sort', 'company')
            ->assertSeeInOrder(['Zeta', 'Alpha'])
            ->call('sort', 'city')
            ->assertSeeInOrder(['Aachen', 'Zwickau'])
            ->call('sort', 'number')
            ->assertSeeInOrder(['K-10001', 'K-10002']);
    });

    it('ignores unknown sort columns', function () {
        Livewire::test(Index::class)
            ->call('sort', 'password')
            ->assertSet('sortBy', 'company');
    });

    it('paginates after 25 customers', function () {
        Customer::factory()->count(30)->create();

        Livewire::test(Index::class)
            ->tap(fn ($component) => expect($component->instance()->customers->count())->toBe(25))
            ->call('nextPage')
            ->tap(fn ($component) => expect($component->instance()->customers->count())->toBe(5));
    });

    it('shows an empty state', function () {
        Livewire::test(Index::class)->assertSee('Noch keine Kunden');
    });
});
