<?php

use Illuminate\Console\Scheduling\CallbackEvent;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;

function queueWorkerEvent(): ?Event
{
    return collect(app(Schedule::class)->events())
        ->first(fn (Event $event) => str_contains($event->command, 'queue:work'));
}

it('starts a worker every minute that ends before the next one', function () {
    $event = queueWorkerEvent();

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('* * * * *')
        ->and($event->command)
        ->toContain('--max-time=55')
        ->toContain('--sleep=3');
});

it('fetches the mailboxes every 20 seconds without starting a process', function () {
    $event = collect(app(Schedule::class)->events())->first(fn (Event $event) => $event->description === 'fetch-mail');

    expect($event)->toBeInstanceOf(CallbackEvent::class)
        ->and($event->repeatSeconds)->toBe(20)
        ->and($event->withoutOverlapping)->toBeTrue();
});

it('never runs two queue workers at the same time', function () {
    $event = queueWorkerEvent();

    expect($event->withoutOverlapping)->toBeTrue()
        ->and($event->expiresAt)->toBe(10);
});

it('runs the worker in the background so other tasks are not delayed', function () {
    expect(queueWorkerEvent()->runInBackground)->toBeTrue();
});

it('lets the worker stop well before the job retry window', function () {
    preg_match('/--timeout=(\d+)/', queueWorkerEvent()->command, $timeout);

    expect((int) $timeout[1])->toBeLessThan(config('queue.connections.database.retry_after'));
});
