<?php

/*
 * Visibility (tickets.view.own / .group / .all) and the activity log of tickets.
 */

use App\Livewire\Intern\Appointments\Index as AppointmentList;
use App\Livewire\Intern\Tickets\Composer;
use App\Livewire\Intern\Tickets\TicketList;
use App\Livewire\Intern\Tickets\TicketView;
use App\Models\Activity;
use App\Models\Appointment;
use App\Models\Group;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use App\Services\AttachmentStorage;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

beforeEach(function () {
    $this->myGroup = Group::factory()->create();
    $this->otherGroup = Group::factory()->create();
    $this->me = User::factory()->create();
    $this->me->groups()->attach($this->myGroup);
    $this->colleague = User::factory()->create();

    $this->tickets = [
        'assigned to me' => Ticket::factory()->create(['assignee_id' => $this->me->id, 'group_id' => $this->otherGroup->id]),
        'my group' => Ticket::factory()->create(['group_id' => $this->myGroup->id, 'assignee_id' => $this->colleague->id]),
        'pool without group' => Ticket::factory()->create(['group_id' => null]),
        'foreign group' => Ticket::factory()->create(['group_id' => $this->otherGroup->id, 'assignee_id' => $this->colleague->id]),
    ];
});

it('applies the visibility rules consistently in policy and query', function (array $permissions, array $visible) {
    $this->me->givePermissionTo($permissions);
    $user = User::find($this->me->id); // fresh instance (memoized groups/permissions)

    foreach ($this->tickets as $name => $ticket) {
        expect($user->can('view', $ticket))->toBe(in_array($name, $visible, true), "policy: {$name}");
    }

    $ids = Ticket::visibleTo($user)->pluck('id')->all();
    $expected = collect($this->tickets)->only($visible)->pluck('id')->all();
    expect($ids)->toEqualCanonicalizing($expected);
})->with([
    'no permission' => [[], []],
    'own only' => [['tickets.view.own'], ['assigned to me']],
    'group only' => [['tickets.view.group'], ['my group', 'pool without group']],
    'own + group (Mitarbeiter)' => [['tickets.view.own', 'tickets.view.group'], ['assigned to me', 'my group', 'pool without group']],
    'all' => [['tickets.view.all'], ['assigned to me', 'my group', 'pool without group', 'foreign group']],
]);

it('requires extra permissions on top of visibility for actions', function () {
    $this->me->givePermissionTo('tickets.view.all');
    $ticket = $this->tickets['my group'];
    $user = fn () => User::find($this->me->id);

    expect($user()->can('update', $ticket))->toBeFalse()
        ->and($user()->can('addNote', $ticket))->toBeFalse()
        ->and($user()->can('delete', $ticket))->toBeFalse()
        ->and($user()->can('merge', $ticket))->toBeFalse()
        ->and($user()->can('create', Ticket::class))->toBeFalse();

    $this->me->givePermissionTo(['tickets.edit', 'notes.manage', 'tickets.delete', 'tickets.merge']);

    expect($user()->can('update', $ticket))->toBeTrue()
        ->and($user()->can('addNote', $ticket))->toBeTrue()
        ->and($user()->can('delete', $ticket))->toBeTrue()
        ->and($user()->can('merge', $ticket))->toBeTrue()
        ->and($user()->can('create', Ticket::class))->toBeTrue();
});

it('never grants actions on invisible tickets, even with every action permission', function () {
    $this->me->givePermissionTo(['tickets.view.own', 'tickets.edit', 'notes.manage', 'tickets.delete', 'tickets.merge']);
    $user = User::find($this->me->id);
    $hidden = $this->tickets['foreign group'];

    foreach (['view', 'update', 'reply', 'addNote', 'delete', 'merge'] as $ability) {
        expect($user->can($ability, $hidden))->toBeFalse($ability);
    }
});

it('keeps hidden tickets out of every page, component and download', function () {
    Storage::fake('local');
    $this->me->givePermissionTo(['tickets.view.own', 'tickets.view.group', 'tickets.edit', 'notes.manage']);
    $this->actingAs(User::find($this->me->id));
    $hidden = $this->tickets['foreign group'];
    $hidden->update(['subject' => 'Streng geheim']);
    $attachment = app(AttachmentStorage::class)->storeContents('x', 'geheim.txt', 'text/plain', TicketMessage::factory()->for($hidden)->create());
    Appointment::factory()->for($hidden)->create(['note' => 'Geheimer Termin']);

    $this->get(route('intern.tickets.show', $hidden))->assertForbidden();
    $this->get($attachment->url())->assertForbidden();
    Livewire::test(TicketView::class, ['ticket' => $hidden])->assertForbidden();
    Livewire::test(Composer::class, ['ticket' => $hidden])->assertForbidden();
    Livewire::test(TicketList::class)->call('setView', 'all')->assertDontSee('Streng geheim');
    Livewire::test(AppointmentList::class)->assertDontSee('Geheimer Termin');
});

it('rejects tampering with locked ticket properties', function (string $component, string $property) {
    $this->me->givePermissionTo(['tickets.view.all', 'tickets.edit']);
    $this->actingAs(User::find($this->me->id));

    $params = $component === TicketList::class ? [] : ['ticket' => $this->tickets['my group']];
    Livewire::test($component, $params)->set($property, $this->tickets['foreign group']->id);
})->with([
    [TicketView::class, 'ticket'],
    [Composer::class, 'ticket'],
    [TicketList::class, 'selectedId'],
])->throws(CannotUpdateLockedPropertyException::class);

it('does not allow VOS through the follow-up dialog', function () {
    $this->me->givePermissionTo(['tickets.view.all', 'tickets.edit']);
    $this->actingAs(User::find($this->me->id));

    Livewire::test(TicketView::class, ['ticket' => $this->tickets['my group']])
        ->set('pendingStatus', 'on_site')
        ->set('followUpDate', today()->toDateString())
        ->call('confirmFollowUp')
        ->assertStatus(422);
});

it('logs every change of a ticket into its history', function () {
    Storage::fake('local');
    $this->me->givePermissionTo(['tickets.view.all', 'tickets.edit', 'notes.manage', 'tickets.merge']);
    $this->actingAs($user = User::find($this->me->id));
    $ticket = $this->tickets['my group'];

    $view = fn () => Livewire::test(TicketView::class, ['ticket' => $ticket]);
    $view()->call('setPriority', 'urgent');
    $view()->call('take');
    Livewire::test(Composer::class, ['ticket' => $ticket])->set('body', '<p>Antwort</p>')->call('send');
    Livewire::test(Composer::class, ['ticket' => $ticket])->call('setMode', 'note')->set('body', '<p>Notiz</p>')->call('send');
    $view()->call('planAppointment')->call('saveAppointment');
    $view()->call('setStatus', 'waiting_external')->call('confirmFollowUp');
    Livewire::test(TicketView::class, ['ticket' => $this->tickets['pool without group']])->call('merge', $ticket->id);

    $entries = Activity::forHistoryOf($ticket->refresh())->where('causer_id', $user->id)->orderBy('id')->get()
        ->map(fn (Activity $a) => "{$a->subject_type}.{$a->action}")->all();

    expect($entries)->toBe([
        'ticket.updated',          // priority
        'ticket.updated',          // take (assignee + status)
        'ticket.reply_added',
        'ticket.note_added',
        'appointment.created',
        'ticket.updated',          // status VOS
        'ticket.updated',          // waiting externally + follow-up
        'ticket.merged_from',
    ]);
});
