<?php

use App\Mail\Inbound\MailboxConnector;
use App\Models\Mailbox;
use App\Models\Ticket;
use App\Support\QueueAfterResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Support\FakeMailboxConnector;

it('works off queued jobs right after the response', function () {
    config(['queue.default' => 'database']);
    dispatch(fn () => cache()->put('sent-after-response', true));
    expect(DB::table('jobs')->count())->toBe(1);

    app(QueueAfterResponse::class)->schedule();
    app(QueueAfterResponse::class)->schedule(); // once per request
    app()->terminate();

    expect(cache('sent-after-response'))->toBeTrue()
        ->and(DB::table('jobs')->count())->toBe(0);
});

it('does nothing with the sync queue', function () {
    config(['queue.default' => 'sync']);

    app(QueueAfterResponse::class)->schedule();
    app()->terminate();

    expect(DB::table('jobs')->count())->toBe(0);
});

it('fetches mails in the same process with --now', function () {
    Storage::fake('local');
    config(['queue.default' => 'database']);
    $connector = new FakeMailboxConnector;
    app()->instance(MailboxConnector::class, $connector);
    Mailbox::factory()->create();
    $connector->reply('Drucker defekt', from: 'Neu <neu@example.org>');

    $this->artisan('servicedesk:fetch-mail')->assertSuccessful();
    expect(Ticket::count())->toBe(0)->and(DB::table('jobs')->count())->toBe(1);

    DB::table('jobs')->delete();
    $this->artisan('servicedesk:fetch-mail', ['--now' => true])->assertSuccessful();
    expect(Ticket::sole()->subject)->toBe('Drucker defekt');
});
