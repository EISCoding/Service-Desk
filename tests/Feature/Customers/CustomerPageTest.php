<?php

use App\Livewire\Intern\Customers\Contacts;
use App\Livewire\Intern\Customers\History;
use App\Livewire\Intern\Customers\Notes;
use App\Livewire\Intern\Customers\Show;
use App\Livewire\Intern\Customers\Tickets;
use App\Models\Contact;
use App\Models\Customer;
use App\Models\CustomerNote;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->staff = User::factory()->staff()->create(['name' => 'Anna Becker']);
    $this->customer = Customer::factory()->create([
        'company' => 'Müller GmbH', 'street' => 'Hauptstraße 1', 'postal_code' => '10115', 'city' => 'Berlin',
    ]);
});

describe('page', function () {
    it('shows the customer under /intern/kunden/{id}', function () {
        $this->actingAs($this->staff)
            ->get("/intern/kunden/{$this->customer->id}")
            ->assertOk()
            ->assertSee('<title>Müller GmbH · ServiceDesk</title>', false)
            ->assertSeeInOrder(['Übersicht', 'Ansprechpartner', 'Tickets', 'Notizen', 'Verlauf'])
            ->assertSee('K-10001')
            ->assertSee('Hauptstraße 1');
    });

    it('is forbidden without customers.manage and 404 for unknown customers', function () {
        $this->actingAs(User::factory()->create())->get("/intern/kunden/{$this->customer->id}")->assertForbidden();
        $this->actingAs($this->staff)->get('/intern/kunden/999')->assertNotFound();
    });

    it('is linked from the customer list', function () {
        $this->actingAs($this->staff)->get('/intern/kunden')->assertSee(route('intern.customers.show', $this->customer));
    });

    it('shows primary contacts and pinned notes in the overview', function () {
        Contact::factory()->for($this->customer)->primary()->create(['first_name' => 'Petra', 'last_name' => 'Primär']);
        Contact::factory()->for($this->customer)->create(['first_name' => 'Nico', 'last_name' => 'Neben']);
        CustomerNote::factory()->for($this->customer)->pinned()->create(['body' => 'Nur vormittags anrufen']);
        CustomerNote::factory()->for($this->customer)->create(['body' => 'Unwichtig']);

        Livewire::actingAs($this->staff)->test(Show::class, ['customer' => $this->customer])
            ->assertSee('Petra Primär')
            ->assertDontSee('Nico Neben')
            ->assertSee('Nur vormittags anrufen')
            ->assertDontSee('Unwichtig');
    });

    it('opens tabs and falls back to the overview for unknown tabs', function () {
        Livewire::actingAs($this->staff)->withQueryParams(['tab' => 'notizen'])
            ->test(Show::class, ['customer' => $this->customer])
            ->assertSet('tab', 'notizen')
            ->assertSeeLivewire(Notes::class)
            ->call('showTab', 'ansprechpartner')
            ->assertSeeLivewire(Contacts::class)
            ->call('showTab', 'verlauf')
            ->assertSeeLivewire(History::class)
            ->call('showTab', 'tickets')
            ->assertSeeLivewire(Tickets::class)
            ->call('showTab', 'unsinn')
            ->assertSet('tab', 'uebersicht');

        Livewire::actingAs($this->staff)->withQueryParams(['tab' => 'unsinn'])
            ->test(Show::class, ['customer' => $this->customer])
            ->assertSet('tab', 'uebersicht');
    });
});

describe('contacts tab', function () {
    it('lists contacts with primary and portal badges', function () {
        Contact::factory()->for($this->customer)->primary()->withPortalAccess()->create(['last_name' => 'Primär', 'invited_at' => now()]);
        Contact::factory()->for($this->customer)->create(['last_name' => 'Neben']);
        Contact::factory()->create(['last_name' => 'Fremd']);

        Livewire::actingAs($this->staff)->test(Contacts::class, ['customer' => $this->customer])
            ->assertSeeInOrder(['Primär', 'Hauptansprechpartner', 'Eingeladen', 'Neben'])
            ->assertDontSee('Fremd');
    });
});

