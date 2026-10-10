<?php

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Livewire\Intern\Tickets\TicketList;
use App\Models\Customer;
use App\Models\Group;
use App\Models\Ticket;
use App\Models\User;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

beforeEach(function () {
    $this->group = Group::factory()->create(['name' => 'Support']);
    $this->other = Group::factory()->create(['name' => 'Technik']);
    $this->me = User::factory()->staff()->create(['name' => 'Anna Becker']);
    $this->me->groups()->attach($this->group);
    $this->actingAs($this->me);
});

/**
 * A list with all session-persisted filters reset.
 */
function freshList(): Testable
{
    return Livewire::test(TicketList::class)->call('resetFilters');
}

function listed(Testable $component): array
{
    return $component->instance()->tickets->pluck('subject')->all();
}

it('is the ticket page under /intern/tickets', function () {
    $this->get('/intern/tickets')
        ->assertOk()
        ->assertSee('<title>Tickets · ServiceDesk</title>', false)
        ->assertSeeLivewire(TicketList::class)
        ->assertSee('Kein Ticket ausgewählt');
});

it('is forbidden without any ticket view permission', function () {
    $this->actingAs(User::factory()->create())->get('/intern/tickets')->assertForbidden();
});

it('starts the staff area on the first allowed page', function () {
    $this->get('/intern')->assertRedirect(route('intern.tickets.index'));

    $this->actingAs(User::factory()->create())->get('/intern')->assertRedirect(route('intern.settings.general'));
});

describe('views', function () {
    beforeEach(function () {
        Ticket::factory()->create(['subject' => 'Mir zugewiesen', 'assignee_id' => $this->me->id, 'group_id' => $this->other->id]);
        Ticket::factory()->create(['subject' => 'Meine Gruppe offen', 'group_id' => $this->group->id]);
        Ticket::factory()->create(['subject' => 'Pool ohne Gruppe', 'group_id' => null, 'assignee_id' => User::factory()->create()->id]);
        Ticket::factory()->create(['subject' => 'Fremde Gruppe', 'group_id' => $this->other->id]);
    });

    it('shows all visible tickets by default', function () {
        expect(listed(Livewire::test(TicketList::class)))
            ->toEqualCanonicalizing(['Mir zugewiesen', 'Meine Gruppe offen', 'Pool ohne Gruppe']);
    });

    it('filters mine, my groups and unassigned', function () {
        $list = Livewire::test(TicketList::class);

        expect(listed($list->call('setView', 'mine')))->toBe(['Mir zugewiesen'])
            ->and(listed($list->call('setView', 'groups')))->toBe(['Meine Gruppe offen'])
            ->and(listed($list->call('setView', 'unassigned')))->toBe(['Meine Gruppe offen']);
    });

    it('counts open tickets per view', function () {
        Ticket::factory()->status(TicketStatus::Closed)->create(['group_id' => $this->group->id]);

        expect(Livewire::test(TicketList::class)->instance()->counts)
            ->toBe(['mine' => 1, 'groups' => 1, 'all' => 3, 'unassigned' => 1]);
    });

    it('lets admins see every ticket', function () {
        $this->actingAs(User::factory()->admin()->create());

        expect(Livewire::test(TicketList::class)->instance()->counts['all'])->toBe(4);
    });
});

