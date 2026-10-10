<?php

use App\Livewire\Intern\Tickets\Composer;
use App\Models\Contact;
use App\Models\MailTemplate;
use App\Models\Ticket;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->contact = Contact::factory()->create(['salutation' => 'herr', 'last_name' => 'Kraus']);
    $this->ticket = Ticket::factory()->create(['number' => '2026-00042', 'customer_id' => $this->contact->customer_id, 'contact_id' => $this->contact->id, 'group_id' => null]);
    $this->staff = User::factory()->staff()->create(['name' => 'Bert Support']);
    $this->actingAs($this->staff);
});

it('offers the text snippets in the composer', function () {
    MailTemplate::factory()->create(['name' => 'Fernwartung']);
    MailTemplate::factory()->system('ticket_opened')->create(['name' => 'Ticket eröffnet']);

    Livewire::test(Composer::class, ['ticket' => $this->ticket])
        ->assertSee('Textbaustein')
        ->assertSee('Fernwartung')
        ->assertDontSee('Ticket eröffnet');
});

it('hides the snippet menu without snippets', function () {
    Livewire::test(Composer::class, ['ticket' => $this->ticket])->assertDontSee('Textbaustein');
});

it('returns the snippet with filled placeholders', function () {
    $snippet = MailTemplate::factory()->create(['body' => '<div>{kontakt.anrede}, zu Ticket#{ticket.nummer} meldet sich {mitarbeiter.name}.</div>']);

    Livewire::test(Composer::class, ['ticket' => $this->ticket])
        ->call('snippet', $snippet->id)
        ->assertReturned('<div>Sehr geehrter Herr Kraus, zu Ticket#2026-00042 meldet sich Bert Support.</div>');
});

it('does not hand out system templates as snippets', function () {
    $system = MailTemplate::factory()->system('ticket_opened')->create();

    Livewire::test(Composer::class, ['ticket' => $this->ticket])->call('snippet', $system->id)->assertNotFound();
});
