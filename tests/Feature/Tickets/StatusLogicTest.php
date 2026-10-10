<?php

use App\Enums\TicketStatus;
use App\Livewire\Intern\Tickets\TicketView;
use App\Models\Ticket;
use App\Models\User;
use App\Services\TicketStatusService;
use Illuminate\Support\Carbon;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

beforeEach(function () {
    $this->staff = User::factory()->staff()->create();
    $this->ticket = Ticket::factory()->create(['group_id' => null]);
    $this->actingAs($this->staff);
});

function statusView(): Testable
{
    return Livewire::test(TicketView::class, ['ticket' => test()->ticket]);
}

describe('take', function () {
    it('assigns the ticket to me and sets it to In Arbeit', function () {
        statusView()
            ->assertSee('Übernehmen')
            ->call('take')
            ->assertDispatched('toast', message: 'Ticket übernommen.')
            ->assertDontSee('Übernehmen');

        expect($this->ticket->refresh())
            ->assignee_id->toBe($this->staff->id)
            ->status->toBe(TicketStatus::InProgress);
    });

    it('takes over tickets of colleagues and reopens closed ones', function () {
        $this->ticket->forceFill(['assignee_id' => User::factory()->create()->id, 'status' => TicketStatus::Closed, 'closed_at' => now()])->save();
        $this->actingAs(User::factory()->admin()->create());

        statusView()->call('take');

        expect($this->ticket->refresh())->status->toBe(TicketStatus::InProgress)->closed_at->toBeNull();
    });
});

describe('follow-up', function () {
    it('asks for a follow-up date for Warte auf Intern/Extern', function (string $status) {
        $this->travelTo('2026-10-14 10:00'); // Wednesday

        statusView()
            ->call('setStatus', $status)
            ->assertSet('pendingStatus', $status)
            ->assertSet('followUpDate', '2026-10-16')
            ->assertSee('Wann soll das Ticket wieder vorgelegt werden?');

        expect($this->ticket->refresh()->status)->toBe(TicketStatus::New); // not applied yet
    })->with(['waiting_internal', 'waiting_external']);

    it('sets status and follow-up after confirming', function () {
        statusView()
            ->call('setStatus', 'waiting_external')
            ->set('followUpDate', today()->addDays(5)->toDateString())
            ->call('confirmStatus')
            ->assertHasNoErrors()
            ->assertSet('pendingStatus', null);

        $this->ticket->refresh();
        expect($this->ticket->status)->toBe(TicketStatus::WaitingExternal)
            ->and($this->ticket->follow_up_at->toDateString())->toBe(today()->addDays(5)->toDateString())
            ->and($this->ticket->follow_up_due)->toBeFalse()
            ->and($this->ticket->activities()->first()->changes)->toHaveKeys(['status', 'follow_up_at']);
    });

    it('requires a date that is not in the past', function () {
        statusView()->call('setStatus', 'waiting_internal')->set('followUpDate', '')->call('confirmStatus')->assertHasErrors('followUpDate');
        statusView()->call('setStatus', 'waiting_internal')->set('followUpDate', today()->subDay()->toDateString())->call('confirmStatus')->assertHasErrors('followUpDate');
    });

    it('can be cancelled', function () {
        statusView()
            ->call('setStatus', 'waiting_internal')
            ->call('cancelStatusChange')
            ->assertSet('pendingStatus', null)
            ->assertSet('statusVersion', 1);

        expect($this->ticket->refresh()->status)->toBe(TicketStatus::New);
    });

    it('clears the follow-up when the ticket moves on', function () {
        $this->ticket->forceFill(['status' => TicketStatus::WaitingInternal, 'follow_up_at' => today(), 'follow_up_due' => true])->save();

        statusView()->call('setStatus', 'in_progress');

        expect($this->ticket->refresh())->follow_up_at->toBeNull()->follow_up_due->toBeFalse();
    });

    it('defaults to the second working day', function (string $today, string $expected) {
        $this->travelTo($today);

        expect(TicketStatusService::defaultFollowUp()->toDateString())->toBe($expected);
    })->with([
        'Monday' => ['2026-10-12', '2026-10-14'],
        'Thursday' => ['2026-10-15', '2026-10-19'],
        'Friday' => ['2026-10-16', '2026-10-20'],
        'Saturday' => ['2026-10-17', '2026-10-20'],
    ]);
});

describe('service', function () {
    it('refuses waiting statuses without follow-up date', function () {
        app(TicketStatusService::class)->change($this->ticket, TicketStatus::WaitingInternal);
    })->throws(InvalidArgumentException::class);

    it('marks a follow-up for today as due immediately', function () {
        app(TicketStatusService::class)->change($this->ticket, TicketStatus::WaitingInternal, Carbon::today());

        expect($this->ticket->refresh()->follow_up_due)->toBeTrue();
    });

    it('sets and clears closed_at', function () {
        $service = app(TicketStatusService::class);

        $service->change($this->ticket, TicketStatus::Closed);
        $closedAt = $this->ticket->refresh()->closed_at;
        expect($closedAt)->not->toBeNull();

        $service->change($this->ticket, TicketStatus::Closed);
        expect($this->ticket->refresh()->closed_at->equalTo($closedAt))->toBeTrue();

        $service->change($this->ticket, TicketStatus::WaitingCustomer);
        expect($this->ticket->refresh()->closed_at)->toBeNull();
    });
});

it('needs tickets.edit to take or change the status', function () {
    $viewer = User::factory()->create();
    $viewer->givePermissionTo('tickets.view.all');
    $this->actingAs($viewer);

    statusView()->assertDontSee('Übernehmen')->call('take')->assertForbidden();
    statusView()->call('setStatus', 'closed')->assertForbidden();
});
