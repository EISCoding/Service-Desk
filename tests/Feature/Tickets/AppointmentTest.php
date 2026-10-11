<?php

use App\Enums\TicketStatus;
use App\Livewire\Intern\Tickets\TicketView;
use App\Models\Activity;
use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Ticket;
use App\Models\User;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

beforeEach(function () {
    $this->staff = User::factory()->staff()->create(['name' => 'Anna Technik']);
    $customer = Customer::factory()->create(['company' => 'Müller GmbH', 'street' => 'Hauptstraße 1', 'postal_code' => '10115', 'city' => 'Berlin']);
    $this->ticket = Ticket::factory()->create(['group_id' => null, 'customer_id' => $customer->id, 'assignee_id' => $this->staff->id]);
    $this->actingAs($this->staff);
    $this->travelTo('2026-10-14 10:00'); // Wednesday
});

function vosView(): Testable
{
    return Livewire::test(TicketView::class, ['ticket' => test()->ticket]);
}

it('opens the appointment dialog when switching to VOS', function () {
    vosView()
        ->call('setStatus', 'on_site')
        ->assertSet('planningAppointment', true)
        ->assertSet('appointmentDate', '2026-10-15')
        ->assertSet('appointmentTechnician', (string) $this->staff->id)
        ->assertSet('appointmentLocation', "Müller GmbH\nHauptstraße 1\n10115 Berlin")
        ->assertSee('VOS-Termin planen');

    expect($this->ticket->refresh()->status)->toBe(TicketStatus::New);
});

it('saves the appointment and sets the status to VOS', function () {
    vosView()
        ->call('setStatus', 'on_site')
        ->set('appointmentFrom', '08:30')
        ->set('appointmentTo', '11:00')
        ->set('appointmentNote', 'Schlüssel beim Pförtner')
        ->call('saveAppointment')
        ->assertHasNoErrors()
        ->assertSet('planningAppointment', false)
        ->assertDispatched('toast', message: 'Termin geplant: Do, 15. Okt 2026, 08:30–11:00')
        ->assertSee('Schlüssel beim Pförtner');

    $appointment = Appointment::sole();
    expect($appointment->starts_at->format('Y-m-d H:i'))->toBe('2026-10-15 08:30')
        ->and($appointment->ends_at->format('H:i'))->toBe('11:00')
        ->and($appointment->technician->is($this->staff))->toBeTrue()
        ->and($appointment->creator->is($this->staff))->toBeTrue()
        ->and($this->ticket->refresh()->status)->toBe(TicketStatus::OnSite)
        ->and(Activity::forHistoryOf($this->ticket)->where('subject_type', 'appointment')->pluck('action')->all())->toBe(['created']);
});

it('validates the appointment', function (array $input, string $field) {
    vosView()->call('planAppointment')->set($input)->call('saveAppointment')->assertHasErrors($field);
})->with([
    'past date' => [['appointmentDate' => '2026-10-13'], 'appointmentDate'],
    'end before start' => [['appointmentFrom' => '10:00', 'appointmentTo' => '09:00'], 'appointmentTo'],
    'invalid time' => [['appointmentFrom' => '25:00'], 'appointmentFrom'],
    'no location' => [['appointmentLocation' => ''], 'appointmentLocation'],
    'no technician' => [['appointmentTechnician' => ''], 'appointmentTechnician'],
]);

it('does not accept deactivated technicians', function () {
    $inactive = User::factory()->inactive()->create();

    vosView()->call('planAppointment')->set('appointmentTechnician', (string) $inactive->id)->call('saveAppointment')
        ->assertHasErrors('appointmentTechnician');
});

it('keeps the status when cancelling the dialog', function () {
    vosView()->call('setStatus', 'on_site')->call('cancelStatusChange')->assertSet('planningAppointment', false);

    expect($this->ticket->refresh()->status)->toBe(TicketStatus::New)
        ->and(Appointment::count())->toBe(0);
});

it('plans further appointments and deletes them', function () {
    $existing = Appointment::factory()->for($this->ticket)->create();
    $this->ticket->forceFill(['status' => TicketStatus::OnSite])->save();

    vosView()
        ->assertSee($existing->timeRange())
        ->call('planAppointment')
        ->call('saveAppointment')
        ->assertHasNoErrors();
    expect($this->ticket->appointments()->count())->toBe(2);

    vosView()->call('deleteAppointment', $existing->id)->assertDispatched('toast', message: 'Termin gelöscht.');
    expect(Appointment::find($existing->id))->toBeNull();
});

it('cannot delete appointments of other tickets', function () {
    $foreign = Appointment::factory()->create();

    vosView()->call('deleteAppointment', $foreign->id)->assertNotFound();
});

it('needs tickets.edit', function () {
    $viewer = User::factory()->create();
    $viewer->givePermissionTo('tickets.view.all');
    $this->actingAs($viewer);

    vosView()->assertDontSee('wire:click="planAppointment"', false)->call('planAppointment')->assertForbidden();
});
