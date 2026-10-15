<?php

use App\Models\Contact;
use App\Models\Mailbox;
use App\Notifications\Portal\ContactInvitation;
use Database\Seeders\MailTemplateSeeder;
use Illuminate\Support\Facades\Mail;

beforeEach(fn () => $this->seed(MailTemplateSeeder::class));

it('sends portal mails through the SMTP of the default mailbox', function () {
    Mailbox::factory()->create(['is_active' => true, 'is_default' => true, 'name' => 'Support DaAdre', 'email' => 'support@daadre.de', 'smtp_host' => 'smtp.daadre.de']);
    $contact = Contact::factory()->withPortalAccess()->create();

    $mail = (new ContactInvitation('token'))->toMail($contact);

    expect($mail->mailer)->toBe('mailbox')
        ->and($mail->from)->toBe(['support@daadre.de', 'Support DaAdre'])
        ->and(config('mail.mailers.mailbox.host'))->toBe('smtp.daadre.de');
});

it('really delivers the invitation (with MAILBOX_MAILER for local tests)', function () {
    config(['servicedesk.mail.mailer' => 'array']);
    Mailbox::factory()->create(['is_active' => true, 'is_default' => true, 'name' => 'Support DaAdre', 'email' => 'support@daadre.de']);
    $contact = Contact::factory()->withPortalAccess()->create(['email' => 'kunde@example.org']);

    $contact->notifyNow(new ContactInvitation('token'));

    $sent = collect(Mail::mailer('array')->getSymfonyTransport()->messages())->sole()->getOriginalMessage();
    expect($sent->getFrom()[0]->getAddress())->toBe('support@daadre.de')
        ->and($sent->getTo()[0]->getAddress())->toBe('kunde@example.org')
        ->and($sent->getSubject())->toBe('Ihr Zugang zum Kundenportal');
});

it('falls back to MAIL_* without an active mailbox', function () {
    $mail = (new ContactInvitation('token'))->toMail(Contact::factory()->withPortalAccess()->create());

    expect($mail->mailer)->toBeNull()->and($mail->from)->toBe([]);
});
