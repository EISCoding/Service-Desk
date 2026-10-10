<?php

use App\Enums\MessageType;
use App\Jobs\FetchMailbox;
use App\Jobs\SendTicketMessage;
use App\Livewire\Intern\Tickets\Composer;
use App\Livewire\Intern\Tickets\TicketView;
use App\Mail\Inbound\MailboxConnector;
use App\Mail\Mailboxes\MailboxMailerFactory;
use App\Models\Contact;
use App\Models\Mailbox;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Symfony\Component\Mime\Email;
use Tests\Support\FakeMailboxConnector;

beforeEach(function () {
    // No travelTo() here: Livewire's fake uploads lose their file names with a shifted test clock.
    Storage::fake('local');
    $this->mailbox = Mailbox::factory()->create(['name' => 'Support Musterfirma', 'email' => 'support@musterfirma.test']);
    $this->contact = Contact::factory()->create(['email' => 'anna.becker@kunde.test', 'first_name' => 'Anna', 'last_name' => 'Becker']);
    $this->ticket = Ticket::factory()->create([
        'subject' => 'Drucker defekt', 'customer_id' => $this->contact->customer_id, 'contact_id' => $this->contact->id,
        'mailbox_id' => $this->mailbox->id, 'group_id' => null,
    ]);
    $this->staff = User::factory()->staff()->create(['name' => 'Bert Support']);
    $this->actingAs($this->staff);
});

/**
 * Sends queued ticket mails through the array transport and returns the Symfony messages.
 *
 * @return list<Email>
 */
function sendQueuedMails(): array
{
    $mailer = Mail::mailer('array');
    $factory = Mockery::mock(MailboxMailerFactory::class);
    $factory->shouldReceive('make')->andReturn($mailer);
    app()->instance(MailboxMailerFactory::class, $factory);

    TicketMessage::where('mail_status', 'queued')->get()->each(fn ($m) => (new SendTicketMessage($m))->handle($factory));

    return array_map(fn ($sent) => $sent->getOriginalMessage(), iterator_to_array($mailer->getSymfonyTransport()->messages()));
}

it('turns a public reply into a queued mail with threading information', function () {
    Queue::fake();
    TicketMessage::factory()->for($this->ticket)->create(['type' => MessageType::MailIn, 'message_id' => 'kunde-1@kunde.test', 'author_type' => 'contact', 'author_id' => $this->contact->id]);

    Livewire::test(Composer::class, ['ticket' => $this->ticket])
        ->assertSee('anna.becker@kunde.test')
        ->set('body', '<div>Wir kommen morgen vorbei.</div>')
        ->call('send')
        ->assertDispatched('toast', message: 'Antwort wird per E-Mail gesendet.');

    $message = $this->ticket->messages()->where('type', 'mail_out')->sole();
    expect($message)
        ->mail_status->toBe('queued')
        ->in_reply_to->toBe('kunde-1@kunde.test')
        ->and($message->to)->toBe([['email' => 'anna.becker@kunde.test', 'name' => 'Anna Becker']])
        ->and($message->message_id)->toStartWith("sd.{$this->ticket->number}.")->toEndWith('@musterfirma.test')
        ->and($message->author->is($this->staff))->toBeTrue();

    Queue::assertPushed(SendTicketMessage::class, fn ($job) => $job->message->is($message));
});

it('sends through the mailbox with ticket tag, Message-ID, References and attachments', function () {
    Queue::fake();
    TicketMessage::factory()->for($this->ticket)->create(['type' => MessageType::MailIn, 'message_id' => 'kunde-1@kunde.test']);

    Livewire::test(Composer::class, ['ticket' => $this->ticket])
        ->set('body', '<div>Anbei die <strong>Anleitung</strong>.</div>')
        ->set('files', [UploadedFile::fake()->create('anleitung.pdf', 20, 'application/pdf')])
        ->call('send');

    [$email] = sendQueuedMails();
    $message = $this->ticket->messages()->where('type', 'mail_out')->sole();

    expect($email->getFrom()[0]->getAddress())->toBe('support@musterfirma.test')
        ->and($email->getFrom()[0]->getName())->toBe('Support Musterfirma')
        ->and($email->getTo()[0]->getAddress())->toBe('anna.becker@kunde.test')
        ->and($email->getSubject())->toBe("Drucker defekt [#{$this->ticket->number}]")
        ->and($email->getHeaders()->get('Message-ID')->getBodyAsString())->toBe("<{$message->message_id}>")
        ->and($email->getHeaders()->get('In-Reply-To')->getBodyAsString())->toBe('<kunde-1@kunde.test>')
        ->and($email->getHeaders()->get('References')->getBodyAsString())->toContain('<kunde-1@kunde.test>')
        ->and($email->getHeaders()->get('X-ServiceDesk-Ticket')->getBodyAsString())->toBe($this->ticket->number)
        ->and($email->getHeaders()->has('Auto-Submitted'))->toBeFalse()
        ->and($email->getHtmlBody())->toContain('<strong>Anleitung</strong>')->toContain($this->ticket->reference())
        ->and($email->getTextBody())->toContain('Anbei die Anleitung.')
        ->and($email->getAttachments()[0]->getFilename())->toBe('anleitung.pdf')
        ->and($message->refresh()->mail_status)->toBe('sent')
        ->and($message->sent_at)->not->toBeNull();
});

