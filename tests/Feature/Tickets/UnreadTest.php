<?php

use App\Enums\MessageType;
use App\Livewire\Intern\Tickets\TicketList;
use App\Livewire\Intern\Tickets\TicketView;
use App\Livewire\Portal\Tickets\Index as PortalIndex;
use App\Livewire\Portal\Tickets\Show as PortalShow;
use App\Models\Contact;
use App\Models\Customer;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->staff = User::factory()->staff()->create();
    $this->customer = Customer::factory()->create();
    $this->contact = Contact::factory()->for($this->customer)->withPortalAccess()->create();
    $this->ticket = Ticket::factory()->create(['subject' => 'Drucker defekt', 'customer_id' => $this->customer->id, 'contact_id' => $this->contact->id, 'group_id' => null]);
});

function customerWrites(Ticket $ticket, Contact $contact): void
{
    TicketMessage::factory()->for($ticket)->create(['author_type' => 'contact', 'author_id' => $contact->id]);
}

function teamWrites(Ticket $ticket, User $user, array $attributes = []): void
{
    TicketMessage::factory()->for($ticket)->create(['author_type' => 'user', 'author_id' => $user->id, ...$attributes]);
}

it('marks a customer reply as unread for staff until they open the ticket', function () {
    customerWrites($this->ticket, $this->contact);

    expect($this->ticket->fresh()->isUnreadFor($this->staff))->toBeTrue()
        ->and(Ticket::unreadBy($this->staff)->pluck('id')->all())->toBe([$this->ticket->id]);

    $this->actingAs($this->staff);
    Livewire::test(TicketList::class)->assertSee('Neue Kundenantwort:');

    $this->travel(1)->minute();
    Livewire::test(TicketView::class, ['ticket' => $this->ticket]);

    expect($this->ticket->fresh()->isUnreadFor($this->staff))->toBeFalse()
        ->and(Ticket::unreadBy($this->staff)->count())->toBe(0);
    Livewire::test(TicketList::class)->assertDontSee('Neue Kundenantwort:');

    // Each staff member has their own state.
    expect($this->ticket->fresh()->isUnreadFor(User::factory()->staff()->create()))->toBeTrue();

    // A new reply after the visit is unread again.
    $this->travel(1)->minute();
    customerWrites($this->ticket, $this->contact);
    expect($this->ticket->fresh()->isUnreadFor($this->staff))->toBeTrue();
});

it('marks a team reply as unread in the portal until the contact opens the ticket', function () {
    teamWrites($this->ticket, $this->staff);

    expect($this->ticket->fresh()->isUnreadFor($this->contact))->toBeTrue()
        ->and($this->ticket->fresh()->isUnreadFor($this->staff))->toBeFalse();

    $this->actingAs($this->contact, 'contact');
    Livewire::test(PortalIndex::class)->assertSee('Neue Antwort:');

    $this->travel(1)->minute();
    Livewire::test(PortalShow::class, ['ticket' => $this->ticket]);

    expect($this->ticket->fresh()->isUnreadFor($this->contact))->toBeFalse();
    Livewire::test(PortalIndex::class)->assertDontSee('Neue Antwort:');
});

it('ignores internal notes and automatic messages', function () {
    teamWrites($this->ticket, $this->staff, ['type' => MessageType::Internal]);
    teamWrites($this->ticket, $this->staff, ['type' => MessageType::MailOut, 'is_automatic' => true]);
    TicketMessage::factory()->for($this->ticket)->create(['type' => MessageType::MailIn, 'author_type' => null, 'author_id' => null, 'is_automatic' => true]);

    $ticket = $this->ticket->fresh();

    expect($ticket->isUnreadFor($this->contact))->toBeFalse()
        ->and($ticket->isUnreadFor($this->staff))->toBeFalse();
});

it('treats incoming mails without a known contact as customer replies', function () {
    TicketMessage::factory()->for($this->ticket)->create(['type' => MessageType::MailIn, 'author_type' => null, 'author_id' => null]);

    expect($this->ticket->fresh()->isUnreadFor($this->staff))->toBeTrue();
});
