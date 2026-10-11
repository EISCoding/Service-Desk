<?php

use App\Enums\MessageType;
use App\Enums\TicketStatus;
use App\Livewire\Portal\Tickets\Index;
use App\Livewire\Portal\Tickets\Show;
use App\Models\Appointment;
use App\Models\Contact;
use App\Models\Customer;
use App\Models\CustomerNote;
use App\Models\Group;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use App\Services\AttachmentStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

/*
| A contact must never see tickets of other customers or (unless main
| contact) of colleagues, and never internal content of any ticket.
*/

beforeEach(function () {
    Storage::fake('local');
    $this->customer = Customer::factory()->create();
    $this->contact = Contact::factory()->for($this->customer)->withPortalAccess()->create(['password' => 'Kundenportal2026']);
    $this->staff = User::factory()->create(['name' => 'Interner Bearbeiter']);

    $this->own = Ticket::factory()->status(TicketStatus::WaitingInternal)->create([
        'subject' => 'Eigenes Ticket', 'customer_id' => $this->customer->id, 'contact_id' => $this->contact->id,
        'group_id' => Group::factory()->create(['name' => 'Geheime Gruppe'])->id, 'assignee_id' => $this->staff->id,
        'follow_up_at' => today()->addDay(),
    ]);
    $this->colleague = Ticket::factory()->create(['subject' => 'Ticket des Kollegen', 'customer_id' => $this->customer->id, 'contact_id' => Contact::factory()->for($this->customer)->create()->id]);
    $this->foreign = Ticket::factory()->create(['subject' => 'Ticket einer anderen Firma']);

    $this->actingAs($this->contact, 'contact');
});

function isolationMessage(Ticket $ticket, MessageType $type, string $text): TicketMessage
{
    $message = new TicketMessage(['type' => $type, 'body' => "<p>{$text}</p>"]);
    $message->ticket()->associate($ticket);
    $message->author()->associate(test()->staff);
    $message->save();

    return $message;
}

it('never lists or opens tickets of other customers or colleagues', function () {
    $this->get('/portal?state=all')->assertOk()
        ->assertSee('Eigenes Ticket')
        ->assertDontSee('Ticket des Kollegen')
        ->assertDontSee('Ticket einer anderen Firma');

    Livewire::test(Index::class)->set('search', $this->foreign->number)->assertDontSee('Ticket einer anderen Firma');

    $this->get("/portal/tickets/{$this->colleague->id}")->assertNotFound();
    $this->get("/portal/tickets/{$this->foreign->id}")->assertNotFound();
});

it('keeps main contacts inside their own company', function () {
    $this->contact->update(['is_primary' => true]);

    $this->get('/portal?state=all')->assertSee('Ticket des Kollegen')->assertDontSee('Ticket einer anderen Firma');
    $this->get("/portal/tickets/{$this->foreign->id}")->assertNotFound();
});

it('rejects swapping the ticket of the view', function () {
    Livewire::test(Show::class, ['ticket' => $this->own])->set('ticket', $this->foreign);
})->throws(CannotUpdateLockedPropertyException::class);

it('never shows internal notes, customer notes or internal ticket data', function () {
    isolationMessage($this->own, MessageType::Public, 'Öffentliche Antwort');
    $note = isolationMessage($this->own, MessageType::Internal, 'Interne Notiz zum Kunden');
    app(AttachmentStorage::class)->storeUpload(UploadedFile::fake()->create('intern.pdf', 10), $note);
    CustomerNote::factory()->for($this->customer)->create(['body' => 'Kundennotiz: zahlt schlecht']);
    Appointment::factory()->for($this->own)->create(['note' => 'Notiz für den Techniker', 'starts_at' => now()->addDay(), 'ends_at' => now()->addDay()->addHour()]);
    $this->own->logActivity('note_added');

    $page = $this->get("/portal/tickets/{$this->own->id}")->assertOk()->assertSee('Öffentliche Antwort');

    foreach (['Interne Notiz zum Kunden', 'intern.pdf', 'Kundennotiz', 'Notiz für den Techniker', 'Geheime Gruppe', 'Warte auf Intern', 'Wiedervorlage', 'Interne Notiz'] as $secret) {
        $page->assertDontSee($secret);
    }

    $this->get('/portal')->assertDontSee('Geheime Gruppe')->assertDontSee('Warte auf Intern');
});

it('does not serve attachments outside the own public messages', function () {
    $internal = app(AttachmentStorage::class)->storeUpload(UploadedFile::fake()->create('intern.pdf', 10), isolationMessage($this->own, MessageType::Internal, 'Intern'));
    $colleague = app(AttachmentStorage::class)->storeUpload(UploadedFile::fake()->create('kollege.pdf', 10), isolationMessage($this->colleague, MessageType::Public, 'Kollege'));
    $foreign = app(AttachmentStorage::class)->storeUpload(UploadedFile::fake()->create('fremd.pdf', 10), isolationMessage($this->foreign, MessageType::MailOut, 'Fremd'));

    foreach ([$internal, $colleague, $foreign] as $attachment) {
        $this->get(route('portal.attachments.show', $attachment))->assertForbidden();
    }
});

it('does not let contacts open or answer foreign tickets', function () {
    Livewire::test(Show::class, ['ticket' => $this->colleague])->assertNotFound();
    Livewire::test(Show::class, ['ticket' => $this->foreign])->assertNotFound();

    expect($this->foreign->messages()->count())->toBe(0);
});

it('keeps contacts out of the staff area', function () {
    foreach (['/intern/tickets', "/intern/tickets/{$this->own->id}", '/intern/kunden', '/intern/admin'] as $path) {
        $this->get($path)->assertRedirect('/intern/login');
    }
});
