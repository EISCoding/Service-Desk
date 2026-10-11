<?php

use App\Enums\TicketStatus;
use App\Jobs\MarkDueFollowUps;
use App\Models\Ticket;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;

beforeEach(fn () => $this->travelTo('2026-10-14 06:00'));

it('flags waiting tickets whose follow-up date has come', function () {
    $due = Ticket::factory()->create(['status' => TicketStatus::WaitingInternal, 'follow_up_at' => '2026-10-14']);
    $overdue = Ticket::factory()->create(['status' => TicketStatus::WaitingExternal, 'follow_up_at' => '2026-10-10']);
    $later = Ticket::factory()->create(['status' => TicketStatus::WaitingInternal, 'follow_up_at' => '2026-10-15']);

    (new MarkDueFollowUps)->handle();

    expect($due->refresh()->follow_up_due)->toBeTrue()
        ->and($overdue->refresh()->follow_up_due)->toBeTrue()
        ->and($later->refresh()->follow_up_due)->toBeFalse();
});

it('clears stale flags of tickets that are no longer waiting', function () {
    $moved = Ticket::factory()->create(['status' => TicketStatus::InProgress, 'follow_up_due' => true]);

    (new MarkDueFollowUps)->handle();

    expect($moved->refresh()->follow_up_due)->toBeFalse();
});

it('does not write activity entries or touch the last activity', function () {
    $ticket = Ticket::factory()->create(['status' => TicketStatus::WaitingInternal, 'follow_up_at' => '2026-10-14', 'last_activity_at' => '2026-10-01']);
    $entries = $ticket->activities()->count();

    (new MarkDueFollowUps)->handle();

    expect($ticket->activities()->count())->toBe($entries)
        ->and($ticket->refresh()->last_activity_at->toDateString())->toBe('2026-10-01');
});

it('runs every morning via the scheduler', function () {
    $event = collect(app(Schedule::class)->events())->first(fn (Event $event) => $event->description === 'mark-due-follow-ups');

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('0 6 * * *');
});
