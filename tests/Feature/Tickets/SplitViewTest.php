<?php

use App\Livewire\Intern\Tickets\TicketList;
use App\Livewire\Intern\Tickets\TicketView;
use App\Models\Group;
use App\Models\Ticket;
use App\Models\User;

beforeEach(function () {
    $this->staff = User::factory()->staff()->create();
    $this->ticket = Ticket::factory()->create(['subject' => 'Drucker defekt', 'group_id' => null]);
    $this->actingAs($this->staff);
});

it('shows list and ticket side by side under /intern/tickets/{id}', function () {
    $this->get("/intern/tickets/{$this->ticket->id}")
        ->assertOk()
        ->assertSee("<title>Ticket#{$this->ticket->number} Drucker defekt · ServiceDesk</title>", false)
        ->assertSeeLivewire(TicketList::class)
        ->assertSeeLivewire(TicketView::class)
        ->assertSee('lg:grid-cols-[minmax(320px,400px)_1fr]', false)
        ->assertSee('aria-current="page"', false);
});

it('shows only the list on phones when no ticket is open', function () {
    $html = $this->get('/intern/tickets')->getContent();

    expect($html)->toMatch('/class="[^"]*hidden[^"]*"\s+aria-label="Ticket"/')
        ->and($html)->not->toMatch('/class="[^"]*\bhidden\b[^"]*"\s+aria-label="Ticketliste"/');
});

it('shows only the ticket with a back link on phones when a ticket is open', function () {
    $html = $this->get("/intern/tickets/{$this->ticket->id}")->getContent();

    expect($html)->toMatch('/class="[^"]*\bhidden\b[^"]*"\s+aria-label="Ticketliste"/')
        ->and($html)->toContain('lg:hidden');
});

it('keeps the scroll position of the list', function () {
    $this->get("/intern/tickets/{$this->ticket->id}")->assertSee('ticket-list-scroll');
});

it('refuses tickets the user may not see', function () {
    $hidden = Ticket::factory()->create(['group_id' => Group::factory()->create()->id]);

    $this->get("/intern/tickets/{$hidden->id}")->assertForbidden();
    $this->get('/intern/tickets/999999')->assertRedirect(route('intern.tickets.index'));
});
