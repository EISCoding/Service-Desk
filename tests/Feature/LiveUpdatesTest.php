<?php

use App\Enums\MessageType;
use App\Enums\TicketStatus;
use App\Livewire\Intern\Dashboard;
use App\Livewire\Intern\Tickets\TicketList;
use App\Livewire\Intern\Tickets\TicketView;
use App\Livewire\Portal\Tickets\Index as PortalIndex;
use App\Livewire\Portal\Tickets\Show as PortalShow;
use App\Models\Contact;
use App\Models\Customer;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

beforeEach(function () {
    $this->staff = User::factory()->staff()->create();
    $this->customer = Customer::factory()->create();
    $this->contact = Contact::factory()->for($this->customer)->withPortalAccess()->create();
    $this->ticket = Ticket::factory()->create(['subject' => 'Drucker defekt', 'customer_id' => $this->customer->id, 'contact_id' => $this->contact->id, 'group_id' => null, 'assignee_id' => $this->staff->id]);
});

describe('staff', function () {
    beforeEach(fn () => $this->actingAs($this->staff));

    it('shows new customer replies in the open ticket without reloading', function () {
        $component = Livewire::test(TicketView::class, ['ticket' => $this->ticket]);
        $token = $component->get('changeToken');

        $component->call('checkForChanges')->assertSet('changeToken', $token);

        $this->travel(1)->minute();
        TicketMessage::factory()->for($this->ticket)->create(['author_type' => 'contact', 'author_id' => $this->contact->id, 'body' => '<p>Geht wieder!</p>']);

        $component->call('checkForChanges')
            ->assertSee('Geht wieder!')
            ->assertDispatched('ticket-updated');

        expect($component->get('changeToken'))->not->toBe($token)
            ->and($this->ticket->fresh()->isUnreadFor($this->staff))->toBeFalse();
    });

    it('shows status changes made by colleagues', function () {
        $component = Livewire::test(TicketView::class, ['ticket' => $this->ticket]);

        $this->ticket->update(['status' => TicketStatus::WaitingCustomer]);

        $component->call('checkForChanges')->assertSet('ticket.status', TicketStatus::WaitingCustomer);
    });

    it('sends deleted tickets back to the list (the page reloads when live updates fail)', function () {
        $this->ticket->delete();

        $this->get(route('intern.tickets.show', $this->ticket->id))
            ->assertRedirect(route('intern.tickets.index'))
            ->assertSessionHas('toast.message', 'Dieses Ticket gibt es nicht mehr.');
    });

    it('adds new tickets to the list and the overview', function () {
        $list = Livewire::test(TicketList::class)->assertSee('Drucker defekt')->assertDontSee('Server down');
        $dashboard = Livewire::test(Dashboard::class)->assertDontSee('Server down');

        Ticket::factory()->create(['subject' => 'Server down', 'assignee_id' => $this->staff->id]);

        $list->call('checkForChanges')->assertSee('Server down');
        $dashboard->call('checkForChanges')->assertSee('Server down');
    });
});

describe('portal', function () {
    beforeEach(fn () => $this->actingAs($this->contact, 'contact'));

    it('shows new team replies in the open ticket without reloading', function () {
        $component = Livewire::test(PortalShow::class, ['ticket' => $this->ticket]);
        $token = $component->get('changeToken');

        // Internal notes are not part of the portal view.
        TicketMessage::factory()->for($this->ticket)->create(['type' => MessageType::Internal, 'author_type' => 'user', 'author_id' => $this->staff->id, 'body' => '<p>Nur intern</p>']);
        $this->ticket->refresh();

        $this->travel(1)->minute();
        TicketMessage::factory()->for($this->ticket)->create(['author_type' => 'user', 'author_id' => $this->staff->id, 'body' => '<p>Bitte neu starten.</p>']);

        $component->call('checkForChanges')
            ->assertSee('Bitte neu starten.')
            ->assertDontSee('Nur intern');

        expect($component->get('changeToken'))->not->toBe($token)
            ->and($this->ticket->fresh()->isUnreadFor($this->contact))->toBeFalse();
    });

    it('updates the ticket list of the portal', function () {
        $component = Livewire::test(PortalIndex::class)->assertDontSee('Bitte Seriennummer senden');

        $this->travel(1)->minute();
        $this->ticket->update(['status' => TicketStatus::WaitingCustomer, 'subject' => 'Bitte Seriennummer senden']);

        $component->call('checkForChanges')->assertSeeInOrder(['Wartet auf Ihre Antwort', 'Bitte Seriennummer senden']);
    });

    it('sends missing tickets back to the start page', function () {
        $this->contact->forceFill(['password' => 'Kundenportal2026'])->save();
        $this->ticket->delete();

        $this->get(route('portal.tickets.show', $this->ticket->id))->assertRedirect(route('portal.home'));
    });
});

it('builds fingerprints that MariaDB and MySQL accept in strict mode', function () {
    // ORDER BY together with count()/max() fails there (SQLite allows it): "1140 Mixing of GROUP columns".
    $queries = [];
    DB::listen(function ($query) use (&$queries) {
        $queries[] = strtolower($query->sql);
    });

    $this->ticket->fingerprint();
    $this->ticket->fingerprint(withInternal: false);
    Ticket::fingerprintOf(Ticket::query()->latest('last_activity_at'));

    $aggregates = array_filter($queries, fn (string $sql) => str_contains($sql, 'max('));
    expect($aggregates)->toHaveCount(3)
        ->and(array_filter($aggregates, fn (string $sql) => str_contains($sql, 'order by')))->toBeEmpty();
});
