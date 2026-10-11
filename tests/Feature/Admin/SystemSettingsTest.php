<?php

use App\Enums\TicketStatus;
use App\Livewire\Intern\Admin\Settings\Index;
use App\Models\Ticket;
use App\Models\User;
use App\Support\AppSettings;
use Illuminate\Http\UploadedFile;
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
        ->assertSee(['Firmenname', 'Logo', 'Akzentfarbe', 'Wiedereröffnungsfrist']);
});

it('applies company name, accent color and reopen period', function () {
    $this->actingAs($this->admin);

    Livewire::test(Index::class)
        ->assertSet('reopenDays', 14)
        ->set('companyName', 'Muster IT')
        ->set('accent', 'orange')
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
        ->assertSee('<title>Anmelden · Kundenportal · Muster IT</title>', false)
        ->assertSee(':root{--accent:#c93400', false)
        ->assertSee('.dark{--accent:#ff9f0a', false);
});

it('validates the settings', function () {
    $this->actingAs($this->admin);

    Livewire::test(Index::class)
        ->set('companyName', '')
        ->set('accent', 'neon')
        ->set('reopenDays', -1)
        ->set('logo', UploadedFile::fake()->create('logo.svg', 10, 'image/svg+xml'))
        ->call('save')
        ->assertHasErrors(['companyName', 'accent', 'reopenDays', 'logo']);
});

it('uploads, serves and removes the logo', function () {
    $this->actingAs($this->admin);

    Livewire::test(Index::class)->set('logo', UploadedFile::fake()->image('logo.png', 128, 128))->call('save')->assertHasNoErrors();

    $settings = app(AppSettings::class);
    Storage::disk('local')->assertExists($settings->get('logo'));
    $this->get($settings->logoUrl())->assertOk()->assertHeader('Cache-Control', 'immutable, max-age=31536000, public');
    $this->get('/portal/login')->assertSee($settings->logoUrl(), false);

    $path = $settings->get('logo');
    Livewire::test(Index::class)->call('removeLogo');

    Storage::disk('local')->assertMissing($path);
    expect(app(AppSettings::class)->logoUrl())->toBeNull();
    $this->get('/logo')->assertNotFound();
});
