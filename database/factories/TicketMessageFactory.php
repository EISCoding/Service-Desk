<?php

namespace Database\Factories;

use App\Enums\MessageType;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TicketMessage>
 */
class TicketMessageFactory extends Factory
{
    public function definition(): array
    {
        return [
            'ticket_id' => Ticket::factory(),
            'type' => MessageType::Public,
            'body' => '<p>'.fake()->paragraph().'</p>',
            'author_type' => 'user',
            'author_id' => User::factory(),
        ];
    }

    public function internal(): static
    {
        return $this->state(fn () => ['type' => MessageType::Internal]);
    }
}
