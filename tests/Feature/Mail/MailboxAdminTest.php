<?php

use App\Livewire\Intern\Admin\Mailboxes\Index;
use App\Mail\Mailboxes\ConnectionTester;
use App\Mail\Mailboxes\MailboxSettings;
use App\Models\Group;
use App\Models\Mailbox;
use App\Models\Ticket;
use App\Models\User;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->actingAs($this->admin);
});

function fillMailbox(Testable $component): Testable
{
    return $component->set([
        'name' => 'Support Musterfirma', 'email' => 'Support@Musterfirma.test',
        'imap_host' => 'imap.musterfirma.test', 'imap_username' => 'support@musterfirma.test', 'imap_password' => 'imap-pw',
        'smtp_host' => 'smtp.musterfirma.test', 'smtp_username' => 'support@musterfirma.test', 'smtp_password' => 'smtp-pw',
    ]);
}

it('is available under /intern/admin/postfaecher with admin.mailboxes only', function () {
    $this->get('/intern/admin/postfaecher')->assertOk()->assertSee('<title>Postfächer · ServiceDesk</title>', false);

    $this->actingAs(User::factory()->staff()->create())->get('/intern/admin/postfaecher')->assertForbidden();
});

it('creates a mailbox', function () {
    $group = Group::factory()->create();

    fillMailbox(Livewire::test(Index::class)->call('create')->assertSet('is_default', true))
        ->set('default_group_id', (string) $group->id)
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('toast', message: 'Postfach angelegt.');

    $mailbox = Mailbox::sole();
    expect($mailbox)
        ->email->toBe('support@musterfirma.test')
        ->imap_password->toBe('imap-pw')
        ->smtp_password->toBe('smtp-pw')
        ->default_group_id->toBe($group->id)
        ->is_default->toBeTrue();
});

it('suggests ports for the encryption and user names from the address', function () {
    Livewire::test(Index::class)
        ->call('create')
        ->set('email', 'hilfe@firma.test')
        ->assertSet('imap_username', 'hilfe@firma.test')
        ->assertSet('smtp_username', 'hilfe@firma.test')
        ->set('smtp_encryption', 'ssl')->assertSet('smtp_port', 465)
        ->set('smtp_encryption', 'tls')->assertSet('smtp_port', 587)
        ->set('imap_encryption', 'tls')->assertSet('imap_port', 143);
});

it('keeps stored passwords when the fields stay empty', function () {
    $mailbox = Mailbox::factory()->create(['imap_password' => 'alt-imap', 'smtp_password' => 'alt-smtp']);

    Livewire::test(Index::class)
        ->call('edit', $mailbox->id)
        ->assertSet('imap_password', '')
        ->set('name', 'Neu')
        ->set('smtp_password', 'neu-smtp')
        ->call('save')
        ->assertHasNoErrors();

    expect($mailbox->refresh())->name->toBe('Neu')->imap_password->toBe('alt-imap')->smtp_password->toBe('neu-smtp');
});

it('validates the input', function (array $input, string $field) {
    Mailbox::factory()->create(['email' => 'vergeben@firma.test']);

    fillMailbox(Livewire::test(Index::class)->call('create'))->set($input)->call('save')->assertHasErrors($field);
})->with([
    'email taken' => [['email' => 'vergeben@firma.test'], 'email'],
    'invalid port' => [['imap_port' => 70000], 'imap_port'],
    'unknown encryption' => [['smtp_encryption' => 'quantum'], 'smtp_encryption'],
    'password missing on create' => [['smtp_password' => ''], 'smtp_password'],
    'move without folder' => [['processed_action' => 'move', 'processed_folder' => ''], 'processed_folder'],
]);

it('tests IMAP and SMTP with the entered settings', function () {
    $tester = Mockery::mock(ConnectionTester::class);
    $tester->shouldReceive('imap')->once()->withArgs(fn (array $s) => $s['host'] === 'imap.musterfirma.test' && $s['password'] === 'imap-pw' && $s['folder'] === 'INBOX')->andReturnNull();
    $tester->shouldReceive('smtp')->once()->andReturn('Connection refused');
    app()->instance(ConnectionTester::class, $tester);

    fillMailbox(Livewire::test(Index::class)->call('create'))
        ->call('testImap')
        ->assertSet('testResults.imap', ['ok' => true, 'message' => 'IMAP-Verbindung erfolgreich.'])
        ->call('testSmtp')
        ->assertSet('testResults.smtp', ['ok' => false, 'message' => 'Connection refused'])
        ->assertSee('Connection refused');
});

it('tests with the stored password while editing', function () {
    $mailbox = Mailbox::factory()->create(['smtp_password' => 'gespeichert']);
    $tester = Mockery::mock(ConnectionTester::class);
    $tester->shouldReceive('smtp')->once()->withArgs(fn (array $s) => $s['password'] === 'gespeichert')->andReturnNull();
    app()->instance(ConnectionTester::class, $tester);

    Livewire::test(Index::class)->call('edit', $mailbox->id)->call('testSmtp')->assertSet('testResults.smtp.ok', true);
});

it('reports real connection errors readably', function () {
    $error = app(ConnectionTester::class)->smtp(['host' => '127.0.0.1', 'port' => 1, 'encryption' => 'none', 'username' => 'x', 'password' => 'y']);

    expect($error)->toBeString()->not->toBeEmpty();
});

it('maps encryption settings to SMTP options', function () {
    $config = fn (string $encryption) => MailboxSettings::smtpConfig(['host' => 'h', 'port' => 1, 'encryption' => $encryption, 'username' => 'u', 'password' => 'p']);

    expect($config('ssl'))->scheme->toBe('smtps')
        ->and($config('tls'))->scheme->toBe('smtp')->require_tls->toBeTrue()
        ->and($config('none'))->auto_tls->toBeFalse()->require_tls->toBeFalse();
});

it('deletes mailboxes only without tickets', function () {
    $used = Mailbox::factory()->create();
    Ticket::factory()->create()->mailbox()->associate($used)->save();
    $unused = Mailbox::factory()->create();

    Livewire::test(Index::class)->call('delete', $used->id)->assertForbidden();
    Livewire::test(Index::class)->call('delete', $unused->id);

    expect(Mailbox::find($unused->id))->toBeNull()->and(Mailbox::find($used->id))->not->toBeNull();
});
