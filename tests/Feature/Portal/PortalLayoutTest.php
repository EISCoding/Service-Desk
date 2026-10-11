<?php

use App\Models\Contact;

it('uses the shared design system with a reduced navigation', function () {
    $contact = Contact::factory()->withPortalAccess()->create(['password' => 'Kundenportal2026']);

    $response = $this->actingAs($contact, 'contact')->get('/portal')->assertOk();

    foreach (['/portal', '/portal/tickets/neu', '/portal/profil'] as $path) {
        // Topbar (desktop) and tab bar (phones)
        expect(substr_count($response->getContent(), 'href="'.url($path).'"'))->toBeGreaterThanOrEqual(2);
    }

    $response->assertSee('Kundenportal')
        ->assertSee($contact->fullName())
        ->assertSee($contact->customer->company)
        ->assertSee('Abmelden')
        ->assertSee('rounded-card', false)
        ->assertDontSee('/intern/kunden')
        ->assertDontSee('/intern/admin')
        ->assertDontSee('Vorlagen');
});
