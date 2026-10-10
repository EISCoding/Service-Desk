<?php

use App\Enums\MessageType;
use App\Enums\TicketSource;
use App\Jobs\FetchMailbox;
use App\Mail\Inbound\MailboxConnector;
use App\Models\Group;
use App\Models\Mailbox;
use App\Models\Ticket;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Support\FakeMailboxConnector;

beforeEach(function () {
    $this->travelTo('2026-10-15 08:00');
    Storage::fake('local');
    $this->connector = new FakeMailboxConnector;
    app()->instance(MailboxConnector::class, $this->connector);
    $this->group = Group::factory()->create();
    $this->mailbox = Mailbox::factory()->create(['email' => 'support@musterfirma.test', 'default_group_id' => $this->group->id]);
});

it('turns a new mail into a ticket of the mailbox', function () {
    $this->connector->add('new-from-contact');

    FetchMailbox::dispatchSync($this->mailbox);

    $ticket = Ticket::sole();
    expect($ticket)
        ->subject->toBe('Drucker im 2. OG druckt nicht')
        ->source->toBe(TicketSource::Mail)
        ->mailbox_id->toBe($this->mailbox->id)
        ->group_id->toBe($this->group->id)
        ->requester_email->toBe('anna.becker@kunde.test');

    $message = $ticket->messages->sole();
    expect($message->type)->toBe(MessageType::MailIn)
        ->and($message->message_id)->toBe('CAF1234567890@mail.kunde.test')
        ->and($message->from_email)->toBe('anna.becker@kunde.test')
        ->and($message->body)->toContain('<b>nicht mehr</b>')->not->toContain('script')
        ->and($message->attachments->pluck('original_name')->all())->toBe(['logo.png', 'fehlerbericht.pdf'])
        ->and($message->created_at->format('Y-m-d H:i'))->toBe('2026-10-14 09:15')
        ->and($this->connector->processed)->toBe(['Drucker im 2. OG druckt nicht'])
        ->and($this->mailbox->refresh()->last_fetched_at)->not->toBeNull()
        ->and($this->mailbox->last_error)->toBeNull();
});

it('never imports the same mail twice', function () {
    $this->connector->add('new-from-contact');
    FetchMailbox::dispatchSync($this->mailbox);
    $this->connector->add('new-from-contact');
    FetchMailbox::dispatchSync($this->mailbox);

    expect(Ticket::count())->toBe(1)->and($this->connector->processed)->toHaveCount(2);
});

it('uses the arrival time for mails dated in the future', function () {
    $this->travelTo('2026-10-01 08:00');
    $this->connector->add('new-from-contact');

    FetchMailbox::dispatchSync($this->mailbox);

    expect(Ticket::sole()->messages->sole()->created_at->format('Y-m-d H:i'))->toBe('2026-10-01 08:00');
});

it('records connection errors on the mailbox', function () {
    $this->connector->failWith = 'Authentication failed';

    FetchMailbox::dispatchSync($this->mailbox);

    expect($this->mailbox->refresh()->last_error)->toBe('Authentication failed');
});

it('skips inactive mailboxes', function () {
    $this->connector->add('new-from-contact');
    $this->mailbox->update(['is_active' => false]);

    FetchMailbox::dispatchSync($this->mailbox);

    expect(Ticket::count())->toBe(0);
});

it('dispatches one job per active mailbox every minute without overlapping', function () {
    Queue::fake();
    Mailbox::factory()->create(['is_active' => false]);
    $second = Mailbox::factory()->create();

    $this->artisan('servicedesk:fetch-mail')->assertSuccessful();

    Queue::assertPushed(FetchMailbox::class, 2);
    Queue::assertPushed(FetchMailbox::class, fn (FetchMailbox $job) => $job->mailbox->is($second));

    $event = collect(app(Schedule::class)->events())->first(fn (Event $e) => str_contains($e->command, 'servicedesk:fetch-mail'));
    expect($event->expression)->toBe('* * * * *')->and($event->withoutOverlapping)->toBeTrue();
});

it('is unique per mailbox and does not overlap', function () {
    $job = new FetchMailbox($this->mailbox);

    expect($job)->toBeInstanceOf(ShouldBeUnique::class)
        ->and($job->uniqueId())->toBe((string) $this->mailbox->id)
        ->and($job->middleware()[0])->toBeInstanceOf(WithoutOverlapping::class);
});
