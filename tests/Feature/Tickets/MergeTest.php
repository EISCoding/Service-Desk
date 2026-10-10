<?php

use App\Enums\MessageType;
use App\Enums\TicketStatus;
use App\Livewire\Intern\Tickets\TicketView;
use App\Models\Appointment;
use App\Models\Group;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use App\Services\TicketMerger;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->actingAs($this->admin);
    $this->source = Ticket::factory()->create(['subject' => 'Drucker defekt (Duplikat)']);
    $this->target = Ticket::factory()->create(['subject' => 'Drucker defekt']);
});

it('moves messages and appointments, closes the source and notes both', function () {
    $message = TicketMessage::factory()->for($this->source)->create(['body' => '<p>Zweite Mail</p>']);
    $appointment = Appointment::factory()->for($this->source)->create();

    app(TicketMerger::class)->merge($this->source, $this->target, $this->admin);

    $this->source->refresh();
    expect($message->refresh()->ticket_id)->toBe($this->target->id)
        ->and($appointment->refresh()->ticket_id)->toBe($this->target->id)
        ->and($this->source->status)->toBe(TicketStatus::Closed)
        ->and($this->source->merged_into_id)->toBe($this->target->id)
        ->and($this->source->messages->sole()->type)->toBe(MessageType::Internal)
        ->and($this->source->messages->sole()->plainText())->toContain($this->target->number)
        ->and($this->target->messages()->where('type', 'internal')->sole()->plainText())->toContain($this->source->number)
        ->and($this->source->activities()->where('action', 'merged_into')->exists())->toBeTrue()
        ->and($this->target->activities()->where('action', 'merged_from')->exists())->toBeTrue();
});

it('takes over the customer when the target has none', function () {
    $this->target->forceFill(['customer_id' => null, 'contact_id' => null])->save();

    app(TicketMerger::class)->merge($this->source, $this->target, $this->admin);

    expect($this->target->refresh()->customer_id)->toBe($this->source->customer_id);
});

it('refuses merging into itself or merging twice', function (string $case) {
    $merger = app(TicketMerger::class);

    match ($case) {
        'itself' => $merger->merge($this->source, $this->source, $this->admin),
        'twice' => (function () use ($merger) {
            $merger->merge($this->source, $this->target, $this->admin);
            $merger->merge($this->source->refresh(), Ticket::factory()->create(), $this->admin);
        })(),
    };
})->with(['itself', 'twice'])->throws(InvalidArgumentException::class);

it('merges from the ticket view and opens the target', function () {
    Livewire::test(TicketView::class, ['ticket' => $this->source])
        ->assertSee('Zusammenführen …')
        ->set('mergeSearch', $this->target->number)
        ->assertSee('Drucker defekt')
        ->call('merge', $this->target->id)
        ->assertRedirect(route('intern.tickets.show', $this->target));

    expect($this->source->refresh()->merged_into_id)->toBe($this->target->id)
        ->and(session('toast.message'))->toBe("Ticket {$this->source->number} wurde in {$this->target->number} zusammengeführt.");
});

it('offers only visible, unmerged other tickets as targets', function () {
    $staff = User::factory()->staff()->create();
    $staff->givePermissionTo('tickets.merge');
    $this->actingAs($staff);
    $this->source->forceFill(['group_id' => null])->save();
    $this->target->forceFill(['group_id' => null])->save();
    Ticket::factory()->create(['subject' => 'Drucker geheim', 'group_id' => Group::factory()->create()->id]);
    Ticket::factory()->create(['subject' => 'Drucker alt', 'group_id' => null, 'merged_into_id' => $this->target->id]);

    $results = Livewire::test(TicketView::class, ['ticket' => $this->source])->set('mergeSearch', 'Drucker')->instance()->mergeResults;

    expect($results->pluck('subject')->all())->toBe(['Drucker defekt']);
});

it('needs tickets.merge', function () {
    $staff = User::factory()->staff()->create();
    $this->actingAs($staff);
    $this->source->forceFill(['group_id' => null])->save();

    Livewire::test(TicketView::class, ['ticket' => $this->source])
        ->assertDontSee('Zusammenführen …')
        ->call('merge', $this->target->id)
        ->assertForbidden();
});

it('cannot merge into tickets the user may not see', function () {
    $staff = User::factory()->staff()->create();
    $staff->givePermissionTo('tickets.merge');
    $this->actingAs($staff);
    $this->source->forceFill(['group_id' => null])->save();
    $hidden = Ticket::factory()->create(['group_id' => Group::factory()->create()->id]);

    Livewire::test(TicketView::class, ['ticket' => $this->source])->call('merge', $hidden->id)->assertNotFound();
});
