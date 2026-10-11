<?php

use App\Enums\Salutation;
use App\Enums\TicketStatus;
use App\Livewire\Portal\Tickets\Index;
use App\Models\Contact;
use App\Models\Customer;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->customer = Customer::factory()->create(['company' => 'Schmidt AG']);
    $this->contact = Contact::factory()->for($this->customer)->withPortalAccess()->create(['salutation' => Salutation::Ms, 'last_name' => 'Becker']);
    $this->actingAs($this->contact, 'contact');
});

it('greets the contact and summarizes the open tickets', function () {
    $this->travelTo(now()->setTime(14, 0));
    $ticket = Ticket::factory()->create(['customer_id' => $this->customer->id, 'contact_id' => $this->contact->id]);
    Ticket::factory()->create(['customer_id' => $this->customer->id, 'contact_id' => $this->contact->id]);
    TicketMessage::factory()->for($ticket)->create(['author_type' => 'user', 'author_id' => User::factory()->create()->id]);

    Livewire::test(Index::class)
        ->assertSee('Schmidt AG')
        ->assertSee('Guten Tag, Frau Becker')
        ->assertSee('2 offene Anfragen, 1 mit neuer Antwort.');
});

it('puts tickets waiting for the customer on top', function () {
    Ticket::factory()->status(TicketStatus::WaitingCustomer)->create(['subject' => 'Bitte Seriennummer senden', 'customer_id' => $this->customer->id, 'contact_id' => $this->contact->id]);
    Ticket::factory()->create(['subject' => 'Drucker defekt', 'customer_id' => $this->customer->id, 'contact_id' => $this->contact->id]);
    Ticket::factory()->status(TicketStatus::WaitingCustomer)->create(['subject' => 'Fremdes Ticket']);

    Livewire::test(Index::class)
        ->assertSeeInOrder(['Wartet auf Ihre Antwort', 'Bitte Seriennummer senden', 'Jetzt antworten', 'Ihre Anfragen', 'Drucker defekt'])
        ->assertDontSee('Fremdes Ticket');
});

it('hides the waiting section when nothing waits for the customer', function () {
    Livewire::test(Index::class)
        ->assertDontSee('Jetzt antworten')
        ->assertSee('Sie haben gerade keine offenen Anfragen.');
});
