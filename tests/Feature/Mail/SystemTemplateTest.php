<?php

use App\Enums\SystemTemplate;
use App\Enums\TicketStatus;
use App\Jobs\FetchMailbox;
use App\Jobs\SendTicketMessage;
use App\Livewire\Intern\Tickets\TicketView;
use App\Mail\Inbound\MailboxConnector;
use App\Models\Contact;
use App\Models\Mailbox;
use App\Models\MailTemplate;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use App\Services\TemplateMailer;
use Database\Seeders\MailTemplateSeeder;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Support\FakeMailboxConnector;

beforeEach(function () {
    Storage::fake('local');
    Queue::fake([SendTicketMessage::class]);
    $this->seed(MailTemplateSeeder::class);
    $this->mailbox = Mailbox::factory()->create(['name' => 'Support Musterfirma', 'email' => 'support@musterfirma.test']);
    $this->contact = Contact::factory()->create(['email' => 'anna.becker@kunde.test', 'salutation' => 'frau', 'first_name' => 'Anna', 'last_name' => 'Becker']);
});

function templateMail(Ticket $ticket): ?TicketMessage
{
    return $ticket->messages()->reorder()->where('type', 'mail_out')->whereNotNull('template_key')->latest('id')->first();
}

it('seeds the system templates once and keeps edited texts', function () {
    MailTemplate::where('key', 'ticket_opened')->update(['subject' => 'Eigener Betreff']);

    $this->seed(MailTemplateSeeder::class);

    expect(MailTemplate::where('type', 'system')->pluck('key')->all())->toEqualCanonicalizing(array_column(SystemTemplate::cases(), 'value'))
        ->and(MailTemplate::where('key', 'ticket_opened')->value('subject'))->toBe('Eigener Betreff');
});

describe('ticket opened', function () {
    beforeEach(function () {
        $this->connector = new FakeMailboxConnector;
        app()->instance(MailboxConnector::class, $this->connector);
    });

    it('confirms new mail tickets of known contacts', function () {
        $this->connector->reply('Drucker <defekt>', messageId: 'kunde-1@kunde.test');
        FetchMailbox::dispatchSync($this->mailbox);

        $ticket = Ticket::sole();
        $mail = templateMail($ticket);

        expect($mail)->template_key->toBe('ticket_opened')
            ->subject->toBe('Ihre Anfrage ist eingegangen: Drucker <defekt>')
            ->in_reply_to->toBe('kunde-1@kunde.test')
            ->author_id->toBeNull()
            ->and($mail->to)->toBe([['email' => 'anna.becker@kunde.test', 'name' => 'Anna Becker']])
            ->and($mail->body)->toContain('Sehr geehrte Frau Becker,')->toContain("Ticket#{$ticket->number}");
        Queue::assertPushed(SendTicketMessage::class, fn ($job) => $job->message->is($mail));
    });

    it('does not confirm mails of unknown senders, automatic mails or replies', function () {
        $this->connector->reply('Frage', from: 'Unbekannt <neu@example.org>');
        $this->connector->reply('Abwesenheitsnotiz: Drucker');
        FetchMailbox::dispatchSync($this->mailbox);

        $ticket = Ticket::factory()->create(['contact_id' => $this->contact->id, 'customer_id' => $this->contact->customer_id, 'mailbox_id' => $this->mailbox->id]);
        $this->connector->reply("Nachtrag [#{$ticket->number}]");
        FetchMailbox::dispatchSync($this->mailbox);

        expect(TicketMessage::whereNotNull('template_key')->count())->toBe(0);
        Queue::assertNothingPushed();
    });

    it('sends nothing while the template is switched off', function () {
        MailTemplate::where('key', 'ticket_opened')->update(['is_active' => false]);

        $this->connector->reply('Drucker defekt');
        FetchMailbox::dispatchSync($this->mailbox);

        expect(Ticket::sole()->messages()->count())->toBe(1);
    });
});

