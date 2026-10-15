<?php

use App\Enums\MessageType;
use App\Livewire\Intern\Admin\Logs\Index;
use App\Models\Customer;
use App\Models\Mailbox;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use App\Support\SchedulerHeartbeat;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->actingAs($this->admin);

    $this->logDir = storage_path('logs');
    $this->logFile = 'test-'.uniqid().'.log';
    File::put($this->logDir.'/'.$this->logFile, implode("\n", [
        '[2026-10-14 09:00:00] local.INFO: Alles gut',
        '[2026-10-14 09:05:00] local.WARNING: Postfach langsam',
        '[2026-10-14 09:10:00] local.ERROR: SMTP abgelehnt {"exception":"[object] (Swift)"}',
        '#0 /var/www/app/Mail.php(12): send()',
        '#1 {main}',
        '',
    ]));
    touch($this->logDir.'/'.$this->logFile, time() + 60);
});

afterEach(fn () => File::delete($this->logDir.'/'.$this->logFile));

it('is only available with the log permission', function () {
    $this->get(route('intern.admin.logs'))->assertOk()->assertSee('Protokolle');
    $this->get(route('intern.admin.index'))->assertSee('Protokolle');
    $this->actingAs(User::factory()->staff()->create())->get(route('intern.admin.logs'))->assertForbidden();
});

it('shows errors and warnings of the newest log file with details', function () {
    Livewire::test(Index::class)
        ->assertSet('tab', 'system')
        ->assertSeeInOrder(['SMTP abgelehnt', '#0 /var/www/app/Mail.php', 'Postfach langsam'])
        ->assertDontSee('Alles gut')
        ->set('level', 'errors')
        ->assertDontSee('Postfach langsam')
        ->set('level', 'all')
        ->assertSee('Alles gut')
        ->set('search', 'postfach')
        ->assertSee('Postfach langsam')
        ->assertDontSee('SMTP abgelehnt');
});

it('downloads log files but nothing else', function () {
    $this->get(route('intern.admin.logs.download', $this->logFile))->assertOk()->assertDownload($this->logFile);
    $this->get('/intern/admin/protokolle/datei/..%2F.env')->assertNotFound();
    $this->get(route('intern.admin.logs.download', 'gibt-es-nicht.log'))->assertNotFound();
});

it('lists incoming and outgoing mails with errors and the mailbox status', function () {
    Mailbox::factory()->create(['name' => 'Support', 'last_error' => 'IMAP-Login fehlgeschlagen']);
    $ticket = Ticket::factory()->create();
    TicketMessage::factory()->for($ticket)->create(['type' => MessageType::MailIn, 'subject' => 'Drucker defekt', 'from_email' => 'kunde@example.com']);
    TicketMessage::factory()->for($ticket)->create(['type' => MessageType::MailOut, 'subject' => 'Re: Drucker defekt', 'to' => [['email' => 'kunde@example.com', 'name' => null]], 'mail_status' => 'failed', 'mail_error' => 'Mailbox voll']);
    TicketMessage::factory()->for($ticket)->create(['body' => '<p>Portal</p>', 'subject' => 'Keine Mail']);

    Livewire::test(Index::class)
        ->set('tab', 'mail')
        ->assertSee(['Support', 'IMAP-Login fehlgeschlagen', 'Drucker defekt', 'Mailbox voll', 'Fehlgeschlagen', $ticket->reference()])
        ->assertDontSee('Keine Mail')
        ->set('mailFilter', 'in')
        ->assertDontSee('Mailbox voll');
});

it('lists the activity of all areas with links', function () {
    $customer = Customer::factory()->create(['company' => 'Schmidt AG']);
    $customer->update(['city' => 'Berlin']);

    Livewire::test(Index::class)
        ->set('tab', 'activity')
        ->assertSee('Schmidt AG')
        ->assertSee(route('intern.customers.show', $customer))
        ->set('subject', 'ticket')
        ->assertDontSee('Schmidt AG');
});

it('shows the queue health and retries or deletes failed jobs', function () {
    SchedulerHeartbeat::beat();
    DB::table('failed_jobs')->insert([
        'uuid' => 'job-1', 'connection' => 'database', 'queue' => 'default',
        'payload' => json_encode(['displayName' => 'App\\Jobs\\SendTicketMail']), 'exception' => "RuntimeException: SMTP down\n#0 trace", 'failed_at' => now(),
    ]);

    $component = Livewire::test(Index::class)
        ->set('tab', 'jobs')
        ->assertSee(['Zuletzt', 'SendTicketMail', 'RuntimeException: SMTP down']);

    $component->call('forgetJob', 'job-1');
    expect(DB::table('failed_jobs')->count())->toBe(0);
});

it('warns when the scheduler does not run', function () {
    Livewire::test(Index::class)->set('tab', 'jobs')->assertSee('Noch nie gelaufen')->assertSee('schedule:run');
});
