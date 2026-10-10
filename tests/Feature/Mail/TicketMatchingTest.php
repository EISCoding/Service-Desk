<?php

use App\Jobs\FetchMailbox;
use App\Mail\Inbound\MailboxConnector;
use App\Models\Mailbox;
use App\Models\Ticket;
use App\Models\TicketMessage;
use Illuminate\Support\Facades\Storage;
use Tests\Support\FakeMailboxConnector;

beforeEach(function () {
    $this->travelTo('2026-10-15 12:00');
    Storage::fake('local');
    $this->connector = new FakeMailboxConnector;
    app()->instance(MailboxConnector::class, $this->connector);
    $this->mailbox = Mailbox::factory()->create(['email' => 'support@musterfirma.test']);
    $this->ticket = Ticket::factory()->create(['subject' => 'Drucker defekt']);
});

function fetch(): void
{
    FetchMailbox::dispatchSync(test()->mailbox);
}

it('adds a mail with [#number] in the subject to that ticket', function () {
    $this->connector->reply("AW: Drucker defekt [#{$this->ticket->number}]");

    fetch();

    expect(Ticket::count())->toBe(1)
        ->and($this->ticket->messages->sole()->plainText())->toContain('Danke, das hat geholfen.');
});

it('also understands Ticket#number in the subject', function () {
    $this->connector->reply("Re: Ticket#{$this->ticket->number} Drucker");

    fetch();

    expect(Ticket::count())->toBe(1)->and($this->ticket->messages)->toHaveCount(1);
});

it('matches replies by In-Reply-To / References', function () {
    TicketMessage::factory()->for($this->ticket)->create(['message_id' => 'servicedesk-abc@musterfirma.test']);

    $this->connector->reply('Re: ohne Nummer', inReplyTo: 'servicedesk-abc@musterfirma.test');
    fetch();

    expect(Ticket::count())->toBe(1)->and($this->ticket->messages)->toHaveCount(2);
});

it('creates a new ticket when nothing matches', function () {
    $this->connector->reply('Neues Problem', inReplyTo: 'unbekannt@irgendwo');
    $this->connector->reply('Re: [#2020-99999] gibt es nicht');

    fetch();

    expect(Ticket::count())->toBe(3)
        ->and(Ticket::latest('id')->first()->subject)->toBe('Re: [#2020-99999] gibt es nicht');
});

it('follows merged tickets to their target', function () {
    $target = Ticket::factory()->create();
    $this->ticket->forceFill(['merged_into_id' => $target->id])->save();

    $this->connector->reply("AW: [#{$this->ticket->number}]");
    fetch();

    expect($target->messages)->toHaveCount(1)->and($this->ticket->messages)->toHaveCount(0);
});

it('prefers the subject tag over threading headers', function () {
    $other = Ticket::factory()->create();
    TicketMessage::factory()->for($other)->create(['message_id' => 'alt@musterfirma.test']);

    $this->connector->reply("AW: [#{$this->ticket->number}]", inReplyTo: 'alt@musterfirma.test');
    fetch();

    expect($this->ticket->messages)->toHaveCount(1)->and($other->messages)->toHaveCount(1);
});
