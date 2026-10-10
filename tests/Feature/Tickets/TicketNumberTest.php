<?php

use App\Models\Ticket;
use App\Services\TicketNumberGenerator;
use Illuminate\Support\Facades\DB;

it('numbers tickets per year starting at 00001', function () {
    $this->travelTo('2026-03-01');

    expect(Ticket::factory()->create()->number)->toBe('2026-00001')
        ->and(Ticket::factory()->create()->number)->toBe('2026-00002');
});

it('restarts the counter in a new year', function () {
    $this->travelTo('2026-12-31 23:59');
    Ticket::factory()->count(3)->create();

    $this->travelTo('2027-01-01 00:01');
    expect(Ticket::factory()->create()->number)->toBe('2027-00001');

    $this->travelTo('2026-12-31 23:59');
    expect(app(TicketNumberGenerator::class)->next())->toBe('2026-00004');
});

it('continues an existing counter and pads to five digits', function () {
    $this->travelTo('2026-06-01');
    DB::table('ticket_counters')->insert(['year' => 2026, 'last_number' => 122]);

    expect(Ticket::factory()->create()->number)->toBe('2026-00123');
});

it('never hands out the same number twice', function () {
    $numbers = collect(range(1, 50))->map(fn () => app(TicketNumberGenerator::class)->next());

    expect($numbers->unique())->toHaveCount(50);
});

it('does not consume a number when the ticket already has one', function () {
    $this->travelTo('2026-06-01');
    Ticket::factory()->create(['number' => '2025-00099']);

    expect(Ticket::factory()->create()->number)->toBe('2026-00001');
});
