<?php

use App\Enums\MessageType;
use App\Enums\TicketPriority;
use App\Enums\TicketSource;
use App\Enums\TicketStatus;
use App\Livewire\Intern\Customers\Tickets as CustomerTickets;
use App\Livewire\Intern\Tickets\CreateTicket;
use App\Models\Contact;
use App\Models\Customer;
use App\Models\Group;
use App\Models\Ticket;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->group = Group::factory()->create();
    $this->staff = User::factory()->staff()->create();
    $this->staff->groups()->attach($this->group);
    $this->customer = Customer::factory()->create(['company' => 'Müller GmbH']);
    $this->actingAs($this->staff);
});

it('creates a ticket for a customer and contact, assigned to me', function () {
    $contact = Contact::factory()->for($this->customer)->create();

    Livewire::test(CreateTicket::class)
        ->dispatch('create-ticket')
        ->assertSet('show', true)
        ->assertSet('groupId', (string) $this->group->id)
        ->set('customerSearch', 'müller')
        ->assertSee('Müller GmbH')
        ->call('selectCustomer', $this->customer->id)
        ->assertSet('contactId', (string) $contact->id) // only contact is preselected
        ->set('subject', 'Drucker defekt')
        ->set('priority', 'high')
        ->set('body', '<div>Der Drucker <strong>druckt nicht</strong>.<script>x</script></div>')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('intern.tickets.show', Ticket::first()));

    $ticket = Ticket::sole();
    expect($ticket)
        ->subject->toBe('Drucker defekt')
        ->priority->toBe(TicketPriority::High)
        ->status->toBe(TicketStatus::InProgress)
        ->source->toBe(TicketSource::Internal)
        ->customer_id->toBe($this->customer->id)
        ->contact_id->toBe($contact->id)
        ->group_id->toBe($this->group->id)
        ->assignee_id->toBe($this->staff->id);

    $message = $ticket->messages->sole();
    expect($message->type)->toBe(MessageType::Public)
        ->and($message->author->is($this->staff))->toBeTrue()
        ->and($message->body)->toBe('<div>Der Drucker <strong>druckt nicht</strong>.</div>')
        ->and(session('toast.message'))->toBe("Ticket#{$ticket->number} angelegt.");
});

it('creates an unassigned new ticket', function () {
    Livewire::test(CreateTicket::class)
        ->dispatch('create-ticket', customerId: $this->customer->id)
        ->assertSet('customerId', $this->customer->id)
        ->set('subject', 'Frage')
        ->set('body', '<div>Text</div>')
        ->set('assignToMe', false)
        ->call('save')
        ->assertHasNoErrors();

    expect(Ticket::sole())->status->toBe(TicketStatus::New)->assignee_id->toBeNull();
});

it('preselects the primary contact', function () {
    Contact::factory()->for($this->customer)->create();
    $primary = Contact::factory()->for($this->customer)->primary()->create();

    Livewire::test(CreateTicket::class)
        ->dispatch('create-ticket', customerId: $this->customer->id)
        ->assertSet('contactId', (string) $primary->id);
});

it('validates the input', function (array $input, string $field) {
    $foreignContact = Contact::factory()->create();
    $input = array_map(fn ($value) => $value === ':foreign' ? (string) $foreignContact->id : $value, $input);

    Livewire::test(CreateTicket::class)
        ->dispatch('create-ticket', customerId: $this->customer->id)
        ->set(array_merge(['subject' => 'Betreff', 'body' => '<div>Text</div>'], $input))
        ->call('save')
        ->assertHasErrors($field);
})->with([
    'customer missing' => [['customerId' => null], 'customerId'],
    'subject missing' => [['subject' => ''], 'subject'],
    'body missing' => [['body' => ''], 'body'],
    'body only markup' => [['body' => '<div><br></div>'], 'body'],
    'contact of another customer' => [['contactId' => ':foreign'], 'contactId'],
    'unknown priority' => [['priority' => 'mega'], 'priority'],
]);

it('needs tickets.edit', function () {
    $viewer = User::factory()->create();
    $viewer->givePermissionTo('tickets.view.all');
    $this->actingAs($viewer);

    Livewire::test(CreateTicket::class)->dispatch('create-ticket')->assertForbidden();
});

it('lists the visible tickets of a customer in the customer tab', function () {
    Ticket::factory()->create(['customer_id' => $this->customer->id, 'subject' => 'Sichtbar', 'group_id' => $this->group->id]);
    Ticket::factory()->create(['customer_id' => $this->customer->id, 'subject' => 'Fremd', 'group_id' => Group::factory()->create()->id]);
    Ticket::factory()->create(['subject' => 'Anderer Kunde']);

    Livewire::test(CustomerTickets::class, ['customer' => $this->customer])
        ->assertSee('Sichtbar')
        ->assertDontSee('Fremd')
        ->assertDontSee('Anderer Kunde')
        ->assertSee('Neues Ticket');
});
