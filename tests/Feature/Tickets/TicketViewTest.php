<?php

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Livewire\Intern\Tickets\TicketView;
use App\Models\Attachment;
use App\Models\Contact;
use App\Models\Customer;
use App\Models\Group;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

beforeEach(function () {
    $this->staff = User::factory()->staff()->create(['name' => 'Anna Becker']);
    $this->customer = Customer::factory()->create(['company' => 'Müller GmbH']);
    $this->ticket = Ticket::factory()->create(['customer_id' => $this->customer->id, 'contact_id' => null, 'group_id' => null]);
    $this->actingAs($this->staff);
});

function ticketView(): Testable
{
    return Livewire::test(TicketView::class, ['ticket' => test()->ticket]);
}

describe('conversation', function () {
    it('shows messages chronologically with internal notes marked', function () {
        TicketMessage::factory()->for($this->ticket)->create(['body' => '<p>Erste Nachricht</p>', 'created_at' => now()->subHour()]);
        TicketMessage::factory()->for($this->ticket)->internal()->create(['body' => '<p>Nur für uns</p>']);

        ticketView()
            ->assertSeeInOrder(['Erste Nachricht', 'Interne Notiz', 'Nur für uns'])
            ->assertSee('bg-yellow-500/10', false);
    });

    it('renders the sanitized HTML and attachments', function () {
        $message = TicketMessage::factory()->for($this->ticket)->create(['body' => '<p>Siehe <strong>Anhang</strong></p>']);
        $attachment = new Attachment(['disk' => 'local', 'path' => 'x/y.pdf', 'original_name' => 'angebot.pdf', 'mime_type' => 'application/pdf', 'size' => 2048]);
        $attachment->attachable()->associate($message)->save();

        ticketView()
            ->assertSee('<strong>Anhang</strong>', false)
            ->assertSee('angebot.pdf')
            ->assertSee('2.0 KB')
            ->assertSee($attachment->url(inline: true));
    });

    it('shows contacts as customer authors', function () {
        $contact = Contact::factory()->for($this->customer)->create(['first_name' => 'Kim', 'last_name' => 'Kunde']);
        TicketMessage::factory()->for($this->ticket)->create(['author_type' => 'contact', 'author_id' => $contact->id]);

        ticketView()->assertSee('Kim Kunde')->assertSee('· Kunde');
    });
});

describe('fields', function () {
    it('shows the ticket fields in the sidebar', function () {
        ticketView()
            ->assertSee('Status')->assertSee('Priorität')->assertSee('Gruppe')->assertSee('Bearbeiter')
            ->assertSee('Müller GmbH')->assertSee('Ansprechpartner')->assertSee('Intern');
    });

    it('changes priority, group and assignee with activity log', function () {
        $group = Group::factory()->create(['name' => 'Technik']);
        $this->staff->groups()->attach($group);

        ticketView()
            ->call('setPriority', 'urgent')
            ->assertDispatched('toast', message: 'Priorität: Dringend')
            ->assertDispatched('ticket-updated')
            ->call('setGroup', (string) $group->id)
            ->call('setAssignee', (string) $this->staff->id);

        $this->ticket->refresh();
        expect($this->ticket->priority)->toBe(TicketPriority::Urgent)
            ->and($this->ticket->group_id)->toBe($group->id)
            ->and($this->ticket->assignee_id)->toBe($this->staff->id)
            ->and($this->ticket->activities()->where('action', 'updated')->count())->toBe(3);
    });

    it('leaves the ticket when it is handed over to a foreign group', function () {
        $foreign = Group::factory()->create(['name' => 'Technik']);

        ticketView()
            ->call('setGroup', (string) $foreign->id)
            ->assertRedirect(route('intern.tickets.index'));

        expect(session('toast.message'))->toContain('nicht mehr sichtbar');
    });

    it('closes and reopens tickets', function () {
        ticketView()->call('setStatus', 'closed');
        expect($this->ticket->refresh())->status->toBe(TicketStatus::Closed)->closed_at->not->toBeNull();

        ticketView()->call('setStatus', 'in_progress');
        expect($this->ticket->refresh()->closed_at)->toBeNull();
    });

    it('finds and assigns a customer and drops a contact of another customer', function () {
        $old = Contact::factory()->for($this->customer)->create();
        $this->ticket->contact()->associate($old)->save();
        $new = Customer::factory()->create(['company' => 'Schmidt AG']);
        Customer::factory()->archived()->create(['company' => 'Schmidt Alt']);

        ticketView()
            ->set('customerSearch', 'schmidt')
            ->assertSee('Schmidt AG')
            ->assertDontSee('Schmidt Alt')
            ->call('setCustomer', $new->id)
            ->assertSet('customerSearch', '');

        expect($this->ticket->refresh())->customer_id->toBe($new->id)->contact_id->toBeNull();
    });

    it('only accepts contacts of the ticket customer', function () {
        $own = Contact::factory()->for($this->customer)->create(['last_name' => 'Eigen']);
        $foreign = Contact::factory()->create();

        ticketView()->call('setContact', (string) $own->id);
        expect($this->ticket->refresh()->contact_id)->toBe($own->id);

        ticketView()->call('setContact', (string) $foreign->id)->assertNotFound();
    });

    it('can remove the customer', function () {
        ticketView()->call('setCustomer', null);

        expect($this->ticket->refresh()->customer_id)->toBeNull();
    });

    it('does not assign deactivated users', function () {
        $inactive = User::factory()->inactive()->create();

        ticketView()->call('setAssignee', (string) $inactive->id)->assertNotFound();
    });
});

describe('permissions', function () {
    it('needs tickets.edit to change fields', function () {
        $viewer = User::factory()->create();
        $viewer->givePermissionTo('tickets.view.group');
        $this->actingAs($viewer);

        ticketView()
            ->assertSee('disabled', false)
            ->call('setPriority', 'urgent')
            ->assertForbidden();
    });

    it('deletes tickets only with tickets.delete', function () {
        ticketView()->assertDontSee('Ticket löschen')->call('delete')->assertForbidden();

        $this->actingAs(User::factory()->admin()->create());
        ticketView()->call('delete')->assertRedirect(route('intern.tickets.index'));

        expect(Ticket::find($this->ticket->id))->toBeNull();
    });
});
