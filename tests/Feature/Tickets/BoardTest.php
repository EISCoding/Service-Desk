<?php

use App\Enums\Permission;
use App\Enums\TicketStatus;
use App\Livewire\Intern\Tickets\Board;
use App\Models\Group;
use App\Models\Mailbox;
use App\Models\Ticket;
use App\Models\User;
use Database\Seeders\MailTemplateSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->me = User::factory()->staff()->create();
    $this->actingAs($this->me);
    $this->ticket = Ticket::factory()->create(['subject' => 'Drucker defekt', 'group_id' => null, 'assignee_id' => $this->me->id, 'requester_email' => null]);
});

it('shows open tickets in status columns and recently closed ones', function () {
    Ticket::factory()->status(TicketStatus::WaitingCustomer)->create(['subject' => 'Warte auf Rückmeldung', 'group_id' => null]);
    Ticket::factory()->status(TicketStatus::Closed)->create(['subject' => 'Gestern erledigt', 'group_id' => null, 'closed_at' => now()->subDay()]);
    Ticket::factory()->status(TicketStatus::Closed)->create(['subject' => 'Lange her', 'group_id' => null, 'closed_at' => now()->subMonth()]);
    Ticket::factory()->create(['subject' => 'Fremde Gruppe', 'group_id' => Group::factory()->create()->id]);

    $this->get(route('intern.tickets.board'))
        ->assertOk()
        ->assertSee('wire:sort:group-id="new"', false)
        ->assertSeeInOrder(['Neu', 'Drucker defekt', 'Warte auf Kunden', 'Warte auf Rückmeldung', 'Geschlossen', 'Gestern erledigt'])
        ->assertDontSee('Lange her')
        ->assertDontSee('Fremde Gruppe');
});

it('filters by view and search', function () {
    Ticket::factory()->create(['subject' => 'Nicht meins', 'group_id' => null, 'assignee_id' => null]);

    Livewire::test(Board::class)
        ->set('view', 'mine')
        ->assertSee('Drucker defekt')
        ->assertDontSee('Nicht meins')
        ->set('view', 'all')
        ->set('search', 'meins')
        ->assertSee('Nicht meins')
        ->assertDontSee('Drucker defekt');
});

it('changes the status when a card is dropped into another column', function () {
    Livewire::test(Board::class)
        ->call('moveTicket', $this->ticket->id, 0, 'in_progress')
        ->assertSet('movingId', null)
        ->assertDispatched('toast');

    expect($this->ticket->fresh()->status)->toBe(TicketStatus::InProgress);
});

it('asks for the follow-up date of waiting statuses', function () {
    $component = Livewire::test(Board::class)
        ->call('moveTicket', $this->ticket->id, 0, 'waiting_internal')
        ->assertSet('movingId', $this->ticket->id)
        ->assertSet('movingStatus', 'waiting_internal')
        ->assertSee('Wiedervorlage am');

    expect($this->ticket->fresh()->status)->toBe(TicketStatus::New);

    $component->set('followUpDate', today()->addDays(3)->toDateString())
        ->call('confirmMove')
        ->assertSet('movingId', null);

    expect($this->ticket->fresh())
        ->status->toBe(TicketStatus::WaitingInternal)
        ->follow_up_at->toDateString()->toBe(today()->addDays(3)->toDateString());
});

it('offers to inform the customer when a mail can be sent', function () {
    $this->seed(MailTemplateSeeder::class);
    $this->ticket->forceFill(['mailbox_id' => Mailbox::factory()->create()->id, 'requester_email' => 'kunde@example.com'])->save();

    Livewire::test(Board::class)
        ->call('moveTicket', $this->ticket->id, 0, 'closed')
        ->assertSet('movingStatus', 'closed')
        ->assertSet('notifyCustomer', true)
        ->assertSee('Kunden per E-Mail informieren');
});

it('keeps VOS for the appointment dialog of the ticket', function () {
    Livewire::test(Board::class)
        ->call('moveTicket', $this->ticket->id, 0, 'on_site')
        ->assertDispatched('toast', type: 'info');

    expect($this->ticket->fresh()->status)->toBe(TicketStatus::New);
});

it('checks the permissions of the dropped ticket', function () {
    $hidden = Ticket::factory()->create(['group_id' => Group::factory()->create()->id]);

    Livewire::test(Board::class)->call('moveTicket', $hidden->id, 0, 'in_progress')->assertNotFound();

    $reader = User::factory()->create()->givePermissionTo(Permission::TicketsViewOwn->value);
    $this->ticket->forceFill(['assignee_id' => $reader->id])->save();

    Livewire::actingAs($reader)->test(Board::class)->call('moveTicket', $this->ticket->id, 0, 'in_progress')->assertForbidden();
});

it('remembers the chosen layout for the ticket navigation', function () {
    $this->get(route('intern.tickets.board'))->assertOk();
    $this->get(route('intern.tickets.index'))->assertRedirect(route('intern.tickets.board'));
    $this->get(route('intern.tickets.index', ['ansicht' => 'mine']))->assertOk();
    $this->get(route('intern.tickets.index', ['darstellung' => 'liste']))->assertOk();
    $this->get(route('intern.tickets.index'))->assertOk()->assertSee('aria-label="Darstellung"', false);
});
