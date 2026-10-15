<?php

use App\Enums\TicketStatus;
use App\Livewire\Intern\Admin\Settings\Index;
use App\Models\Ticket;
use App\Models\User;
use App\Support\AppSettings;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('local');
    $this->admin = User::factory()->admin()->create();
});

it('is only available with admin.settings', function () {
    $this->actingAs(User::factory()->staff()->create())->get('/intern/admin/einstellungen')->assertForbidden();

    $this->actingAs($this->admin)->get('/intern/admin')->assertSee(route('intern.admin.settings'));
    $this->get('/intern/admin/einstellungen')->assertOk()
        ->assertSee('<title>Systemeinstellungen · ServiceDesk</title>', false)
        ->assertSee(['Firmenname', 'Wiedereröffnungsfrist', 'Erscheinungsbild']);
});

it('applies company name and reopen period', function () {
    $this->actingAs($this->admin);

    Livewire::test(Index::class)
        ->assertSet('reopenDays', 14)
        ->set('companyName', 'Muster IT')
        ->set('reopenDays', 30)
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect('/intern/admin/einstellungen');

    expect(config('app.name'))->toBe('Muster IT')
        ->and(config('servicedesk.mail.reopen_days'))->toBe(30);

    $ticket = Ticket::factory()->status(TicketStatus::Closed)->create(['closed_at' => now()->subDays(20)]);
    expect($ticket->canBeReopened())->toBeTrue();

    // A new request reads the settings again.
    app()->forgetInstance(AppSettings::class);
    $this->get('/portal/login')
        ->assertSee('<title>Anmelden · Kundenportal · Muster IT</title>', false);
});

it('validates the settings', function () {
    $this->actingAs($this->admin);

    Livewire::test(Index::class)
        ->set('companyName', '')
        ->set('reopenDays', -1)
        ->call('save')
        ->assertHasErrors(['companyName', 'reopenDays']);
});