describe('filters', function () {
    it('hides closed tickets unless asked', function () {
        Ticket::factory()->create(['subject' => 'Offen']);
        Ticket::factory()->status(TicketStatus::Closed)->create(['subject' => 'Erledigt']);

        $list = Livewire::test(TicketList::class);
        expect(listed($list))->toBe(['Offen'])
            ->and(listed($list->set('status', 'closed')))->toBe(['Erledigt'])
            ->and(listed($list->set('status', 'all')))->toHaveCount(2);
    });

    it('filters by priority, group, assignee and customer', function () {
        $customer = Customer::factory()->create(['company' => 'Müller GmbH']);
        Ticket::factory()->create(['subject' => 'Dringend', 'priority' => TicketPriority::Urgent, 'group_id' => $this->group->id, 'assignee_id' => $this->me->id, 'customer_id' => $customer->id]);
        Ticket::factory()->create(['subject' => 'Normal']);

        expect(listed(freshList()->set('priority', 'urgent')))->toBe(['Dringend'])
            ->and(listed(freshList()->set('group', (string) $this->group->id)))->toBe(['Dringend'])
            ->and(listed(freshList()->set('group', 'none')))->toBe(['Normal'])
            ->and(listed(freshList()->set('assignee', (string) $this->me->id)))->toBe(['Dringend'])
            ->and(listed(freshList()->set('assignee', 'none')))->toBe(['Normal'])
            ->and(listed(freshList()->set('customer', 'müller')))->toBe(['Dringend']);
    });

    it('filters tickets without customer and due follow-ups', function () {
        Ticket::factory()->withoutCustomer()->create(['subject' => 'Unbekannter Absender']);
        Ticket::factory()->create(['subject' => 'Fällig', 'follow_up_due' => true]);

        expect(listed(freshList()->set('withoutCustomer', true)))->toBe(['Unbekannter Absender'])
            ->and(listed(freshList()->set('dueOnly', true)))->toBe(['Fällig']);
    });

    it('searches subject and number', function () {
        $ticket = Ticket::factory()->create(['subject' => 'Drucker defekt']);
        Ticket::factory()->create(['subject' => 'VPN']);

        expect(listed(freshList()->set('search', 'drucker')))->toBe(['Drucker defekt'])
            ->and(listed(freshList()->set('search', $ticket->number)))->toBe(['Drucker defekt'])
            ->and(listed(freshList()->set('search', $ticket->reference())))->toBe(['Drucker defekt']);
    });

    it('sorts by activity, priority or creation', function () {
        Ticket::factory()->create(['subject' => 'Alt aber dringend', 'priority' => TicketPriority::Urgent, 'last_activity_at' => now()->subDay(), 'created_at' => now()->subDays(2)]);
        Ticket::factory()->create(['subject' => 'Neu und normal', 'last_activity_at' => now(), 'created_at' => now()->subDays(3)]);

        expect(listed(Livewire::test(TicketList::class)))->toBe(['Neu und normal', 'Alt aber dringend'])
            ->and(listed(freshList()->set('sort', 'priority')))->toBe(['Alt aber dringend', 'Neu und normal'])
            ->and(listed(freshList()->set('sort', 'created')))->toBe(['Alt aber dringend', 'Neu und normal']);
    });

    it('resets filters and keeps them in the session', function () {
        Livewire::test(TicketList::class)->set('priority', 'urgent')->set('dueOnly', true);

        $list = Livewire::test(TicketList::class)->assertSet('priority', 'urgent');
        expect($list->instance()->activeFilterCount())->toBe(2);

        $list->call('resetFilters')->assertSet('priority', '')->assertSet('dueOnly', false);
    });

    it('loads more tickets on demand', function () {
        Ticket::factory()->count(35)->create(['customer_id' => Customer::factory()->create()->id, 'contact_id' => null]);

        $list = Livewire::test(TicketList::class)->assertSee('Weitere laden');
        expect($list->instance()->tickets)->toHaveCount(31);

        $list->call('loadMore')->assertDontSee('Weitere laden');
    });
});

it('keeps its header (views, search, filters) sticky over the whole list', function () {
    // The sticky header's parent must grow with the list (min-h-full, not h-full),
    // otherwise the header scrolls away once the list is longer than the window.
    $html = Livewire::test(TicketList::class)->html();

    expect($html)->toMatch('/^<div[^>]*class="flex min-h-full flex-col"/')
        ->and($html)->toContain('sticky top-0');
});

it('shows status, priority, due follow-up and assignee in the list', function () {
    Ticket::factory()->create([
        'subject' => 'Server down', 'priority' => TicketPriority::Urgent, 'status' => TicketStatus::WaitingInternal,
        'follow_up_at' => today(), 'follow_up_due' => true, 'assignee_id' => $this->me->id,
    ]);

    Livewire::test(TicketList::class)
        ->assertSee('Server down')
        ->assertSee('Ticket#')
        ->assertSee('Warte auf Intern')
        ->assertSee('Dringend')
        ->assertSee('Wiedervorlage fällig')
        ->assertSee('Anna Becker');
});
