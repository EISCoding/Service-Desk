<?php

use App\Models\Ticket;
use App\Models\User;

it('marks list, composer and status for the keyboard shortcuts', function () {
    $ticket = Ticket::factory()->create(['group_id' => null]);

    $this->actingAs(User::factory()->staff()->create())
        ->get("/intern/tickets/{$ticket->id}")
        ->assertOk()
        ->assertSee('aria-label="Tickets"', false)  // J / K
        ->assertSee('data-composer', false)          // R / N
        ->assertSee('data-shortcut="status"', false) // E
        ->assertSee('Tastenkürzel:');
});
