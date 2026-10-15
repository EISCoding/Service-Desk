<?php

use App\Enums\SystemTemplate;
use App\Livewire\Intern\Admin\Branding\Index as BrandingSettings;
use App\Mail\TicketMessageMail;
use App\Models\Contact;
use App\Models\Mailbox;
use App\Models\MailTemplate;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use App\Notifications\Portal\ResetPassword;
use App\Support\AppSettings;
use App\Support\Html;
use App\Support\Placeholders;
use Database\Seeders\MailTemplateSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('local');
    $this->settings = app(AppSettings::class);
});

function sampleTicketMail(): TicketMessageMail
{
    $contact = Contact::factory()->create();
    $ticket = Ticket::factory()->create(['customer_id' => $contact->customer_id, 'contact_id' => $contact->id, 'mailbox_id' => Mailbox::factory()->create()->id]);
    $message = TicketMessage::factory()->for($ticket)->create(['type' => 'mail_out', 'body' => '<div>Ihr Drucker ist repariert.</div>', 'to' => [['email' => $contact->email, 'name' => null]]]);

    return new TicketMessageMail($message);
}

it('brands ticket mails with company name, accent color, reply hint and footer', function () {
    $this->settings->set(['company_name' => 'Muster IT', 'accent' => 'green', 'mail_footer' => "Muster IT GmbH · Hauptstraße 1\nTelefon 0123 456"]);

    $html = sampleTicketMail()->render();

    expect($html)->toContain('Muster IT')
        ->toContain('Ihr Drucker ist repariert.')
        ->toContain('background-color:#1f7f37')
        ->toContain('Bitte antworten Sie direkt auf diese E-Mail')
        ->toContain('Muster IT GmbH · Hauptstraße 1<br />')
        ->toContain('Ticket#');
});

it('uses the custom reply hint', function () {
    $this->settings->set(['mail_reply_hint' => 'Antworten Sie einfach auf diese Nachricht.']);

    expect(sampleTicketMail()->render())->toContain('Antworten Sie einfach auf diese Nachricht.')
        ->not->toContain('damit Ihre Nachricht dem Ticket zugeordnet wird');
});

it('embeds the logo when sending and links it in previews', function () {
    $this->actingAs(User::factory()->admin()->create());
    Livewire::test(BrandingSettings::class)->set('logo', UploadedFile::fake()->image('logo.png', 64, 64))->call('save');

    // Laravel's render() inlines embedded images as data URIs; the admin preview links the public URL.
    expect(sampleTicketMail()->render())->toContain('src="data:image/png;base64,');
    $this->get('/intern/admin/einstellungen/mail-vorschau')->assertSee(app(AppSettings::class)->logoUrl(), false);

    Mail::mailer('array')->send(sampleTicketMail());
    $sent = collect(app('mail.manager')->mailer('array')->getSymfonyTransport()->messages())->last()->getOriginalMessage();

    expect($sent->getHtmlBody())->toContain('src="cid:')
        ->and(collect($sent->getAttachments())->contains(fn ($part) => $part->getDisposition() === 'inline'))->toBeTrue();
});

it('renders staff notifications in the same layout', function () {
    $user = User::factory()->create(['name' => 'Bert']);
    $mail = (new Illuminate\Auth\Notifications\ResetPassword('token-123'))->toMail($user);

    expect($mail->view)->toBe(['mail.notification', 'mail.notification-text'])
        ->and($mail->subject)->toBe('Passwort zurücksetzen')
        ->and(view('mail.notification', $mail->data())->render())
        ->toContain('Hallo Bert,')
        ->toContain('/intern/reset-password/token-123')
        ->toContain('Passwort zurücksetzen');
});

it('takes the portal password mail from its system template', function () {
    $this->seed(MailTemplateSeeder::class);
    MailTemplate::where('key', 'portal_password_reset')->update(['subject' => 'Neues Passwort für {firma.name}']);
    $contact = Contact::factory()->withPortalAccess()->create(['salutation' => 'frau', 'last_name' => 'Berg']);

    $notification = new ResetPassword('abc');
    $mail = $notification->toMail($contact);

    expect($mail->subject)->toBe('Neues Passwort für ServiceDesk')
        ->and($mail->viewData['body'])->toContain('Sehr geehrte Frau Berg,')
        ->toContain('href="'.e($notification->url($contact)).'"')
        ->toContain('Passwort festlegen');
});

it('upgrades system templates that still hold an earlier default text', function () {
    $this->seed(MailTemplateSeeder::class);
    MailTemplate::where('key', 'ticket_closed')->update(['body' => Html::sanitize(SystemTemplate::TicketClosed->previousDefaultBody())]);
    MailTemplate::where('key', 'status_changed')->update(['body' => '<div>Eigener Text</div>']);

    $this->seed(MailTemplateSeeder::class);

    expect(MailTemplate::where('key', 'ticket_closed')->value('body'))->toContain('Ihr Team von {firma.name}')
        ->and(MailTemplate::where('key', 'status_changed')->value('body'))->toBe('<div>Eigener Text</div>');
});

it('offers the company name and a button as placeholders', function () {
    $placeholders = new Placeholders(portalLink: 'https://example.org/einladung');

    expect($placeholders->text('{firma.name}'))->toBe('ServiceDesk')
        ->and($placeholders->html('{portal.button}'))->toContain('href="https://example.org/einladung"')->toContain('Passwort festlegen')
        ->and($placeholders->text('{portal.button}'))->toBe('https://example.org/einladung');
});

it('keeps link addresses in the plain text version', function () {
    expect(Html::toText('<div>Bitte <a href="https://example.org/a?b=1&amp;c=2">Passwort festlegen</a>, siehe <a href="https://example.org">https://example.org</a></div>'))
        ->toBe('Bitte Passwort festlegen (https://example.org/a?b=1&c=2), siehe https://example.org');
});
