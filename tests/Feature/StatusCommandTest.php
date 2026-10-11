<?php

use App\Models\Mailbox;
use App\Models\Ticket;
use App\Models\TicketMessage;

it('explains the state of mail sending', function () {
    config(['mail.default' => 'log', 'servicedesk.mail.mailer' => 'log']);
    Mailbox::factory()->create(['name' => 'Support', 'email' => 'support@firma.de', 'last_error' => 'IMAP: Login failed']);
    $message = TicketMessage::factory()->for(Ticket::factory())->create(['type' => 'mail_out']);
    $message->forceFill(['mail_status' => 'failed', 'mail_error' => 'Connection could not be established with host smtp.firma.de'])->save();

    $this->artisan('servicedesk:status')
        ->expectsOutputToContain('log – Mails werden NICHT verschickt')
        ->expectsOutputToContain('Ticket-Mails gehen NICHT raus')
        ->expectsOutputToContain('IMAP: Login failed')
        ->expectsOutputToContain('Connection could not be established')
        ->assertSuccessful();
});

it('reports a queue that is not being worked off', function () {
    DB::table('jobs')->insert(['queue' => 'default', 'payload' => '{}', 'attempts' => 0, 'available_at' => now()->subMinutes(10)->timestamp, 'created_at' => now()->subMinutes(10)->timestamp]);

    $this->artisan('servicedesk:status')->expectsOutputToContain('der Worker läuft nicht')->assertSuccessful();
});
