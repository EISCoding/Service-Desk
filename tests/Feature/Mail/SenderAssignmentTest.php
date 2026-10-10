<?php

use App\Jobs\FetchMailbox;
use App\Livewire\Intern\Tickets\TicketList;
use App\Mail\Inbound\MailboxConnector;
use App\Models\Contact;
use App\Models\Customer;
use App\Models\Mailbox;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Support\FakeMailboxConnector;

beforeEach(function () {
    $this->travelTo('2026-10-15 12:00');
    Storage::fake('local');
    $this->connector = new FakeMailboxConnector;
    app()->instance(MailboxConnector::class, $this->connector);
    $this->mailbox = Mailbox::factory()->create(['email' => 'support@musterfirma.test']);
    $this->customer = Customer::factory()->create(['company' => 'Kunde GmbH']);
    $this->contact = Contact::factory()->for($this->customer)->create(['email' => 'anna.becker@kunde.test', 'first_name' => 'Anna', 'last_name' => 'Becker']);
});

it('assigns mails from known contacts to their customer', function () {
    $this->connector->add('new-from-contact'); // From: Anna.Becker@kunde.test (case differs)

    FetchMailbox::dispatchSync($this->mailbox);

    $ticket = Ticket::sole();
    expect($ticket->customer_id)->toBe($this->customer->id)
        ->and($ticket->contact_id)->toBe($this->contact->id)
        ->and($ticket->messages->sole()->author->is($this->contact))->toBeTrue()
        ->and($ticket->messages->sole()->authorName())->toBe('Anna Becker');
});

it('leaves mails from unknown senders without customer', function () {
    $this->connector->reply('Frage zum Angebot', from: 'Max Neu <max@neukunde.test>');

    FetchMailbox::dispatchSync($this->mailbox);

    $ticket = Ticket::sole();
    expect($ticket->customer_id)->toBeNull()
        ->and($ticket->contact_id)->toBeNull()
        ->and($ticket->requester_email)->toBe('max@neukunde.test')
        ->and($ticket->requester_name)->toBe('Max Neu')
        ->and($ticket->messages->sole()->author)->toBeNull()
        ->and($ticket->messages->sole()->authorName())->toBe('Max Neu');

    $this->actingAs(User::factory()->admin()->create());
    expect(Livewire::test(TicketList::class)->call('resetFilters')->set('withoutCustomer', true)->instance()->tickets->pluck('id')->all())
        ->toBe([$ticket->id]);
});

it('keeps replies of unknown senders on existing tickets', function () {
    $ticket = Ticket::factory()->create(['customer_id' => $this->customer->id]);

    $this->connector->reply("AW: [#{$ticket->number}]", from: 'Kollege <kollege@kunde.test>');
    FetchMailbox::dispatchSync($this->mailbox);

    $message = $ticket->messages->sole();
    expect($message->author)->toBeNull()
        ->and($message->from_email)->toBe('kollege@kunde.test')
        ->and($ticket->refresh()->customer_id)->toBe($this->customer->id);
});

it('fills in the customer when a known contact writes to a ticket without customer', function () {
    $ticket = Ticket::factory()->withoutCustomer()->create();

    $this->connector->reply("AW: [#{$ticket->number}]");
    FetchMailbox::dispatchSync($this->mailbox);

    expect($ticket->refresh())->customer_id->toBe($this->customer->id)->contact_id->toBe($this->contact->id);
});
