<?php

use App\Enums\MessageType;
use App\Enums\SystemTemplate;
use App\Jobs\SendTicketMessage;
use App\Livewire\Intern\Tickets\Composer;
use App\Mail\Mailboxes\MailboxMailerFactory;
use App\Models\Contact;
use App\Models\Mailbox;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use App\Services\TemplateMailer;
use Database\Seeders\MailTemplateSeeder;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

beforeEach(function () {
    Queue::fake();
    $this->mailbox = Mailbox::factory()->create(['email' => 'support@musterfirma.test']);
    $this->contact = Contact::factory()->create(['email' => 'anna.becker@kunde.test']);
    $this->ticket = Ticket::factory()->create([
        'customer_id' => $this->contact->customer_id, 'contact_id' => $this->contact->id,
        'mailbox_id' => $this->mailbox->id, 'group_id' => null,
    ]);
    $this->actingAs(User::factory()->staff()->create());
});

/**
 * CC addresses of the mails that would go out now.
 *
 * @return list<list<string>>
 */
function sentCc(): array
{
    $mailer = Mail::mailer('array');
    $factory = Mockery::mock(MailboxMailerFactory::class);
    $factory->shouldReceive('make')->andReturn($mailer);

    TicketMessage::where('mail_status', 'queued')->get()->each(fn ($m) => (new SendTicketMessage($m))->handle($factory));

    return array_map(
        fn ($sent) => array_map(fn ($address) => $address->getAddress(), $sent->getOriginalMessage()->getCc()),
        iterator_to_array($mailer->getSymfonyTransport()->messages()),
    );
}

it('sends replies to the CC recipients', function () {
    Livewire::test(Composer::class, ['ticket' => $this->ticket])
        ->set('newCc', 'chef@kunde.test')
        ->call('addCc')
        ->set('body', '<p>Hallo</p>')
        ->call('send');

    expect(sentCc())->toBe([['chef@kunde.test']]);
});

it('keeps an address typed into the CC field without pressing Enter', function () {
    Livewire::test(Composer::class, ['ticket' => $this->ticket])
        ->set('newCc', 'Chef@Kunde.test')
        ->set('body', '<p>Hallo</p>')
        ->call('send')
        ->assertHasNoErrors()
        ->assertSet('newCc', '');

    expect(sentCc())->toBe([['chef@kunde.test']]);
});

it('does not send when the typed CC address is invalid', function () {
    Livewire::test(Composer::class, ['ticket' => $this->ticket])
        ->set('newCc', 'kein-mail')
        ->set('body', '<p>Hallo</p>')
        ->call('send')
        ->assertHasErrors('newCc');

    expect(TicketMessage::where('type', MessageType::MailOut->value)->count())->toBe(0);
});

it('keeps the CC of the last reply for the next one', function () {
    TicketMessage::factory()->for($this->ticket)->create([
        'type' => MessageType::MailOut, 'mail_status' => 'sent', 'author_type' => 'user', 'author_id' => auth()->id(),
        'to' => [['email' => 'anna.becker@kunde.test', 'name' => null]], 'cc' => [['email' => 'chef@kunde.test', 'name' => 'Chef']],
    ]);

    Livewire::test(Composer::class, ['ticket' => $this->ticket])
        ->assertSet('cc', [['email' => 'chef@kunde.test', 'name' => 'Chef']]);
});

it('sends template mails (status, closed) to the CC recipients as well', function () {
    $this->seed(MailTemplateSeeder::class);
    TicketMessage::factory()->for($this->ticket)->create([
        'type' => MessageType::MailIn, 'author_type' => 'contact', 'author_id' => $this->contact->id, 'from_email' => 'anna.becker@kunde.test',
        'to' => [['email' => 'support@musterfirma.test', 'name' => null]], 'cc' => [['email' => 'kollege@kunde.test', 'name' => null]],
    ]);

    app(TemplateMailer::class)->send($this->ticket, SystemTemplate::TicketClosed, auth()->user());

    expect(sentCc())->toBe([['kollege@kunde.test']]);
});
