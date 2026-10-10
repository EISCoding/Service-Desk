<?php

use App\Mail\Mailboxes\MailboxMailerFactory;
use App\Models\Mailbox;
use App\Models\Ticket;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

beforeEach(fn () => Storage::fake('local'));

it('imports .eml files into the default mailbox', function () {
    $this->travelTo('2026-10-15 12:00');
    $mailbox = Mailbox::factory()->create(['email' => 'support@musterfirma.test']);

    $this->artisan('servicedesk:import-mail', ['files' => [base_path('tests/Fixtures/mails/new-from-contact.eml')]])
        ->expectsOutputToContain('→ Ticket#')
        ->assertSuccessful();

    expect(Ticket::sole()->mailbox_id)->toBe($mailbox->id);
});

it('fails without mailbox or with a missing file', function () {
    $this->artisan('servicedesk:import-mail', ['files' => ['x.eml']])->assertFailed();

    Mailbox::factory()->create();
    $this->artisan('servicedesk:import-mail', ['files' => ['/nicht/da.eml']])->expectsOutputToContain('Datei nicht gefunden')->assertFailed();
});

it('sends mailbox mails through MAILBOX_MAILER when set', function () {
    config(['servicedesk.mail.mailer' => 'array']);

    expect(app(MailboxMailerFactory::class)->make(Mailbox::factory()->create()))->toBe(Mail::mailer('array'));
});
