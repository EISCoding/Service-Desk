<?php

use App\Enums\Salutation;
use App\Enums\TicketStatus;
use App\Models\Appointment;
use App\Models\Contact;
use App\Models\Customer;
use App\Models\Ticket;
use App\Models\User;
use App\Support\Placeholders;

function placeholderTicket(): Ticket
{
    $customer = Customer::factory()->create(['company' => 'Müller & Söhne GmbH']);
    $contact = Contact::factory()->for($customer)->create([
        'salutation' => Salutation::Ms, 'first_name' => 'Anna', 'last_name' => 'Berg',
    ]);

    return Ticket::factory()->status(TicketStatus::InProgress)->create([
        'number' => '2026-00042',
        'subject' => 'Drucker <defekt>',
        'customer_id' => $customer->id,
        'contact_id' => $contact->id,
        'assignee_id' => User::factory()->create(['name' => 'Max Technik'])->id,
    ]);
}

it('fills all placeholders', function () {
    $ticket = placeholderTicket();
    $appointment = Appointment::factory()->for($ticket)->create([
        'starts_at' => '2026-10-15 09:00', 'ends_at' => '2026-10-15 10:30',
    ]);

    $text = (new Placeholders($ticket, appointment: $appointment))->text(
        '{ticket.nummer}|{ticket.betreff}|{ticket.status}|{kontakt.anrede}|{kontakt.name}|{kunde.firma}|'
        .'{termin.datum}|{termin.zeit}|{mitarbeiter.name}|{portal.link}'
    );

    expect(explode('|', $text))->toBe([
        '2026-00042', 'Drucker <defekt>', 'In Arbeit', 'Sehr geehrte Frau Berg', 'Anna Berg', 'Müller & Söhne GmbH',
        'Donnerstag, 15. Oktober 2026', '09:00–10:30 Uhr', 'Max Technik', url('/portal'),
    ]);
});

it('escapes values in HTML and keeps unknown placeholders', function () {
    $html = (new Placeholders(placeholderTicket()))->html('<div>{ticket.betreff} bei { kunde.firma } – {foo.bar}</div>');

    expect($html)->toBe('<div>Drucker &lt;defekt&gt; bei Müller &amp; Söhne GmbH – {foo.bar}</div>');
});

it('prefers the given staff member over the assignee', function () {
    $placeholders = new Placeholders(placeholderTicket(), staff: User::factory()->create(['name' => 'Eva Support']));

    expect($placeholders->text('{mitarbeiter.name}'))->toBe('Eva Support');
});

it('falls back to the requester without a known contact', function () {
    $ticket = Ticket::factory()->withoutCustomer()->create(['requester_name' => 'Jana Neu', 'requester_email' => 'jana@example.org']);

    expect((new Placeholders($ticket))->text('{kontakt.anrede}, {kontakt.name}{kunde.firma}{termin.datum}{mitarbeiter.name}'))
        ->toBe('Guten Tag Jana Neu, Jana Neu');
});

it('works for a contact without a ticket (portal invitation)', function () {
    $contact = Contact::factory()->create(['salutation' => Salutation::Mr, 'first_name' => 'Tom', 'last_name' => 'Kraus']);

    expect((new Placeholders(contact: $contact))->text('{kontakt.anrede} ({kunde.firma}){ticket.nummer}'))
        ->toBe("Sehr geehrter Herr Kraus ({$contact->customer->company})");
});
