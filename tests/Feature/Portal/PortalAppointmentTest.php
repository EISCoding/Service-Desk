<?php

use App\Enums\TicketStatus;
use App\Models\Appointment;
use App\Models\Contact;
use App\Models\Ticket;
use App\Models\User;

beforeEach(function () {
    $this->travelTo('2026-10-12 08:00');
    $this->contact = Contact::factory()->withPortalAccess()->create(['password' => 'Kundenportal2026']);
    $this->ticket = Ticket::factory()->status(TicketStatus::OnSite)->create(['customer_id' => $this->contact->customer_id, 'contact_id' => $this->contact->id]);
    $this->actingAs($this->contact, 'contact');
});

it('shows upcoming on-site appointments without the internal note', function () {
    Appointment::factory()->for($this->ticket)->create([
        'starts_at' => '2026-10-15 09:00', 'ends_at' => '2026-10-15 10:30',
        'technician_id' => User::factory()->create(['name' => 'Tina Technik'])->id,
        'location' => "Müller GmbH\nHauptstraße 1", 'note' => 'Schlüssel beim Hausmeister',
    ]);
    Appointment::factory()->for($this->ticket)->create(['starts_at' => '2026-10-01 09:00', 'ends_at' => '2026-10-01 10:00', 'location' => 'Alter Termin']);

    $this->get("/portal/tickets/{$this->ticket->id}")
        ->assertOk()
        ->assertSee(['Vor-Ort-Termin', 'Donnerstag, 15. Oktober 2026', '09:00–10:30 Uhr', 'Tina Technik', 'Hauptstraße 1'])
        ->assertDontSee('Schlüssel beim Hausmeister')
        ->assertDontSee('Alter Termin');

    $this->get('/portal')->assertSee('Vor-Ort-Termin Do, 15. Okt 2026, 09:00–10:30');
});
