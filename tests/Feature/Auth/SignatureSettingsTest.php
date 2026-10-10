<?php

use App\Livewire\Intern\Settings\Signature;
use App\Livewire\Intern\Tickets\Composer;
use App\Models\Contact;
use App\Models\Mailbox;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

beforeEach(function () {
    $this->staff = User::factory()->staff()->create();
    $this->actingAs($this->staff);
});

it('edits the own signature under /intern/einstellungen/signatur', function () {
    $this->get('/intern/einstellungen/signatur')->assertOk()->assertSee('<title>Signatur · ServiceDesk</title>', false);

    Livewire::test(Signature::class)
        ->set('signature', '<div>Viele Grüße<br>Bert<script>alert(1)</script></div>')
        ->call('save')
        ->assertDispatched('toast', message: 'Signatur gespeichert.');

    expect($this->staff->refresh()->signature)->toBe('<div>Viele Grüße<br />Bert</div>');

    Livewire::test(Signature::class)->assertSet('signature', '<div>Viele Grüße<br />Bert</div>')
        ->set('signature', '<div><br></div>')->call('save')
        ->assertDispatched('toast', message: 'Signatur entfernt.');

    expect($this->staff->refresh()->signature)->toBeNull();
});

it('is linked in the user menu', function () {
    $this->get('/intern/tickets')->assertSee(route('intern.settings.signature'));
});

it('appends the signature to public replies but not to notes', function () {
    Queue::fake();
    $this->staff->update(['signature' => '<div>Bert Support</div>']);
    $contact = Contact::factory()->create();
    $ticket = Ticket::factory()->create([
        'customer_id' => $contact->customer_id, 'contact_id' => $contact->id, 'group_id' => null,
        'mailbox_id' => Mailbox::factory()->create()->id,
    ]);

    Livewire::test(Composer::class, ['ticket' => $ticket])
        ->assertSee('Deine Signatur wird automatisch angehängt.')
        ->set('body', '<div>Erledigt.</div>')->call('send');
    Livewire::test(Composer::class, ['ticket' => $ticket])
        ->call('setMode', 'note')->set('body', '<div>Intern.</div>')->call('send');

    expect($ticket->messages()->where('type', 'mail_out')->sole()->body)->toBe('<div>Erledigt.</div><div><br /></div><div>Bert Support</div>')
        ->and($ticket->messages()->where('type', 'internal')->sole()->body)->toBe('<div>Intern.</div>');
});
