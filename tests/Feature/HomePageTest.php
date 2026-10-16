<?php

use App\Models\Contact;
use App\Models\Mailbox;
use App\Models\User;
use App\Support\AppSettings;

it('shows the home page with the ways to reach the support', function () {
    Mailbox::factory()->create(['email' => 'support@firma.test', 'is_active' => true, 'is_default' => true]);

    $this->get('/')
        ->assertOk()
        ->assertSee('<title>Support · ServiceDesk</title>', false)
        ->assertSee(['Wie können wir helfen?', 'Hier erreichen Sie den Support von ServiceDesk', 'Kundenportal', 'So erreichen Sie uns'])
        ->assertSee(route('portal.login'), false)
        ->assertSee('href="mailto:support@firma.test"', false)
        ->assertSee(route('login'), false)
        ->assertSee('Mitarbeiter-Anmeldung')
        ->assertDontSee('Telefon')
        ->assertDontSee('Impressum');
});

it('leaves out the e-mail way without an active mailbox', function () {
    Mailbox::factory()->create(['email' => 'alt@firma.test', 'is_active' => false]);

    $this->get('/')->assertOk()->assertDontSee('alt@firma.test')->assertDontSee('E-Mail schreiben');
});

it('uses the texts from the admin area', function () {
    app(AppSettings::class)->set([
        'home_heading' => 'IT-Support der Musterfirma',
        'home_intro' => '<p>Wir sind für Sie da. <strong>Schnell.</strong></p><script>alert(1)</script>',
        'home_phone' => '+49 7331 12 34-56',
        'home_hours' => 'Mo–Fr 8–17 Uhr',
        'home_imprint_url' => 'https://musterfirma.de/impressum',
        'home_privacy_url' => 'https://musterfirma.de/datenschutz',
    ]);
    app()->forgetInstance(AppSettings::class);

    $this->get('/')
        ->assertOk()
        ->assertSee('IT-Support der Musterfirma')
        ->assertSee('<p>Wir sind für Sie da. <strong>Schnell.</strong></p>', false)
        ->assertDontSee('<script>alert(1)</script>', false)
        ->assertSee('href="tel:+497331123456"', false)
        ->assertSee('Mo–Fr 8–17 Uhr')
        ->assertSee('href="https://musterfirma.de/impressum"', false)
        ->assertSee('href="https://musterfirma.de/datenschutz"', false);
});

it('offers the way back to signed-in staff and customers', function () {
    $this->actingAs(User::factory()->staff()->create())
        ->get('/')->assertOk()->assertSee('Zum Mitarbeiterbereich')->assertSee(route('intern.dashboard'), false);

    auth('web')->logout();
    $contact = Contact::factory()->withPortalAccess()->create();

    $this->actingAs($contact, 'contact')
        ->get('/')->assertOk()->assertSee('Zu meinen Anfragen')->assertSee(route('portal.tickets.create'), false)->assertDontSee('Zum Mitarbeiterbereich');
});
