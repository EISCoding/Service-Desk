<?php

use App\Enums\NotificationEvent;
use App\Jobs\FetchMailbox;
use App\Livewire\Intern\NotificationBell;
use App\Livewire\Intern\Tickets\TicketView;
use App\Livewire\Portal\Tickets\Show as PortalShow;
use App\Mail\Inbound\MailboxConnector;
use App\Models\Contact;
use App\Models\Group;
use App\Models\Mailbox;
use App\Models\Ticket;
use App\Models\User;
use App\Notifications\Staff\TicketNotification;
use App\Services\StaffNotifier;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\Support\FakeMailboxConnector;

beforeEach(function () {
    Storage::fake('local');
    Notification::fake();
    $this->group = Group::factory()->create(['name' => 'Support']);
    $this->member = User::factory()->staff()->create();
    $this->member->groups()->attach($this->group);
    $this->outsider = User::factory()->staff()->create();
});

describe('new tickets', function () {
    it('notifies the members of the group, not the causer or inactive members', function () {
        $inactive = User::factory()->staff()->create(['is_active' => false]);
        $inactive->groups()->attach($this->group);
        $creator = User::factory()->staff()->create();
        $creator->groups()->attach($this->group);
        $ticket = Ticket::factory()->create(['group_id' => $this->group->id]);

        app(StaffNotifier::class)->newTicket($ticket, $creator);

        Notification::assertSentTo($this->member, TicketNotification::class, fn ($n, $channels) => $n->event === NotificationEvent::NewTicket
            && $n->title === 'Neues Ticket in Support' && $channels === ['database']);
        Notification::assertNotSentTo([$inactive, $creator, $this->outsider], TicketNotification::class);
    });

    it('notifies everyone with access to group tickets about tickets without group', function () {
        $ownOnly = User::factory()->create();
        $ownOnly->givePermissionTo('tickets.view.own');

        app(StaffNotifier::class)->newTicket(Ticket::factory()->create(['group_id' => null]));

        Notification::assertSentTo([$this->member, $this->outsider], TicketNotification::class);
        Notification::assertNotSentTo($ownOnly, TicketNotification::class);
    });

    it('is triggered by incoming mails', function () {
        $connector = new FakeMailboxConnector;
        app()->instance(MailboxConnector::class, $connector);
        $mailbox = Mailbox::factory()->create(['default_group_id' => $this->group->id]);

        $connector->reply('Drucker defekt', from: 'Neu <neu@example.org>');
        FetchMailbox::dispatchSync($mailbox);

        Notification::assertSentTo($this->member, TicketNotification::class, fn ($n) => $n->event === NotificationEvent::NewTicket);
    });
});

describe('assignments', function () {
    it('notifies the new assignee by bell and mail, but not when assigning oneself', function () {
        $ticket = Ticket::factory()->create(['group_id' => $this->group->id]);
        $lead = User::factory()->admin()->create(['name' => 'Lea Lead']);

        $this->actingAs($lead);
        Livewire::test(TicketView::class, ['ticket' => $ticket])->call('setAssignee', (string) $this->member->id);
        Livewire::test(TicketView::class, ['ticket' => $ticket->refresh()])->call('setAssignee', (string) $lead->id);

        Notification::assertSentTo($this->member, TicketNotification::class, fn ($n, $channels) => $n->event === NotificationEvent::Assigned
            && $n->title === 'Lea Lead hat dir ein Ticket zugewiesen' && $channels === ['database', 'mail']);
        Notification::assertNotSentTo($lead, TicketNotification::class);
    });
});

