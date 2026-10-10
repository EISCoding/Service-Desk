<?php

use App\Livewire\Intern\Appointments\Index;
use App\Models\Appointment;
use App\Models\Group;
use App\Models\Ticket;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->travelTo('2026-10-14 10:00');
    $this->me = User::factory()->staff()->create(['name' => 'Anna Technik']);
    $this->colleague = User::factory()->staff()->create(['name' => 'Bert Technik']);
    $this->actingAs($this->me);
    $this->ticket = Ticket::factory()->create(['group_id' => null, 'subject' => 'Server tauschen']);
});

it('lists upcoming appointments grouped by day under /intern/termine', function () {
    Appointment::factory()->for($this->ticket)->create(['technician_id' => $this->me->id, 'starts_at' => '2026-10-14 14:00', 'ends_at' => '2026-10-14 15:00']);
    Appointment::factory()->for($this->ticket)->create(['technician_id' => $this->colleague->id, 'starts_at' => '2026-10-16 09:00', 'ends_at' => '2026-10-16 10:00', 'location' => "Lager\nHalle 3"]);
    Appointment::factory()->for($this->ticket)->create(['starts_at' => '2026-10-13 09:00', 'ends_at' => '2026-10-13 10:00', 'note' => 'Vergangen']);

    $this->get('/intern/termine')
        ->assertOk()
        ->assertSee('<title>Termine · ServiceDesk</title>', false)
        ->assertSeeInOrder(['Heute', 'Mittwoch, 14. Oktober 2026', '14:00–15:00', 'Anna Technik', 'Server tauschen',
            'Freitag, 16. Oktober 2026', '09:00–10:00', 'Bert Technik', 'Halle 3'])
        ->assertDontSee('Vergangen');
});

it('filters by technician', function () {
    Appointment::factory()->for($this->ticket)->create(['technician_id' => $this->me->id, 'note' => 'Mein Termin']);
    Appointment::factory()->for($this->ticket)->create(['technician_id' => $this->colleague->id, 'note' => 'Kollegentermin']);

    Livewire::test(Index::class)
        ->assertSee('Mein Termin')->assertSee('Kollegentermin')
        ->set('technician', 'mine')
        ->assertSee('Mein Termin')->assertDontSee('Kollegentermin')
        ->set('technician', (string) $this->colleague->id)
        ->assertSee('Kollegentermin')->assertDontSee('Mein Termin');
});

it('only shows appointments of visible tickets', function () {
    $hidden = Ticket::factory()->create(['group_id' => Group::factory()->create()->id]);
    Appointment::factory()->for($hidden)->create(['note' => 'Geheim']);

    Livewire::test(Index::class)->assertDontSee('Geheim')->assertSee('Keine anstehenden Termine');
});

it('needs ticket access', function () {
    $this->actingAs(User::factory()->create())->get('/intern/termine')->assertForbidden();
});
