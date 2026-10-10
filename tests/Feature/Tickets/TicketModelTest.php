<?php

use App\Enums\TicketPriority;
use App\Enums\TicketSource;
use App\Enums\TicketStatus;
use App\Models\Contact;
use App\Models\Customer;
use App\Models\Group;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\QueryException;

it('has the fields from the plan with sensible defaults', function () {
    $ticket = Ticket::factory()->create()->refresh();

    expect($ticket->status)->toBe(TicketStatus::New)
        ->and($ticket->priority)->toBe(TicketPriority::Normal)
        ->and($ticket->source)->toBe(TicketSource::Internal)
        ->and($ticket->customer)->toBeInstanceOf(Customer::class)
        ->and($ticket->follow_up_at)->toBeNull()
        ->and($ticket->closed_at)->toBeNull()
        ->and($ticket->last_activity_at)->not->toBeNull()
        ->and($ticket->number)->toMatch('/^\d{4}-\d{5}$/');
});

it('links contact, group and assignee', function () {
    $ticket = Ticket::factory()->create();
    $ticket->contact()->associate(Contact::factory()->create(['customer_id' => $ticket->customer_id]));
    $ticket->group()->associate(Group::factory()->create());
    $ticket->assignee()->associate(User::factory()->create());
    $ticket->save();

    $ticket->refresh();
    expect($ticket->contact)->toBeInstanceOf(Contact::class)
        ->and($ticket->group)->toBeInstanceOf(Group::class)
        ->and($ticket->assignee)->toBeInstanceOf(User::class);
});

it('can exist without customer (unknown mail senders)', function () {
    expect(Ticket::factory()->withoutCustomer()->create()->customer)->toBeNull();
});

it('keeps tickets when contact, group or assignee are removed', function () {
    $ticket = Ticket::factory()->create();
    $ticket->forceFill([
        'contact_id' => Contact::factory()->create(['customer_id' => $ticket->customer_id])->id,
        'group_id' => Group::factory()->create()->id,
        'assignee_id' => User::factory()->create()->id,
    ])->save();

    $ticket->contact->delete();
    $ticket->group->delete();
    $ticket->assignee->delete();

    expect($ticket->fresh())->contact_id->toBeNull()->group_id->toBeNull()->assignee_id->toBeNull();
});

it('prevents deleting customers with tickets', function () {
    $ticket = Ticket::factory()->create();

    expect($ticket->customer->hasTickets())->toBeTrue()
        ->and(User::factory()->staff()->create()->can('delete', $ticket->customer))->toBeFalse();

    $ticket->customer->delete();
})->throws(QueryException::class);

it('separates open and closed tickets', function () {
    Ticket::factory()->count(2)->create();
    Ticket::factory()->status(TicketStatus::Closed)->create();

    expect(Ticket::open()->count())->toBe(2)
        ->and(Ticket::where('status', 'closed')->first()->isClosed())->toBeTrue();
});

it('logs field changes but not bookkeeping columns', function () {
    $ticket = Ticket::factory()->create();
    $ticket->update(['priority' => TicketPriority::Urgent]);
    $ticket->touchActivity();
    $ticket->forceFill(['follow_up_due' => true])->save();

    expect($ticket->activities->pluck('action')->all())->toBe(['updated', 'created'])
        ->and($ticket->activities->first()->changes)->toBe(['priority' => ['old' => 'normal', 'new' => 'urgent']]);
});
