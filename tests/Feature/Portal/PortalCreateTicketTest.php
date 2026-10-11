<?php

use App\Enums\TicketPriority;
use App\Enums\TicketSource;
use App\Enums\TicketStatus;
use App\Livewire\Portal\Tickets\Create;
use App\Models\Contact;
use App\Models\Group;
use App\Models\Mailbox;
use App\Models\Ticket;
use Database\Seeders\MailTemplateSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('local');
    Queue::fake();
    $this->seed(MailTemplateSeeder::class);
    $this->contact = Contact::factory()->withPortalAccess()->create(['password' => 'Kundenportal2026']);
    $this->actingAs($this->contact, 'contact');
});

it('shows the form', function () {
    $this->get('/portal/tickets/neu')->assertOk()
        ->assertSee('<title>Neues Ticket · ServiceDesk</title>', false)
        ->assertSee(['Betreff', 'Priorität', 'Beschreibung', 'Dateien anhängen']);
});

it('creates a ticket with attachments in the group of the default mailbox', function () {
    $group = Group::factory()->create();
    $mailbox = Mailbox::factory()->create(['is_default' => true, 'default_group_id' => $group->id]);

    Livewire::test(Create::class)
        ->set('subject', 'Drucker defekt')
        ->set('priority', 'high')
        ->set('body', '<div>Seit heute Morgen.</div>')
        ->set('files', [UploadedFile::fake()->image('fehler.png')])
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect('/portal/tickets/'.Ticket::sole()->id);

    $ticket = Ticket::sole();
    expect($ticket)
        ->source->toBe(TicketSource::Portal)
        ->status->toBe(TicketStatus::New)
        ->priority->toBe(TicketPriority::High)
        ->customer_id->toBe($this->contact->customer_id)
        ->contact_id->toBe($this->contact->id)
        ->group_id->toBe($group->id)
        ->mailbox_id->toBe($mailbox->id);

    $message = $ticket->messages()->sole();
    expect($message->type->value)->toBe('public')
        ->and($message->author->is($this->contact))->toBeTrue()
        ->and($message->attachments()->sole()->original_name)->toBe('fehler.png')
        ->and($ticket->activities()->first()->causer->is($this->contact))->toBeTrue();

    // No confirmation mail for portal tickets.
    Queue::assertNothingPushed();
});

it('creates the ticket without group when there is no mailbox', function () {
    Livewire::test(Create::class)->set('subject', 'Frage')->set('body', '<div>Text</div>')->call('save')->assertHasNoErrors();

    expect(Ticket::sole())->group_id->toBeNull()->mailbox_id->toBeNull();
});

it('validates subject and description', function () {
    Livewire::test(Create::class)
        ->set('body', '<div><br></div>')
        ->call('save')
        ->assertHasErrors(['subject' => 'required', 'body']);

    expect(Ticket::count())->toBe(0);
});
