<?php

use App\Livewire\Intern\Admin\MailDesign\Index;
use App\Mail\MailDesignTestMail;
use App\Models\Mailbox;
use App\Models\User;
use App\Support\AppSettings;
use App\Support\MailBranding;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('local');
    $this->admin = User::factory()->admin()->create(['email' => 'admin@firma.test']);
    $this->actingAs($this->admin);
});

function savedBrand(): MailBranding
{
    app()->forgetInstance(AppSettings::class);

    return app(MailBranding::class);
}

it('is only available with admin.settings', function () {
    $this->actingAs(User::factory()->staff()->create())->get('/intern/admin/mail-design')->assertForbidden();
    $this->actingAs(User::factory()->staff()->create())->get('/intern/admin/einstellungen/mail-vorschau')->assertForbidden();

    $this->actingAs($this->admin)->get('/intern/admin')->assertSee(route('intern.admin.mail-design'));
    $this->get('/intern/admin/mail-design')->assertOk()
        ->assertSee('<title>Mail-Design · ServiceDesk</title>', false)
        ->assertSee(['Farben und Schrift', 'Kopfzeile', 'Fußzeile', 'Vorschau', 'Testmail senden']);
});

it('shows unsaved changes in the live preview', function () {
    $component = Livewire::test(Index::class)
        ->set('customAccent', true)
        ->set('accent', '#123456')
        ->set('background', '#fafafa')
        ->set('font', 'georgia')
        ->set('header', '<p>Hotline <strong>0123 456</strong></p>')
        ->set('footer', '<p>Muster IT GmbH · <a href="https://muster.de">muster.de</a></p>')
        ->set('replyHint', 'Einfach antworten.');

    expect($component->instance()->preview())
        ->toContain('background-color:#123456')
        ->toContain('background-color:#fafafa')
        ->toContain('font-family:Georgia')
        ->toContain('Hotline <strong>0123 456</strong>')
        ->toContain('Muster IT GmbH · <a href="https://muster.de"')
        ->toContain('Einfach antworten.')
        ->toContain('Guten Tag Erika Muster');

    // Nothing is stored before saving.
    expect(savedBrand()->footer())->toBeNull()
        ->and(savedBrand()->accent())->toBe('#0066cc');
});

it('saves the design and uses it for all mails', function () {
    Livewire::test(Index::class)
        ->set('customAccent', true)
        ->set('accent', '#AA3300')
        ->set('logo', 'center')
        ->set('logoSize', '56')
        ->set('showName', false)
        ->set('accentBar', false)
        ->set('rounded', false)
        ->set('footer', '<p>Muster IT GmbH<br>Telefon 0123</p><script>alert(1)</script>')
        ->call('save')
        ->assertHasNoErrors();

    $brand = savedBrand();
    expect($brand->accent())->toBe('#aa3300')
        ->and($brand->logoPlacement())->toBe('center')
        ->and($brand->logoSize())->toBe(56)
        ->and($brand->showName())->toBeFalse()
        ->and($brand->accentBar())->toBeFalse()
        ->and($brand->radius())->toBe(0)
        ->and($brand->footer())->toBe('<p>Muster IT GmbH<br />Telefon 0123</p>')
        ->and($brand->footerText())->toBe("Muster IT GmbH\nTelefon 0123");

    $html = $this->get('/intern/admin/einstellungen/mail-vorschau')->assertOk()->getContent();
    expect($html)->toContain('.content a { color: #aa3300; }')
        ->toContain('border-radius:0px')
        ->toContain('Muster IT GmbH<br />Telefon 0123')
        ->not->toContain('<script>');
});

it('falls back to the accent color of the appearance settings', function () {
    app(AppSettings::class)->set(['accent' => 'green']);

    Livewire::test(Index::class)->assertSet('customAccent', false)->assertSet('accent', '#1f7f37')->call('save');

    expect(savedBrand()->design()['accent'])->toBeNull()
        ->and(savedBrand()->accent())->toBe('#1f7f37');
});

it('keeps the plain text footer of older versions until it is edited', function () {
    app(AppSettings::class)->set(['mail_footer' => "Alte Firma\nHauptstraße 1"]);

    Livewire::test(Index::class)->assertSet('footer', 'Alte Firma<br />Hauptstraße 1')->call('save');

    expect(app(AppSettings::class)->get('mail_footer'))->toBeNull()
        ->and(savedBrand()->footerText())->toBe("Alte Firma\nHauptstraße 1");
});

it('validates colors and choices', function () {
    Livewire::test(Index::class)
        ->set('accent', 'red')
        ->set('background', '#12')
        ->set('font', 'comic-sans')
        ->set('logo', 'right')
        ->set('logoSize', 99)
        ->call('save')
        ->assertHasErrors(['accent', 'background', 'font', 'logo', 'logoSize']);
});

it('restores the default design', function () {
    Livewire::test(Index::class)
        ->set('background', '#000000')
        ->set('font', 'georgia')
        ->set('logo', 'none')
        ->call('restoreDefaults')
        ->assertSet('background', '#f2f2f7')
        ->assertSet('font', 'system')
        ->assertSet('logo', 'left');
});

it('sends a test mail in the unsaved design through the default mailbox', function () {
    Mail::fake();
    Mailbox::factory()->create(['is_default' => true, 'email' => 'support@firma.test']);

    Livewire::test(Index::class)
        ->set('background', '#eeeeee')
        ->call('sendTest')
        ->assertHasNoErrors();

    Mail::assertSent(MailDesignTestMail::class, function (MailDesignTestMail $mail) {
        return collect($mail->to)->pluck('address')->all() === ['admin@firma.test']
            && collect($mail->from)->pluck('address')->all() === ['support@firma.test']
            && $mail->brand->background() === '#eeeeee';
    });
});

it('reports a failing test mail', function () {
    Mail::shouldReceive('mailer')->andThrow(new RuntimeException('Connection refused'));

    Livewire::test(Index::class)
        ->set('testRecipient', 'kunde@example.org')
        ->call('sendTest')
        ->assertHasErrors(['testRecipient']);
});
