<?php

use App\Enums\MessageType;
use App\Enums\TicketStatus;
use App\Livewire\Portal\Tickets\Create;
use App\Livewire\Portal\Tickets\Show;
use App\Models\Contact;
use App\Models\Ticket;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('local');
    $this->contact = Contact::factory()->withPortalAccess()->create(['password' => 'Kundenportal2026']);
    $this->actingAs($this->contact, 'contact');
});

function portalTicket(TicketStatus $status = TicketStatus::WaitingCustomer, array $attributes = []): Ticket
{
    $contact = test()->contact;

    return Ticket::factory()->status($status)->create(['subject' => 'Drucker defekt', 'customer_id' => $contact->customer_id, 'contact_id' => $contact->id, ...$attributes]);
}

it('adds the reply as public message and puts "Warte auf Kunden" back to "In Arbeit"', function () {
    $ticket = portalTicket();

    Livewire::test(Show::class, ['ticket' => $ticket])
        ->set('body', '<div>Der Drucker läuft wieder nicht.</div>')
        ->set('files', [UploadedFile::fake()->image('foto.jpg')])
        ->call('reply')
        ->assertHasNoErrors()
        ->assertDispatched('toast', message: 'Ihre Antwort wurde gesendet.')
        ->assertSet('body', '')
        ->assertSee('Der Drucker läuft wieder nicht.');

    $message = $ticket->messages()->sole();
    expect($ticket->refresh()->status)->toBe(TicketStatus::InProgress)
        ->and($message->type)->toBe(MessageType::Public)
        ->and($message->author->is($this->contact))->toBeTrue()
        ->and($message->attachments()->count())->toBe(1)
        ->and($ticket->activities()->where('action', 'reply_added')->sole()->causer->is($this->contact))->toBeTrue();
});

it('keeps other statuses', function () {
    $ticket = portalTicket(TicketStatus::WaitingExternal, ['follow_up_at' => today()->addDay()]);

    Livewire::test(Show::class, ['ticket' => $ticket])->set('body', '<div>Gibt es Neuigkeiten?</div>')->call('reply');

    expect($ticket->refresh()->status)->toBe(TicketStatus::WaitingExternal);
});

it('requires a message', function () {
    Livewire::test(Show::class, ['ticket' => portalTicket()])->set('body', '<div><br></div>')->call('reply')->assertHasErrors('body');
});

it('reopens tickets closed within the reopen period', function () {
    $ticket = portalTicket(TicketStatus::Closed, ['closed_at' => now()->subDays(3)]);

    Livewire::test(Show::class, ['ticket' => $ticket])
        ->assertSee('Mit Ihrer Antwort wird es wieder geöffnet.')
        ->set('body', '<div>Leider doch nicht gelöst.</div>')
        ->call('reply');

    expect($ticket->refresh())->status->toBe(TicketStatus::InProgress)->closed_at->toBeNull();
});

it('offers a new ticket instead of a reply after the reopen period', function () {
    $ticket = portalTicket(TicketStatus::Closed, ['closed_at' => now()->subDays(20)]);

    Livewire::test(Show::class, ['ticket' => $ticket])
        ->assertDontSee('Antwort senden')
        ->assertSee('Neues Ticket dazu')
        ->set('body', '<div>Hallo?</div>')
        ->call('reply')
        ->assertForbidden();

    Livewire::withQueryParams(['bezug' => $ticket->id])->test(Create::class)
        ->assertSet('subject', 'Drucker defekt')
        ->assertSee("Neues Ticket zu {$ticket->reference()}")
        ->set('body', '<div>Wieder kaputt.</div>')
        ->call('save')
        ->assertHasNoErrors();

    $followUp = Ticket::latest('id')->first();
    expect($followUp->messages()->where('type', 'internal')->sole()->plainText())->toContain("Folgeticket zu {$ticket->reference()}")
        ->and($ticket->messages()->where('type', 'internal')->sole()->plainText())->toContain("Weiter in {$followUp->reference()}");
});

it('ignores references to tickets the contact may not see', function () {
    $foreign = Ticket::factory()->create(['subject' => 'Geheim']);

    Livewire::withQueryParams(['bezug' => $foreign->id])->test(Create::class)
        ->assertSet('subject', '')
        ->assertDontSee('Geheim');
});
