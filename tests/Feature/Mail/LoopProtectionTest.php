<?php

use App\Enums\TicketStatus;
use App\Jobs\FetchMailbox;
use App\Mail\Inbound\InboundMailParser;
use App\Mail\Inbound\LoopDetector;
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
});

it('recognizes automatic mails', function (array $headers, string $subject = 'Hallo', string $from = 'Kunde <kunde@kunde.test>') {
    $this->connector->reply($subject, from: $from, headers: $headers);
    $mail = iterator_to_array($this->connector->fetch($this->mailbox, 1))[0]->mail();

    expect(app(LoopDetector::class)->isAutomatic($mail))->toBeTrue();
})->with([
    'Auto-Submitted' => [['Auto-Submitted' => 'auto-replied']],
    'X-Auto-Response-Suppress' => [['X-Auto-Response-Suppress' => 'All']],
    'Precedence bulk' => [['Precedence' => 'bulk']],
    'X-Autoreply' => [['X-Autoreply' => 'yes']],
    'newsletter' => [['List-Unsubscribe' => '<mailto:off@news.test>']],
    'bounce' => [['Return-Path' => '<>']],
    'mailer-daemon' => [[], 'Mail delivery', 'MAILER-DAEMON <mailer-daemon@mx.kunde.test>'],
    'noreply' => [[], 'Info', 'Shop <no-reply@shop.test>'],
    'out of office (de)' => [[], 'Abwesenheitsnotiz: Drucker'],
    'out of office (en)' => [[], 'Out of Office: Printer'],
    'automatic reply' => [[], 'Automatische Antwort: Drucker'],
]);

it('treats normal mails and Auto-Submitted: no as manual', function () {
    $this->connector->reply('Frage', headers: ['Auto-Submitted' => 'no']);
    $mail = iterator_to_array($this->connector->fetch($this->mailbox, 1))[0]->mail();

    expect(app(LoopDetector::class)->isAutomatic($mail))->toBeFalse()
        ->and(app(LoopDetector::class)->isAutomatic((new InboundMailParser)->fromFile(base_path('tests/Fixtures/mails/new-from-contact.eml'))))->toBeFalse();
});

it('marks automatic messages and shows them as such', function () {
    $this->connector->reply('Abwesenheitsnotiz: Drucker');
    FetchMailbox::dispatchSync($this->mailbox);

    expect(TicketMessage::sole()->is_automatic)->toBeTrue();
});

it('does not change the status on automatic replies', function () {
    $waiting = Ticket::factory()->create(['status' => TicketStatus::WaitingCustomer]);
    $closed = Ticket::factory()->create(['status' => TicketStatus::Closed, 'closed_at' => now()->subDays(2)]);
    $old = Ticket::factory()->create(['status' => TicketStatus::Closed, 'closed_at' => now()->subMonths(3)]);

    foreach ([$waiting, $closed, $old] as $ticket) {
        $this->connector->reply("Automatische Antwort: [#{$ticket->number}]");
    }
    FetchMailbox::dispatchSync($this->mailbox);

    expect($waiting->refresh()->status)->toBe(TicketStatus::WaitingCustomer)
        ->and($closed->refresh()->status)->toBe(TicketStatus::Closed)
        ->and($old->refresh()->messages)->toHaveCount(1)
        ->and(Ticket::count())->toBe(3); // no follow-up ticket for the old one
});

it('ignores mails from our own mailboxes', function () {
    Mailbox::factory()->create(['email' => 'technik@musterfirma.test']);
    $this->connector->reply('Kopie', from: 'Technik <technik@musterfirma.test>');
    $this->connector->reply('Weitergeleitet', headers: ['X-ServiceDesk-Ticket' => '2026-00001']);

    FetchMailbox::dispatchSync($this->mailbox);

    expect(Ticket::count())->toBe(0)
        ->and($this->connector->processed)->toBe(['Kopie', 'Weitergeleitet']);
});
