<?php

use App\Livewire\Intern\GlobalSearch;
use App\Models\Contact;
use App\Models\Customer;
use App\Models\Group;
use App\Models\Ticket;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->customer = Customer::factory()->create(['company' => 'Druckerei Becker', 'city' => 'Hamburg']);
    $this->contact = Contact::factory()->for($this->customer)->create(['first_name' => 'Anna', 'last_name' => 'Druckmann', 'email' => 'anna@becker.test']);
    $this->ticket = Ticket::factory()->create(['subject' => 'Drucker im Lager', 'customer_id' => $this->customer->id, 'group_id' => null]);
});

it('sits in the topbar with ⌘K', function () {
    $this->actingAs(User::factory()->staff()->create())->get('/intern/tickets')
        ->assertSee('id="global-search"', false)
        ->assertSee('⌘K');
});

it('finds tickets, customers and contacts', function () {
    $this->actingAs(User::factory()->staff()->create());

    Livewire::test(GlobalSearch::class)
        ->set('query', 'druck')
        ->assertSeeInOrder(['Tickets', 'Drucker im Lager', 'Kunden', 'Druckerei Becker', 'Ansprechpartner', 'Anna Druckmann'])
        ->assertSee(route('intern.tickets.show', $this->ticket))
        ->assertSee(route('intern.customers.show', ['customer' => $this->customer, 'tab' => 'ansprechpartner']));

    Livewire::test(GlobalSearch::class)->set('query', $this->ticket->reference())->assertSee('Drucker im Lager');
    Livewire::test(GlobalSearch::class)->set('query', 'anna@becker')->assertSee('Anna Druckmann')->assertDontSee('Drucker im Lager');
    Livewire::test(GlobalSearch::class)->set('query', 'x')->assertDontSee('Keine Treffer');
    Livewire::test(GlobalSearch::class)->set('query', 'nichts dergleichen')->assertSee('Keine Treffer');
});

it('only finds what the user may see', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('tickets.view.group');
    $user->groups()->attach($group = Group::factory()->create());
    Ticket::factory()->create(['subject' => 'Drucker der Buchhaltung', 'group_id' => Group::factory()->create()->id]);
    Ticket::factory()->create(['subject' => 'Drucker zusammengeführt', 'group_id' => $group->id, 'merged_into_id' => $this->ticket->id]);
    $this->actingAs($user);

    Livewire::test(GlobalSearch::class)
        ->set('query', 'drucker')
        ->assertSee('Drucker im Lager')
        ->assertDontSee('Drucker der Buchhaltung')
        ->assertDontSee('Drucker zusammengeführt')
        ->assertDontSee(route('intern.customers.show', $this->customer)) // the company still shows in the ticket line
        ->assertDontSee('Anna Druckmann');
});
