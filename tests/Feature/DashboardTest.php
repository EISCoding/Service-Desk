<?php

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Livewire\Intern\Dashboard;
use App\Livewire\Intern\Tickets\TicketList;
use App\Models\Appointment;
use App\Models\Contact;
use App\Models\Group;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->group = Group::factory()->create();
    $this->me = User::factory()->staff()->create(['name' => 'Anna Becker']);
    $this->me->groups()->attach($this->group);
    $this->actingAs($this->me);
});

it('greets the user and shows the key figures', function () {
    Ticket::factory()->create(['subject' => 'Mein Ticket', 'assignee_id' => $this->me->id, 'priority' => TicketPriority::Urgent]);
    Ticket::factory()->create(['subject' => 'Offen in der Gruppe', 'group_id' => $this->group->id, 'assignee_id' => null]);
    Ticket::factory()->status(TicketStatus::Closed)->create(['assignee_id' => $this->me->id]);
    Ticket::factory()->create(['subject' => 'Fremde Gruppe', 'group_id' => Group::factory()->create()->id]);

    $this->travelTo(now()->setTime(8, 30));

    $component = Livewire::test(Dashboard::class)
        ->assertSee('Guten Morgen, Anna')
        ->assertSee('Mein Ticket')
        ->assertSee('Dringend')
        ->assertDontSee('Fremde Gruppe');

    expect($component->instance()->counts)->toBe(['mine' => 1, 'unread' => 0, 'due' => 0, 'unassigned' => 1]);
});

it('lists new customer replies and due follow-ups', function () {
    $contact = Contact::factory()->create();
    $replied = Ticket::factory()->create(['subject' => 'Kunde hat geantwortet', 'group_id' => $this->group->id]);
    TicketMessage::factory()->for($replied)->create(['author_type' => 'contact', 'author_id' => $contact->id]);
    Ticket::factory()->create(['subject' => 'Wiedervorlage heute', 'assignee_id' => $this->me->id, 'follow_up_at' => today(), 'follow_up_due' => true]);

    Livewire::test(Dashboard::class)
        ->assertSeeInOrder(['Neue Kundenantworten', 'Kunde hat geantwortet', 'Fällige Wiedervorlagen', 'Wiedervorlage heute'])
        ->assertSee('Neue Kundenantwort:');
});

it('shows the next appointments of the technician', function () {
    $ticket = Ticket::factory()->create(['subject' => 'Server tauschen', 'assignee_id' => $this->me->id]);
    Appointment::factory()->for($ticket)->create(['technician_id' => $this->me->id, 'starts_at' => now()->addDay()->setTime(9, 0), 'ends_at' => now()->addDay()->setTime(10, 0)]);

    Livewire::test(Dashboard::class)->assertSee('Deine nächsten Termine')->assertSee('09:00–10:00 Uhr');
});

it('opens the ticket list with the view of a key figure', function () {
    Ticket::factory()->create(['subject' => 'Mir zugewiesen', 'assignee_id' => $this->me->id, 'follow_up_at' => today(), 'follow_up_due' => true]);
    Ticket::factory()->create(['subject' => 'Gruppenticket offen', 'group_id' => $this->group->id]);

    $this->get(route('intern.tickets.index', ['ansicht' => 'unassigned']))->assertSee('Gruppenticket offen')->assertDontSee('Mir zugewiesen');

    Livewire::test(TicketList::class, ['preset' => 'due'])
        ->assertSet('view', 'all')
        ->assertSet('dueOnly', true)
        ->assertSee('Mir zugewiesen')
        ->assertDontSee('Gruppenticket offen');

    Livewire::test(TicketList::class, ['preset' => 'unread'])->assertSet('unreadOnly', true)->assertSee('Keine Tickets');
});

it('sends users without tickets to their first area', function () {
    $this->actingAs(User::factory()->create())->get('/intern')->assertRedirect(route('intern.settings.general'));
});

it('is the first navigation item', function () {
    $this->get('/intern/tickets')->assertSeeInOrder(['Übersicht', 'Tickets', 'Termine']);
});