describe('status dialog', function () {
    beforeEach(function () {
        $this->staff = User::factory()->staff()->create(['name' => 'Bert Support']);
        $this->actingAs($this->staff);
        $this->ticket = Ticket::factory()->create([
            'subject' => 'Drucker defekt', 'customer_id' => $this->contact->customer_id, 'contact_id' => $this->contact->id,
            'mailbox_id' => $this->mailbox->id, 'group_id' => null,
        ]);
    });

    it('preselects the customer mail for In Arbeit, Warte auf Kunden and Geschlossen', function (string $status, bool $preselected) {
        Livewire::test(TicketView::class, ['ticket' => $this->ticket])
            ->call('setStatus', $status)
            ->assertSet('pendingStatus', $status)
            ->assertSet('notifyCustomer', $preselected)
            ->assertSee('Kunden per E-Mail informieren');

        expect($this->ticket->refresh()->status)->toBe(TicketStatus::New);
    })->with([
        ['in_progress', true], ['waiting_customer', true], ['closed', true], ['waiting_internal', false], ['new', false],
    ]);

    it('informs the customer about the new status', function () {
        Livewire::test(TicketView::class, ['ticket' => $this->ticket])
            ->call('setStatus', 'waiting_customer')
            ->call('confirmStatus')
            ->assertDispatched('toast', message: 'Status: Warte auf Kunden. Der Kunde wird per E-Mail informiert.');

        $mail = templateMail($this->ticket);
        expect($this->ticket->refresh()->status)->toBe(TicketStatus::WaitingCustomer)
            ->and($mail)->template_key->toBe('status_changed')
            ->subject->toBe('Neuer Status „Warte auf Kunden“: Drucker defekt')
            ->and($mail->author->is($this->staff))->toBeTrue()
            ->and($mail->body)->toContain('<strong>Warte auf Kunden</strong>');
    });

    it('changes the status without mail when unchecked', function () {
        Livewire::test(TicketView::class, ['ticket' => $this->ticket])
            ->call('setStatus', 'waiting_internal')
            ->call('confirmStatus');

        expect($this->ticket->refresh()->status)->toBe(TicketStatus::WaitingInternal)
            ->and(templateMail($this->ticket))->toBeNull();
    });

    it('sends the closing mail when closing', function () {
        Livewire::test(TicketView::class, ['ticket' => $this->ticket])->call('setStatus', 'closed')->call('confirmStatus');

        expect($this->ticket->refresh()->isClosed())->toBeTrue()
            ->and(templateMail($this->ticket)->template_key)->toBe('ticket_closed');
    });

    it('asks when taking the ticket', function () {
        Livewire::test(TicketView::class, ['ticket' => $this->ticket])
            ->call('take')
            ->assertSet('taking', true)
            ->assertSee('Ticket übernehmen')
            ->call('confirmStatus')
            ->assertDispatched('toast', message: 'Ticket übernommen. Der Kunde wird per E-Mail informiert.');

        expect($this->ticket->refresh())->status->toBe(TicketStatus::InProgress)->assignee_id->toBe($this->staff->id)
            ->and(templateMail($this->ticket)->body)->toContain('<strong>In Arbeit</strong>');
    });

    it('changes the status directly when no mail can be sent', function () {
        MailTemplate::where('key', 'status_changed')->update(['is_active' => false]);

        Livewire::test(TicketView::class, ['ticket' => $this->ticket])->call('setStatus', 'in_progress')->assertSet('pendingStatus', null);
        Livewire::test(TicketView::class, ['ticket' => $this->ticket->refresh()])->call('take')->assertSet('pendingStatus', null);

        $withoutRecipient = Ticket::factory()->withoutCustomer()->create(['mailbox_id' => $this->mailbox->id]);
        Livewire::test(TicketView::class, ['ticket' => $withoutRecipient])->call('setStatus', 'closed')->assertSet('pendingStatus', null);

        expect($withoutRecipient->refresh()->isClosed())->toBeTrue()
            ->and(TicketMessage::whereNotNull('template_key')->count())->toBe(0);
    });

    it('confirms appointments with date, time and technician', function () {
        $technician = User::factory()->create(['name' => 'Tina Technik']);

        Livewire::test(TicketView::class, ['ticket' => $this->ticket])
            ->call('setStatus', 'on_site')
            ->assertSet('appointmentNotify', true)
            ->assertSee('Terminbestätigung an den Kunden senden')
            ->set('appointmentDate', '2026-12-17')
            ->set('appointmentFrom', '09:00')
            ->set('appointmentTo', '10:30')
            ->set('appointmentTechnician', (string) $technician->id)
            ->call('saveAppointment')
            ->assertHasNoErrors();

        $mail = templateMail($this->ticket);
        expect($mail)->template_key->toBe('appointment_confirmed')
            ->subject->toBe('Terminbestätigung für Donnerstag, 17. Dezember 2026')
            ->and($mail->body)->toContain('Donnerstag, 17. Dezember 2026, 09:00–10:30 Uhr')->toContain('Techniker: Tina Technik');
    });

    it('can plan an appointment without confirmation', function () {
        Livewire::test(TicketView::class, ['ticket' => $this->ticket])
            ->call('setStatus', 'on_site')
            ->set('appointmentNotify', false)
            ->call('saveAppointment')
            ->assertHasNoErrors();

        expect($this->ticket->refresh()->status)->toBe(TicketStatus::OnSite)
            ->and(templateMail($this->ticket))->toBeNull();
    });
});

it('labels template mails in the conversation', function () {
    $this->actingAs(User::factory()->admin()->create());
    $ticket = Ticket::factory()->create(['contact_id' => $this->contact->id, 'customer_id' => $this->contact->customer_id, 'mailbox_id' => $this->mailbox->id]);
    app(TemplateMailer::class)->send($ticket, SystemTemplate::TicketClosed);

    Livewire::test(TicketView::class, ['ticket' => $ticket])->assertSee('Ticket geschlossen');
});
