<?php

use App\Enums\MessageType;
use App\Livewire\Portal\Tickets\Show;
use App\Models\Attachment;
use App\Models\Contact;
use App\Models\Customer;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use App\Services\AttachmentStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('local');
    $this->customer = Customer::factory()->create();
    $this->anna = Contact::factory()->for($this->customer)->withPortalAccess()->create(['password' => 'Kundenportal2026']);
    $this->ticket = Ticket::factory()->create(['subject' => 'Drucker defekt', 'customer_id' => $this->customer->id, 'contact_id' => $this->anna->id]);
    $this->staff = User::factory()->create(['name' => 'Bert Support']);
    $this->actingAs($this->anna, 'contact');
});

function portalMessage(Ticket $ticket, MessageType $type, string $body, $author = null): TicketMessage
{
    $message = new TicketMessage(['type' => $type, 'body' => "<div>{$body}</div>"]);
    $message->ticket()->associate($ticket);
    $message->author()->associate($author);
    $message->save();

    return $message;
}

function portalAttachment(TicketMessage $message, string $name = 'rechnung.pdf'): Attachment
{
    return app(AttachmentStorage::class)->storeUpload(UploadedFile::fake()->create($name, 50, 'application/pdf'), $message);
}

it('shows public messages and mails but never internal notes', function () {
    portalMessage($this->ticket, MessageType::Public, 'Bitte helfen Sie mir.', $this->anna);
    portalMessage($this->ticket, MessageType::MailOut, 'Wir kümmern uns darum.', $this->staff);
    portalMessage($this->ticket, MessageType::MailIn, 'Danke für die schnelle Antwort.');
    $note = portalMessage($this->ticket, MessageType::Internal, 'Kunde ist schwierig, Rabatt ablehnen.', $this->staff);
    portalAttachment($note, 'intern-kalkulation.pdf');

    $this->get("/portal/tickets/{$this->ticket->id}")
        ->assertOk()
        ->assertSee('<title>'.$this->ticket->reference().' · ServiceDesk</title>', false)
        ->assertSeeInOrder(['Drucker defekt', 'Bitte helfen Sie mir.', 'Bert Support', 'Wir kümmern uns darum.', 'Danke für die schnelle Antwort.'])
        ->assertDontSee('Kunde ist schwierig')
        ->assertDontSee('intern-kalkulation.pdf');
});

it('hides tickets of colleagues and other customers', function () {
    $colleague = Ticket::factory()->create(['customer_id' => $this->customer->id, 'contact_id' => Contact::factory()->for($this->customer)->create()->id]);
    $foreign = Ticket::factory()->create();

    $this->get("/portal/tickets/{$colleague->id}")->assertNotFound();
    $this->get("/portal/tickets/{$foreign->id}")->assertNotFound();

    $this->anna->update(['is_primary' => true]);
    $this->get("/portal/tickets/{$colleague->id}")->assertOk();
    $this->get("/portal/tickets/{$foreign->id}")->assertNotFound();
});

it('leads from a merged ticket to its target', function () {
    $merged = Ticket::factory()->create(['customer_id' => $this->customer->id, 'contact_id' => $this->anna->id, 'merged_into_id' => $this->ticket->id]);

    Livewire::test(Show::class, ['ticket' => $merged])->assertRedirect("/portal/tickets/{$this->ticket->id}");
});

describe('attachments', function () {
    it('serves attachments of public messages through the portal', function () {
        $attachment = portalAttachment(portalMessage($this->ticket, MessageType::Public, 'Anbei', $this->anna));

        $this->get("/portal/tickets/{$this->ticket->id}")->assertSee($attachment->url(portal: true), false);
        $this->get($attachment->url(portal: true))->assertOk()->assertDownload('rechnung.pdf');
    });

    it('never serves attachments of internal notes or foreign tickets', function () {
        $internal = portalAttachment(portalMessage($this->ticket, MessageType::Internal, 'Intern', $this->staff));
        $foreign = portalAttachment(portalMessage(Ticket::factory()->create(), MessageType::Public, 'Fremd'));

        $this->get($internal->url(portal: true))->assertForbidden();
        $this->get($foreign->url(portal: true))->assertForbidden();
        $this->get($internal->url())->assertRedirect('/intern/login');
    });

    it('points inline images to the portal', function () {
        $message = portalMessage($this->ticket, MessageType::MailIn, '<img src="cid:bild@kunde">');
        $image = app(AttachmentStorage::class)->storeUpload(UploadedFile::fake()->image('bild.png'), $message);
        $image->forceFill(['content_id' => 'bild@kunde'])->save();

        expect($message->refresh()->displayBody(portal: true))->toContain(e($image->url(inline: true, portal: true)));
    });
});
