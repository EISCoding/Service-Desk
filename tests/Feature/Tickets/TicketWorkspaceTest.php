<?php

use App\Enums\MessageType;
use App\Enums\TicketStatus;
use App\Livewire\Intern\Tickets\Composer;
use App\Livewire\Intern\Tickets\TicketList;
use App\Livewire\Intern\Tickets\TicketView;
use App\Models\Contact;
use App\Models\Customer;
use App\Models\CustomerNote;
use App\Models\Group;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->staff = User::factory()->staff()->create(['name' => 'Anna Becker']);
    $this->customer = Customer::factory()->create(['company' => 'Schmidt AG']);
    $this->contact = Contact::factory()->for($this->customer)->create(['first_name' => 'Kim', 'last_name' => 'Kunde']);
    $this->ticket = Ticket::factory()->create(['subject' => 'Drucker defekt', 'customer_id' => $this->customer->id, 'contact_id' => $this->contact->id, 'group_id' => null, 'requester_email' => null]);
    $this->actingAs($this->staff);
});

describe('customer sidebar', function () {
    it('shows notes and other tickets of the customer', function () {
        CustomerNote::factory()->for($this->customer)->create(['body' => '<p>Normale Notiz</p>', 'created_at' => now()->subDay()]);
        CustomerNote::factory()->for($this->customer)->pinned()->create(['body' => '<p>Nur vormittags anrufen</p>', 'created_at' => now()->subWeek()]);
        Ticket::factory()->create(['subject' => 'Altes Druckerproblem', 'customer_id' => $this->customer->id, 'group_id' => null]);
        Ticket::factory()->create(['subject' => 'Anderer Kunde', 'group_id' => null]);
        Ticket::factory()->create(['subject' => 'Fremde Gruppe', 'customer_id' => $this->customer->id, 'group_id' => Group::factory()->create()->id]);

        Livewire::test(TicketView::class, ['ticket' => $this->ticket])
            ->assertSeeInOrder(['Kundennotizen', 'Nur vormittags anrufen', 'Normale Notiz', 'Weitere Tickets', 'Altes Druckerproblem'])
            ->assertDontSee('Anderer Kunde')
            ->assertDontSee('Fremde Gruppe');
    });

    it('says when the customer has no other tickets', function () {
        Livewire::test(TicketView::class, ['ticket' => $this->ticket])
            ->assertSee('Keine weiteren Tickets.')
            ->assertDontSee('Kundennotizen');
    });
});

describe('quick actions', function () {
    it('closes and reopens the ticket with one click', function () {
        $component = Livewire::test(TicketView::class, ['ticket' => $this->ticket])
            ->assertSee('Erledigt')
            ->call('setStatus', 'closed');

        expect($this->ticket->fresh()->status)->toBe(TicketStatus::Closed);

        $component->assertSee('Wieder öffnen')->call('setStatus', 'in_progress');

        expect($this->ticket->fresh()->status)->toBe(TicketStatus::InProgress);
    });

    it('offers to send a reply and close the ticket in one step', function () {
        Livewire::test(Composer::class, ['ticket' => $this->ticket])
            ->assertSee('Senden & schließen')
            ->set('body', '<p>Erledigt, viele Grüße</p>')
            ->set('statusAfter', 'closed')
            ->call('send');

        expect($this->ticket->fresh()->status)->toBe(TicketStatus::Closed);
    });

    it('offers a full screen editor and the focus mode', function () {
        Livewire::test(Composer::class, ['ticket' => $this->ticket])->assertSee('Vollbild');
        $this->get(route('intern.tickets.show', $this->ticket))->assertSee('toggle-ticket-focus')->assertSee("\$persist(false).as('ticket-focus')", false);
    });
});

describe('list preview', function () {
    it('keeps a preview of the latest message', function () {
        TicketMessage::factory()->for($this->ticket)->create(['author_type' => 'contact', 'author_id' => $this->contact->id, 'body' => '<p>Der Drucker   zeigt <b>Fehler 42</b>.</p>']);
        expect($this->ticket->fresh()->preview)->toBe('Kim Kunde: Der Drucker zeigt Fehler 42.');

        TicketMessage::factory()->for($this->ticket)->internal()->create(['author_type' => 'user', 'author_id' => $this->staff->id, 'body' => '<p>Toner bestellt</p>']);
        expect($this->ticket->fresh()->preview)->toBe('Notiz · Anna Becker: Toner bestellt');

        TicketMessage::factory()->for($this->ticket)->create(['type' => MessageType::MailOut, 'is_automatic' => true, 'author_type' => null, 'author_id' => null, 'body' => '<p>Eingangsbestätigung</p>']);
        expect($this->ticket->fresh()->preview)->toBe('Notiz · Anna Becker: Toner bestellt');

        Livewire::test(TicketList::class)->assertSee('Notiz · Anna Becker: Toner bestellt');
    });

    it('leaves the quoted history of mails out of the preview', function () {
        TicketMessage::factory()->for($this->ticket)->create([
            'type' => MessageType::MailIn, 'author_type' => 'contact', 'author_id' => $this->contact->id,
            'body' => '<p>Danke, klappt!</p><blockquote><p>Bitte neu starten.</p></blockquote>',
        ]);

        expect($this->ticket->fresh()->preview)->toBe('Kim Kunde: Danke, klappt!');
    });
});
