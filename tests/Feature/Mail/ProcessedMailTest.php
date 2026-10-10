<?php

use App\Mail\Inbound\ImapFetchedMail;
use App\Mail\Inbound\InboundMailParser;
use App\Models\Mailbox;
use Webklex\PHPIMAP\Message;

function fetchedMail(Mailbox $mailbox, Message $message): ImapFetchedMail
{
    return new ImapFetchedMail($mailbox, $message, (new InboundMailParser)->fromFile(base_path('tests/Fixtures/mails/new-from-contact.eml')));
}

it('marks processed mails as read by default', function () {
    $message = Mockery::mock(Message::class);
    $message->shouldReceive('setFlag')->once()->with('Seen');
    $message->shouldNotReceive('move');

    fetchedMail(Mailbox::factory()->make(), $message)->markProcessed();
});

it('moves processed mails into the configured folder', function () {
    $message = Mockery::mock(Message::class);
    $message->shouldReceive('move')->once()->with('INBOX.Erledigt');
    $message->shouldNotReceive('setFlag');

    fetchedMail(Mailbox::factory()->make(['processed_action' => 'move', 'processed_folder' => 'INBOX.Erledigt']), $message)->markProcessed();
});

it('falls back to marking as read when no folder is set', function () {
    $message = Mockery::mock(Message::class);
    $message->shouldReceive('setFlag')->once()->with('Seen');

    fetchedMail(Mailbox::factory()->make(['processed_action' => 'move', 'processed_folder' => null]), $message)->markProcessed();
});
