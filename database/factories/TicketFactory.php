<?php

namespace Database\Factories;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Contact;
use App\Models\Customer;
use App\Models\Ticket;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Ticket>
 */
class TicketFactory extends Factory
{
    public function definition(): array
    {
        return [
            'subject' => fake()->randomElement([
                'Drucker im 2. OG druckt nicht', 'VPN-Verbindung bricht ab', 'Neuer Arbeitsplatz für Azubi',
                'Outlook startet nicht', 'Server-Wartung planen', 'Lizenz verlängern', 'WLAN im Lager langsam',
                'Passwort zurücksetzen', 'Backup-Fehler gemeldet', 'Telefonanlage: Weiterleitung einrichten',
            ]),
            'status' => TicketStatus::New,
            'priority' => TicketPriority::Normal,
            'customer_id' => Customer::factory(),
        ];
    }

    public function configure(): static
    {
        // Contact of the ticket's customer, unless one was given.
        return $this->afterMaking(function (Ticket $ticket) {
            if ($ticket->customer_id && ! $ticket->contact_id && fake()->boolean(80)) {
                $ticket->contact_id = Contact::factory()->create(['customer_id' => $ticket->customer_id])->id;
            }
        });
    }

    public function status(TicketStatus $status): static
    {
        return $this->state(fn () => [
            'status' => $status,
            'closed_at' => $status === TicketStatus::Closed ? now() : null,
            'follow_up_at' => $status->requiresFollowUp() ? now()->addDays(2) : null,
        ]);
    }

    public function withoutCustomer(): static
    {
        return $this->state(fn () => ['customer_id' => null, 'contact_id' => null]);
    }
}
