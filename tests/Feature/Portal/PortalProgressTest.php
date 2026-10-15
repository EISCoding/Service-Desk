<?php

use App\Enums\TicketStatus;
use App\Models\Contact;
use App\Models\Customer;
use App\Models\Ticket;

beforeEach(function () {
    $customer = Customer::factory()->create();
    $this->contact = Contact::factory()->for($customer)->withPortalAccess()->create(['password' => 'Kundenportal2026']);
    $this->ticket = Ticket::factory()->create(['customer_id' => $customer->id, 'contact_id' => $this->contact->id]);
});

it('shows the progress step of the ticket', function (TicketStatus $status, string $current, string $hint) {
    $this->ticket->update(['status' => $status]);

    $this->actingAs($this->contact, 'contact')
        ->get(route('portal.tickets.show', $this->ticket))
        ->assertOk()
        ->assertSee('aria-label="Fortschritt"', false)
        ->assertSeeInOrder(['aria-current="step"', $current], false)
        ->assertSee($hint);
})->with([
    'new' => [TicketStatus::New, 'Eingegangen', 'Wir haben Ihre Anfrage erhalten'],
    'in progress' => [TicketStatus::InProgress, 'In Bearbeitung', 'Wir arbeiten an Ihrer Anfrage.'],
    'waiting internally' => [TicketStatus::WaitingInternal, 'In Bearbeitung', 'Wir arbeiten an Ihrer Anfrage.'],
    'waiting for the customer' => [TicketStatus::WaitingCustomer, 'In Bearbeitung', 'Wir benötigen eine Rückmeldung von Ihnen'],
    'closed' => [TicketStatus::Closed, 'Erledigt', 'Ihre Anfrage ist erledigt.'],
]);

it('maps every status to one of three steps', function () {
    expect(collect(TicketStatus::cases())->map->portalStep()->unique()->sort()->values()->all())->toBe([1, 2, 3]);
});
