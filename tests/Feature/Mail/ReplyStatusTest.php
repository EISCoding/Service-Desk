<?php

use App\Enums\MessageType;
use App\Enums\TicketStatus;
use App\Jobs\FetchMailbox;
use App\Mail\Inbound\MailboxConnector;
use App\Models\Contact;
use App\Models\Group;
use App\Models\Mailbox;
use App\Models\Ticket;
use Illuminate\Support\Facades\Storage;
use Tests\Support\FakeMailboxConnector;

beforeEach(function () {
    $this->travelTo('2026-10-15 12:00');
    Storage::fake('local');
    $this->connector = new FakeMailboxConnector;
    app()->instance(MailboxConnector::class, $this->connector);
    $this->mailbox = Mailbox::factory()->create(['email' => 'support@musterfirma.test']);
});

function replyTo(Ticket $ticket, string $from = 'Kunde <kunde@kunde.test>'): void
{
    test()->connector->reply("AW: {$ticket->subject} [#{$ticket->number}]", from: $from);
    FetchMailbox::dispatchSync(test()->mailbox);
}

describe('closed tickets', function () {
    it('reopens a ticket closed within 14 days', function () {
        $ticket = Ticket::factory()->create(['status' => TicketStatus::Closed, 'closed_at' => now()->subDays(13)]);

        replyTo($ticket);

        expect($ticket->refresh())->status->toBe(TicketStatus::InProgress)->closed_at->toBeNull()
            ->and(Ticket::count())->toBe(1)
            ->and($ticket->messages()->where('type', 'mail_in')->count())->toBe(1);
    });

    it('starts a follow-up ticket after the period, with references both ways', function () {
        $group = Group::factory()->create();
        $previous = Ticket::factory()->create(['status' => TicketStatus::Closed, 'closed_at' => now()->subDays(15), 'group_id' => $group->id]);

        replyTo($previous, from: 'Unbekannt <neu@kunde.test>');

        $new = Ticket::latest('id')->first();
        expect(Ticket::count())->toBe(2)
            ->and($previous->refresh()->status)->toBe(TicketStatus::Closed)
            ->and($new->status)->toBe(TicketStatus::New)
            ->and($new->customer_id)->toBe($previous->customer_id)
            ->and($new->contact_id)->toBe($previous->contact_id)
            ->and($new->group_id)->toBe($group->id)
            ->and($new->messages()->where('type', 'mail_in')->count())->toBe(1)
            ->and($new->messages()->where('type', MessageType::Internal)->sole()->plainText())->toContain($previous->reference())
            ->and($previous->messages()->where('type', MessageType::Internal)->sole()->plainText())->toContain($new->reference());
    });

    it('uses the configured period', function () {
        config(['servicedesk.mail.reopen_days' => 30]);
        $ticket = Ticket::factory()->create(['status' => TicketStatus::Closed, 'closed_at' => now()->subDays(20)]);

        replyTo($ticket);

        expect(Ticket::count())->toBe(1)->and($ticket->refresh()->status)->toBe(TicketStatus::InProgress);
    });

    it('takes the contact of a known sender for the follow-up ticket', function () {
        $contact = Contact::factory()->create(['email' => 'anna@kunde.test']);
        $previous = Ticket::factory()->create(['status' => TicketStatus::Closed, 'closed_at' => now()->subMonths(2)]);

        replyTo($previous, from: 'Anna <anna@kunde.test>');

        expect(Ticket::latest('id')->first())->contact_id->toBe($contact->id)->customer_id->toBe($contact->customer_id);
    });
});

describe('waiting for customer', function () {
    it('moves "Warte auf Kunden" to "In Arbeit"', function () {
        $ticket = Ticket::factory()->create(['status' => TicketStatus::WaitingCustomer]);

        replyTo($ticket);

        expect($ticket->refresh()->status)->toBe(TicketStatus::InProgress);
    });

    it('leaves other statuses alone', function (TicketStatus $status) {
        $ticket = Ticket::factory()->status($status)->create();

        replyTo($ticket);

        expect($ticket->refresh()->status)->toBe($status);
    })->with([TicketStatus::New, TicketStatus::InProgress, TicketStatus::WaitingInternal, TicketStatus::WaitingExternal, TicketStatus::OnSite]);
});
