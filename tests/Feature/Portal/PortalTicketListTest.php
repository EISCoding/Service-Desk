<?php

use App\Enums\TicketStatus;
use App\Livewire\Portal\Tickets\Index;
use App\Models\Contact;
use App\Models\Customer;
use App\Models\Ticket;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->customer = Customer::factory()->create(['company' => 'Müller GmbH']);
    $this->anna = Contact::factory()->for($this->customer)->withPortalAccess()->create(['password' => 'Kundenportal2026']);
    $this->bernd = Contact::factory()->for($this->customer)->create();

    $this->own = Ticket::factory()->create(['subject' => 'Drucker defekt', 'customer_id' => $this->customer->id, 'contact_id' => $this->anna->id]);
    $this->colleague = Ticket::factory()->create(['subject' => 'VPN des Kollegen', 'customer_id' => $this->customer->id, 'contact_id' => $this->bernd->id]);
    $this->withoutContact = Ticket::factory()->create(['subject' => 'Firmenticket', 'customer_id' => $this->customer->id, 'contact_id' => null]);
    $this->foreign = Ticket::factory()->create(['subject' => 'Fremde Firma']);
});

it('shows a contact only their own tickets', function () {
    $this->actingAs($this->anna, 'contact')
        ->get('/portal')
        ->assertOk()
        ->assertSee('<title>Tickets · ServiceDesk</title>', false)
        ->assertSee('Drucker defekt')
        ->assertSee($this->own->reference())
        ->assertDontSee('VPN des Kollegen')
        ->assertDontSee('Firmenticket')
        ->assertDontSee('Fremde Firma');
});

it('shows main contacts all tickets of their company', function () {
    $this->anna->update(['is_primary' => true]);
    $this->actingAs($this->anna, 'contact');

    Livewire::test(Index::class)
        ->assertSee(['Drucker defekt', 'VPN des Kollegen', 'Firmenticket', $this->bernd->fullName()])
        ->assertDontSee('Fremde Firma')
        ->set('scope', 'mine')
        ->assertSee('Drucker defekt')
        ->assertDontSee('VPN des Kollegen');
});

it('filters by state and search and hides merged tickets', function () {
    Ticket::factory()->status(TicketStatus::Closed)->create(['subject' => 'Altes Problem', 'customer_id' => $this->customer->id, 'contact_id' => $this->anna->id]);
    Ticket::factory()->status(TicketStatus::Closed)->create(['subject' => 'Zusammengeführt', 'customer_id' => $this->customer->id, 'contact_id' => $this->anna->id, 'merged_into_id' => $this->own->id]);
    $this->actingAs($this->anna, 'contact');

    Livewire::test(Index::class)
        ->assertSee('Drucker defekt')->assertDontSee('Altes Problem')
        ->set('state', 'closed')->assertSee('Altes Problem')->assertDontSee('Drucker defekt')
        ->set('state', 'all')->assertSee(['Altes Problem', 'Drucker defekt'])->assertDontSee('Zusammengeführt')
        ->set('search', $this->own->reference())->assertSee('Drucker defekt')->assertDontSee('Altes Problem');
});

it('uses customer wording for the status', function () {
    $this->own->update(['status' => TicketStatus::WaitingInternal]);
    $this->actingAs($this->anna, 'contact');

    Livewire::test(Index::class)->assertSee('In Bearbeitung')->assertDontSee('Warte auf Intern');
});

it('is not reachable for staff without a portal login', function () {
    $this->actingAs(User::factory()->admin()->create())->get('/portal')->assertRedirect('/portal/login');
});