describe('customer replies', function () {
    beforeEach(function () {
        $this->contact = Contact::factory()->withPortalAccess()->create(['first_name' => 'Anna', 'last_name' => 'Kunde', 'email' => 'anna.becker@kunde.test', 'password' => 'Kundenportal2026']);
        $this->ticket = Ticket::factory()->create(['group_id' => $this->group->id, 'customer_id' => $this->contact->customer_id, 'contact_id' => $this->contact->id]);
    });

    it('notifies the assignee about portal replies', function () {
        $assignee = User::factory()->staff()->create();
        $this->ticket->forceFill(['assignee_id' => $assignee->id])->save();

        $this->actingAs($this->contact, 'contact');
        Livewire::test(PortalShow::class, ['ticket' => $this->ticket])->set('body', '<div>Es geht wieder nicht.</div>')->call('reply');

        Notification::assertSentTo($assignee, TicketNotification::class, fn ($n) => $n->event === NotificationEvent::CustomerReply
            && $n->title === 'Antwort von Anna Kunde' && $n->text === 'Es geht wieder nicht.');
        Notification::assertNotSentTo($this->member, TicketNotification::class);
    });

    it('notifies the group about mail replies to tickets without assignee', function () {
        $connector = new FakeMailboxConnector;
        app()->instance(MailboxConnector::class, $connector);
        $connector->reply("Nachtrag [#{$this->ticket->number}]");

        FetchMailbox::dispatchSync(Mailbox::factory()->create());

        Notification::assertSentTo($this->member, TicketNotification::class, fn ($n) => $n->event === NotificationEvent::CustomerReply);
    });
});

it('respects the notification settings of the user', function () {
    $this->member->update(['notification_settings' => ['new_ticket' => ['database' => false, 'mail' => true]]]);

    expect((new TicketNotification(NotificationEvent::NewTicket, Ticket::factory()->create(), 'Neu'))->via($this->member))->toBe(['mail']);
});

it('writes a German mail with a link to the ticket', function () {
    $ticket = Ticket::factory()->create(['subject' => 'Drucker defekt']);
    $mail = (new TicketNotification(NotificationEvent::CustomerReply, $ticket, 'Antwort von Anna Kunde', 'Geht wieder nicht.'))->toMail($this->member);

    expect($mail->subject)->toBe("Antwort von Anna Kunde – {$ticket->reference()}")
        ->and($mail->actionUrl)->toBe(route('intern.tickets.show', $ticket))
        ->and($mail->introLines)->toContain('„Geht wieder nicht.“');
});

/**
 * Database notification as the bell stores it (Notification::fake() intercepts real sending).
 */
function bellNotification(User $user, Ticket $ticket, string $title): void
{
    $user->notifications()->create([
        'id' => (string) Str::uuid(),
        'type' => TicketNotification::class,
        'data' => (new TicketNotification(NotificationEvent::NewTicket, $ticket, $title))->toArray($user),
    ]);
}

describe('bell', function () {
    beforeEach(function () {
        $this->ticket = Ticket::factory()->create(['group_id' => $this->group->id, 'subject' => 'Drucker defekt']);
        bellNotification($this->member, $this->ticket, 'Neues Ticket in Support');
        $this->actingAs($this->member);
    });

    it('shows unread notifications in the topbar', function () {
        $this->get('/intern/tickets')->assertSee('Benachrichtigungen (1 ungelesen)');

        Livewire::test(NotificationBell::class)
            ->assertSee(['Neues Ticket in Support', $this->ticket->reference(), 'Drucker defekt']);
    });

    it('opens the ticket and marks the notification as read', function () {
        $id = $this->member->notifications()->sole()->id;

        Livewire::test(NotificationBell::class)->call('open', $id)->assertRedirect(route('intern.tickets.show', $this->ticket));

        expect($this->member->unreadNotifications()->count())->toBe(0);
    });

    it('marks all as read and never opens notifications of others', function () {
        $foreign = User::factory()->create();
        bellNotification($foreign, $this->ticket, 'Zugewiesen');

        Livewire::test(NotificationBell::class)->call('open', $foreign->notifications()->sole()->id)->assertNotFound();
        Livewire::test(NotificationBell::class)->call('markAllRead')->assertDontSee('Alle gelesen');

        expect($this->member->unreadNotifications()->count())->toBe(0)
            ->and($foreign->unreadNotifications()->count())->toBe(1);
    });
});
