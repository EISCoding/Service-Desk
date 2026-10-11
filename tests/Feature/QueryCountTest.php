<?php

use App\Enums\TicketStatus;
use App\Models\Appointment;
use App\Models\Contact;
use App\Models\Customer;
use App\Models\Group;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/*
| The number of queries of a list must not grow with the number of rows (N+1).
*/

function queriesFor(callable $request): int
{
    DB::flushQueryLog();
    DB::enableQueryLog();
    $request();
    DB::disableQueryLog();

    return count(DB::getQueryLog());
}

function seedTickets(int $count, Customer $customer, ?Contact $contact = null): void
{
    $group = Group::firstOrCreate(['name' => 'Support']);
    $assignee = User::factory()->create();

    foreach (range(1, $count) as $i) {
        $ticket = Ticket::factory()->status(TicketStatus::OnSite)->create([
            'customer_id' => $customer->id,
            'contact_id' => $contact?->id ?? Contact::factory()->for($customer)->create()->id,
            'group_id' => $group->id,
            'assignee_id' => $assignee->id,
        ]);
        Appointment::factory()->for($ticket)->create(['starts_at' => now()->addDay(), 'ends_at' => now()->addDay()->addHour()]);
    }
}

it('keeps the staff lists free of N+1 queries', function (string $url) {
    $customer = Customer::factory()->create();
    $this->actingAs($admin = User::factory()->admin()->create());

    seedTickets(2, $customer);
    $this->get($url); // warm up caches (permissions, settings)
    $few = queriesFor(fn () => $this->get($url)->assertOk());

    seedTickets(12, $customer);
    $many = queriesFor(fn () => $this->get($url)->assertOk());

    expect($many)->toBe($few);
})->with([
    'tickets' => '/intern/tickets',
    'customers' => '/intern/kunden',
    'appointments' => '/intern/termine',
]);

it('keeps the portal list free of N+1 queries', function () {
    $customer = Customer::factory()->create();
    $contact = Contact::factory()->for($customer)->primary()->withPortalAccess()->create(['password' => 'Kundenportal2026']);
    $this->actingAs($contact, 'contact');

    seedTickets(2, $customer);
    $this->get('/portal'); // warm up caches
    $few = queriesFor(fn () => $this->get('/portal')->assertOk());

    seedTickets(12, $customer);
    $many = queriesFor(fn () => $this->get('/portal')->assertOk());

    expect($many)->toBe($few);
});
