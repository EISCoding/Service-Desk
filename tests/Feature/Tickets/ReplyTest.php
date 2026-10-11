<?php

use App\Enums\MessageType;
use App\Enums\TicketStatus;
use App\Livewire\Intern\Tickets\Composer;
use App\Models\Group;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('local');
    $this->staff = User::factory()->staff()->create();
    $this->ticket = Ticket::factory()->create(['group_id' => null, 'last_activity_at' => now()->subDay()]);
    $this->actingAs($this->staff);
});

function composer(): Testable
{
    return Livewire::test(Composer::class, ['ticket' => test()->ticket]);
}

it('posts a public reply with attachments', function () {
    composer()
        ->set('body', '<div>Wir kümmern uns.</div>')
        ->set('files', [UploadedFile::fake()->create('log.txt', 10, 'text/plain'), UploadedFile::fake()->image('foto.png')])
        ->call('send')
        ->assertHasNoErrors()
        ->assertSet('body', '')
        ->assertSet('files', [])
        ->assertDispatched('ticket-changed')
        ->assertDispatched('toast', message: 'Antwort gespeichert.');

    $message = $this->ticket->messages()->sole();
    expect($message->type)->toBe(MessageType::Public)
        ->and($message->author->is($this->staff))->toBeTrue()
        ->and($message->attachments->pluck('original_name')->all())->toEqualCanonicalizing(['log.txt', 'foto.png'])
        ->and($this->ticket->refresh()->last_activity_at->isToday())->toBeTrue()
        ->and($this->ticket->activities()->first()->action)->toBe('reply_added');

    foreach ($message->attachments as $attachment) {
        Storage::disk('local')->assertExists($attachment->path);
    }
});

it('writes internal notes', function () {
    composer()
        ->call('setMode', 'note')
        ->set('body', '<div>Kunde war am Telefon unfreundlich.</div>')
        ->call('send')
        ->assertHasNoErrors()
        ->assertDispatched('toast', message: 'Interne Notiz gespeichert.');

    expect($this->ticket->messages()->sole()->type)->toBe(MessageType::Internal)
        ->and($this->ticket->activities()->first()->action)->toBe('note_added');
});

it('sets the status together with a reply', function (string $status, TicketStatus $expected) {
    composer()->set('body', '<div>Erledigt.</div>')->set('statusAfter', $status)->call('send')->assertHasNoErrors();

    expect($this->ticket->refresh()->status)->toBe($expected);
})->with([
    'waiting for customer' => ['waiting_customer', TicketStatus::WaitingCustomer],
    'closed' => ['closed', TicketStatus::Closed],
    'unchanged' => ['', TicketStatus::New],
]);

it('does not allow statuses that need a dialog', function () {
    composer()->set('body', '<div>x</div>')->set('statusAfter', 'on_site')->call('send')->assertHasErrors('statusAfter');
});

it('validates text and files', function () {
    composer()->call('send')->assertHasErrors('body');
    composer()->set('body', '<div><br></div>')->call('send')->assertHasErrors('body');

    config(['servicedesk.attachments.max_kb' => 100]);
    composer()->set('body', '<div>x</div>')->set('files', [UploadedFile::fake()->create('gross.zip', 200)])->call('send')
        ->assertHasErrors('files.0');

    config(['servicedesk.attachments.max_files' => 1]);
    composer()->set('body', '<div>x</div>')->set('files', [UploadedFile::fake()->create('a.txt', 1), UploadedFile::fake()->create('b.txt', 1)])->call('send')
        ->assertHasErrors('files');
});

it('can remove a selected file before sending', function () {
    composer()
        ->set('files', [UploadedFile::fake()->create('a.txt', 1), UploadedFile::fake()->create('b.txt', 1)])
        ->call('removeFile', 0)
        ->set('body', '<div>x</div>')
        ->call('send');

    expect($this->ticket->messages()->sole()->attachments->pluck('original_name')->all())->toBe(['b.txt']);
});

describe('permissions', function () {
    it('needs notes.manage for internal notes', function () {
        $user = User::factory()->create();
        $user->givePermissionTo(['tickets.view.all', 'tickets.edit']);
        $this->actingAs($user);

        composer()->assertDontSee('Interne Notiz')->call('setMode', 'note')->set('body', '<div>x</div>')->call('send')->assertForbidden();
    });

    it('needs tickets.edit for public replies and starts in note mode without it', function () {
        $user = User::factory()->create();
        $user->givePermissionTo(['tickets.view.all', 'notes.manage']);
        $this->actingAs($user);

        composer()->assertSet('mode', 'note')->call('setMode', 'reply')->set('body', '<div>x</div>')->call('send')->assertForbidden();
    });

    it('needs the ticket to be visible', function () {
        $this->ticket->forceFill(['group_id' => Group::factory()->create()->id])->save();

        composer()->assertForbidden();
    });
});