it('suggests the CC recipients of the last customer mail without our own addresses', function () {
    TicketMessage::factory()->for($this->ticket)->create([
        'type' => MessageType::MailIn, 'from_email' => 'anna.becker@kunde.test',
        'to' => [['email' => 'support@musterfirma.test', 'name' => null], ['email' => 'it@kunde.test', 'name' => 'IT']],
        'cc' => [['email' => 'chef@kunde.test', 'name' => 'Chef'], ['email' => 'support@musterfirma.test', 'name' => null]],
    ]);

    $composer = Livewire::test(Composer::class, ['ticket' => $this->ticket])
        ->assertSet('cc', [['email' => 'it@kunde.test', 'name' => 'IT'], ['email' => 'chef@kunde.test', 'name' => 'Chef']])
        ->call('removeCc', 0)
        ->set('newCc', 'Neu@Kunde.test')
        ->call('addCc')
        ->assertSet('cc', [['email' => 'chef@kunde.test', 'name' => 'Chef'], ['email' => 'neu@kunde.test', 'name' => null]])
        ->set('newCc', 'kein-mail')
        ->call('addCc')
        ->assertHasErrors('newCc');

    Queue::fake();
    $composer->set('body', '<div>x</div>')->call('send');

    expect($this->ticket->messages()->where('type', 'mail_out')->sole()->cc)
        ->toBe([['email' => 'chef@kunde.test', 'name' => 'Chef'], ['email' => 'neu@kunde.test', 'name' => null]]);
});

it('only stores the reply when there is no recipient', function () {
    $this->ticket->forceFill(['contact_id' => null, 'requester_email' => null])->save();

    Livewire::test(Composer::class, ['ticket' => $this->ticket])
        ->assertSee('Keine E-Mail-Adresse')
        ->set('body', '<div>x</div>')
        ->call('send');

    expect($this->ticket->messages()->sole()->type)->toBe(MessageType::Public);
});

it('marks failed mails and sends them again on request', function () {
    Queue::fake();
    Livewire::test(Composer::class, ['ticket' => $this->ticket])->set('body', '<div>x</div>')->call('send');
    $message = $this->ticket->messages()->sole();

    (new SendTicketMessage($message))->failed(new RuntimeException('550 Mailbox unavailable'));
    expect($message->refresh())->mail_status->toBe('failed')->mail_error->toBe('550 Mailbox unavailable');

    Livewire::test(TicketView::class, ['ticket' => $this->ticket])
        ->assertSee('Versand fehlgeschlagen: 550 Mailbox unavailable')
        ->call('retryMail', $message->id)
        ->assertDispatched('toast', message: 'E-Mail wird erneut gesendet.');

    expect($message->refresh()->mail_status)->toBe('queued');
    Queue::assertPushed(SendTicketMessage::class, 2);
});

it('lands the customer reply to our mail in the same ticket (round trip)', function () {
    Queue::fake([SendTicketMessage::class]);
    Livewire::test(Composer::class, ['ticket' => $this->ticket])->set('body', '<div>Bitte testen.</div>')->call('send');
    $outgoing = $this->ticket->messages()->sole();

    $connector = new FakeMailboxConnector;
    app()->instance(MailboxConnector::class, $connector);
    $connector->reply('Re: Drucker defekt', from: 'Anna <anna.becker@kunde.test>', inReplyTo: $outgoing->message_id, body: 'Geht wieder!');
    FetchMailbox::dispatchSync($this->mailbox);

    expect(Ticket::count())->toBe(1)
        ->and($this->ticket->messages()->where('type', 'mail_in')->sole()->plainText())->toContain('Geht wieder!');
});

it('assigns the default mailbox to internally created tickets', function () {
    $default = Mailbox::factory()->default()->create();

    expect(Mailbox::default()->is($default))->toBeTrue();
});
