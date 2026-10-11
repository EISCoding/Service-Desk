<?php

use App\Enums\TicketStatus;
use App\Livewire\Intern\Tickets\History;
use App\Livewire\Intern\Tickets\TicketView;
use App\Models\Group;
use App\Models\Ticket;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->staff = User::factory()->admin()->create(['name' => 'Bert Support']);
    $this->actingAs($this->staff);
    $this->ticket = Ticket::factory()->create(['group_id' => null]);
});

it('shows the activity log as a tab of the ticket', function () {
    Livewire::test(TicketView::class, ['ticket' => $this->ticket])
        ->assertSee(['Nachrichten', 'Aktivität'])
        ->assertDontSeeLivewire(History::class)
        ->set('tab', 'activity')
        ->assertSeeLivewire(History::class);
});

it('describes ticket changes with readable values', function () {
    $group = Group::factory()->create(['name' => 'Technik']);
    $assignee = User::factory()->create(['name' => 'Tina Technik']);
    $view = Livewire::test(TicketView::class, ['ticket' => $this->ticket]);
    $view->call('setGroup', (string) $group->id);
    $view->call('setAssignee', (string) $assignee->id);
    $view->call('setStatus', TicketStatus::Closed->value)->call('confirmStatus');

    Livewire::test(History::class, ['ticket' => $this->ticket->refresh()])
        ->assertSeeInOrder(['Bert Support', 'hat den Status geändert', 'Status', 'Neu', 'Geschlossen'])
        ->assertSee(['hat den Bearbeiter geändert', 'Tina Technik', 'Gruppe', 'Technik', 'hat das Ticket angelegt'])
        ->assertDontSee('updated');
});

it('is only shown to staff who may see the ticket', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(History::class, ['ticket' => $this->ticket])->assertForbidden();
});
