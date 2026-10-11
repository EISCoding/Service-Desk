<?php

use App\Enums\MessageType;
use App\Models\Contact;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;

it('stores the body sanitized', function () {
    $message = TicketMessage::factory()->create(['body' => '<p>Hallo</p><script>alert(1)</script>']);

    expect($message->refresh()->body)->toBe('<p>Hallo</p>');
});

it('has staff or contacts as author', function () {
    $ticket = Ticket::factory()->create();
    $staff = TicketMessage::factory()->for($ticket)->create(['author_type' => 'user', 'author_id' => User::factory()->create(['name' => 'Anna'])->id]);
    $contact = Contact::factory()->create(['first_name' => 'Kim', 'last_name' => 'Kunde']);
    $fromContact = TicketMessage::factory()->for($ticket)->create(['author_type' => 'contact', 'author_id' => $contact->id]);
    $unknown = TicketMessage::factory()->for($ticket)->create(['author_type' => null, 'author_id' => null, 'type' => MessageType::MailIn]);

    expect($staff->authorName())->toBe('Anna')
        ->and($fromContact->authorName())->toBe('Kim Kunde')
        ->and($unknown->authorName())->toBe('Unbekannt');
});

it('keeps the conversation in chronological order', function () {
    $ticket = Ticket::factory()->create();
    TicketMessage::factory()->for($ticket)->create(['body' => '<p>zweite</p>', 'created_at' => now()]);
    TicketMessage::factory()->for($ticket)->create(['body' => '<p>erste</p>', 'created_at' => now()->subHour()]);

    expect($ticket->messages->map->plainText()->all())->toBe(['erste', 'zweite']);
});

it('never shows internal notes to customers', function () {
    expect(MessageType::Internal->isVisibleToCustomer())->toBeFalse()
        ->and(MessageType::visibleToCustomer())->toBe([MessageType::Public, MessageType::MailIn, MessageType::MailOut])
        ->and(TicketMessage::factory()->internal()->make()->isInternal())->toBeTrue();
});

it('stores mail threading headers', function () {
    $message = TicketMessage::factory()->create(['type' => MessageType::MailIn, 'message_id' => '<abc@mail.example>', 'in_reply_to' => '<xyz@mail.example>']);

    expect($message->refresh())->message_id->toBe('<abc@mail.example>')->in_reply_to->toBe('<xyz@mail.example>');
});

it('is deleted with its ticket', function () {
    $message = TicketMessage::factory()->create();

    $message->ticket->delete();

    expect(TicketMessage::find($message->id))->toBeNull();
});
