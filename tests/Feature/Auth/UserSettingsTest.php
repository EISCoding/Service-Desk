<?php

use App\Enums\NotificationEvent;
use App\Livewire\Intern\Settings\General;
use App\Livewire\Intern\Settings\Notifications;
use App\Livewire\Intern\Tickets\TicketList;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->staff()->create();
    $this->actingAs($this->user);
});

it('bundles all personal settings under /intern/einstellungen', function () {
    $this->get('/intern/tickets')->assertSee(route('intern.settings.general'));

    $this->get('/intern/einstellungen')->assertOk()
        ->assertSee('<title>Einstellungen · ServiceDesk</title>', false)
        ->assertSee(['Darstellung', 'Standard-Ansicht'])
        ->assertSee([
            route('intern.settings.notifications'), route('intern.settings.signature'), route('intern.settings.security'),
        ]);

    $this->get('/intern/einstellungen/benachrichtigungen')->assertOk()->assertSee('Kunde hat geantwortet');
});

it('opens the ticket list with the default view', function () {
    Livewire::test(General::class)
        ->set('ticketView', 'mine')
        ->assertDispatched('toast', message: 'Standard-Ansicht gespeichert.');

    expect($this->user->refresh()->ticket_view)->toBe('mine');
    Livewire::test(TicketList::class)->assertSet('view', 'mine');

    // The view chosen in the list wins until the default is changed again.
    Livewire::test(TicketList::class)->call('setView', 'all');
    Livewire::test(TicketList::class)->assertSet('view', 'all');

    Livewire::test(General::class)->set('ticketView', 'groups');
    Livewire::test(TicketList::class)->assertSet('view', 'groups');

    Livewire::test(General::class)->set('ticketView', 'kaputt')->assertHasErrors('ticketView');
});

it('uses sensible notification defaults', function () {
    expect($this->user->wantsNotification(NotificationEvent::NewTicket, 'database'))->toBeTrue()
        ->and($this->user->wantsNotification(NotificationEvent::NewTicket, 'mail'))->toBeFalse()
        ->and($this->user->wantsNotification(NotificationEvent::Assigned, 'mail'))->toBeTrue()
        ->and($this->user->wantsNotification(NotificationEvent::CustomerReply, 'mail'))->toBeTrue();
});

it('stores the notification settings per event and channel', function () {
    Livewire::test(Notifications::class)
        ->assertSet('settings.new_ticket.mail', false)
        ->set('settings.new_ticket.mail', true)
        ->set('settings.customer_reply.database', false)
        ->call('save')
        ->assertDispatched('toast', message: 'Benachrichtigungen gespeichert.');

    $user = $this->user->refresh();
    expect($user->wantsNotification(NotificationEvent::NewTicket, 'mail'))->toBeTrue()
        ->and($user->wantsNotification(NotificationEvent::CustomerReply, 'database'))->toBeFalse()
        ->and($user->wantsNotification(NotificationEvent::Assigned, 'database'))->toBeTrue();
});