describe('notes tab', function () {
    it('adds a note with the current user as author', function () {
        Livewire::actingAs($this->staff)->test(Notes::class, ['customer' => $this->customer])
            ->set('body', "Zeile 1\nZeile 2")
            ->call('add')
            ->assertHasNoErrors()
            ->assertSet('body', '')
            ->assertDispatched('notes-changed')
            ->assertSee('Zeile 1');

        $note = $this->customer->notes()->sole();
        expect($note->author->is($this->staff))->toBeTrue()
            ->and($note->body)->toBe("Zeile 1\nZeile 2");
    });

    it('requires text', function () {
        Livewire::actingAs($this->staff)->test(Notes::class, ['customer' => $this->customer])
            ->call('add')
            ->assertHasErrors('body');
    });

    it('lets every staff member pin notes', function () {
        $note = CustomerNote::factory()->for($this->customer)->create();

        Livewire::actingAs($this->staff)->test(Notes::class, ['customer' => $this->customer])
            ->call('togglePin', $note->id);

        expect($note->refresh()->is_pinned)->toBeTrue();
    });

    it('lets authors edit and delete their own notes', function () {
        $note = CustomerNote::factory()->for($this->customer)->for($this->staff, 'author')->create(['body' => 'Alt']);

        Livewire::actingAs($this->staff)->test(Notes::class, ['customer' => $this->customer])
            ->call('edit', $note->id)
            ->assertSet('editBody', 'Alt')
            ->set('editBody', 'Neu')
            ->call('saveEdit')
            ->assertHasNoErrors();
        expect($note->refresh()->body)->toBe('Neu');

        Livewire::actingAs($this->staff)->test(Notes::class, ['customer' => $this->customer])
            ->call('delete', $note->id);
        expect(CustomerNote::find($note->id))->toBeNull();
    });

    it('does not let others edit or delete a note', function () {
        $note = CustomerNote::factory()->for($this->customer)->create();

        Livewire::actingAs($this->staff)->test(Notes::class, ['customer' => $this->customer])
            ->assertDontSee('Bearbeiten')
            ->call('edit', $note->id)
            ->assertForbidden();

        Livewire::actingAs($this->staff)->test(Notes::class, ['customer' => $this->customer])
            ->call('delete', $note->id)
            ->assertForbidden();

        expect($note->fresh())->not->toBeNull();
    });

    it('cannot address notes of other customers', function () {
        $foreign = CustomerNote::factory()->for($this->staff, 'author')->create();

        Livewire::actingAs($this->staff)->test(Notes::class, ['customer' => $this->customer])
            ->call('delete', $foreign->id)
            ->assertNotFound();
    });
});

describe('history tab', function () {
    it('shows changes to the customer, its contacts and notes', function () {
        $this->actingAs($this->staff);
        $this->customer->update(['city' => 'Hamburg']);
        Contact::factory()->for($this->customer)->create(['first_name' => 'Petra', 'last_name' => 'Primär']);
        CustomerNote::factory()->for($this->customer)->create();

        Livewire::test(History::class, ['customer' => $this->customer])
            ->assertSeeInOrder([
                'Anna Becker', 'hat eine Notiz hinzugefügt',
                'Anna Becker', 'hat den Ansprechpartner Petra Primär angelegt',
                'Anna Becker', 'hat Kundendaten geändert', 'Ort:', 'Berlin', 'Hamburg',
                'System', 'hat den Kunden angelegt',
            ]);
    });

    it('does not show entries of other customers', function () {
        Customer::factory()->create(['company' => 'Andere AG']);
        Contact::factory()->create(['last_name' => 'Fremdling']);

        Livewire::actingAs($this->staff)->test(History::class, ['customer' => $this->customer])
            ->assertDontSee('Fremdling');
    });
});
