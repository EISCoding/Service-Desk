<?php

use App\Jobs\FetchMailbox;
use App\Livewire\Intern\Tickets\TicketView;
use App\Mail\Inbound\MailboxConnector;
use App\Models\Mailbox;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Support\FakeMailboxConnector;

beforeEach(function () {
    $this->travelTo('2026-10-15 12:00');
    Storage::fake('local');
    $this->connector = new FakeMailboxConnector;
    app()->instance(MailboxConnector::class, $this->connector);
    $this->mailbox = Mailbox::factory()->create(['email' => 'support@musterfirma.test']);
    $this->actingAs(User::factory()->admin()->create());
});

it('stores attachments privately and shows inline images through the attachment controller', function () {
    $this->connector->add('new-from-contact');
    FetchMailbox::dispatchSync($this->mailbox);

    $message = Ticket::sole()->messages->sole();
    [$logo, $pdf] = $message->attachments;

    Storage::disk('local')->assertExists($logo->path);
    expect($message->body)->toContain('cid:logo@kunde.test')->not->toContain('tracker.example')
        ->and($message->displayBody())->toContain($logo->url(inline: true))->not->toContain('cid:')
        ->and($message->listedAttachments()->pluck('original_name')->all())->toBe(['fehlerbericht.pdf']);

    Livewire::test(TicketView::class, ['ticket' => $message->ticket])
        ->assertSee($logo->url(inline: true), false)
        ->assertSee('fehlerbericht.pdf');
});

it('collapses the quoted history of mail replies', function () {
    $ticket = Ticket::factory()->create();
    $this->connector->reply("AW: [#{$ticket->number}]", body: 'Jetzt geht es wieder.');
    FetchMailbox::dispatchSync($this->mailbox);

    Livewire::test(TicketView::class, ['ticket' => $ticket])
        ->assertSeeInOrder(['Jetzt geht es wieder.', 'Zitierten Verlauf anzeigen', 'Alte Nachricht']);
});
