<?php

namespace Database\Factories;

use App\Models\Appointment;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Appointment>
 */
class AppointmentFactory extends Factory
{
    public function definition(): array
    {
        $start = now()->addDays(fake()->numberBetween(1, 14))->setTime(fake()->numberBetween(8, 15), 0);

        return [
            'ticket_id' => Ticket::factory(),
            'technician_id' => User::factory(),
            'starts_at' => $start,
            'ends_at' => $start->copy()->addHours(2),
            'location' => fake()->streetAddress()."\n".fake()->postcode().' '.fake()->city(),
        ];
    }
}
