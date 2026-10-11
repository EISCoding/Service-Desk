<?php

use App\Livewire\Concerns\InteractsWithToasts;
use Livewire\Component;
use Livewire\Livewire;

describe('button', function () {
    it('renders a non-submitting button by default', function () {
        $this->blade('<x-ui.button>Speichern</x-ui.button>')
            ->assertSee('type="button"', false)
            ->assertSee('Speichern')
            ->assertSee('bg-accent', false);
    });

    it('renders a link when an href is given', function () {
        $this->blade('<x-ui.button href="/intern" variant="secondary">Zurück</x-ui.button>')
            ->assertSee('<a href="/intern"', false)
            ->assertDontSee('<button', false)
            ->assertSee('ring-ink/12', false);
    });

    it('renders the danger variant and an icon', function () {
        $this->blade('<x-ui.button variant="danger" icon="trash" type="submit">Löschen</x-ui.button>')
            ->assertSee('type="submit"', false)
            ->assertSee('bg-red-600', false)
            ->assertSee('<svg', false);
    });
});

describe('form fields', function () {
    it('links the label to the input and derives the name from wire:model', function () {
        $this->blade('<x-ui.input label="E-Mail" wire:model="email" type="email" />')
            ->assertSee('for="f-email"', false)
            ->assertSee('id="f-email"', false)
            ->assertSee('name="email"', false)
            ->assertSee('type="email"', false);
    });

    it('shows validation errors accessibly', function () {
        $this->withViewErrors(['email' => 'Bitte eine gültige E-Mail-Adresse eingeben.'])
            ->blade('<x-ui.input label="E-Mail" name="email" />')
            ->assertSee('Bitte eine gültige E-Mail-Adresse eingeben.')
            ->assertSee('aria-invalid="true"', false)
            ->assertSee('aria-describedby="f-email-error"', false);
    });

    it('shows a hint when there is no error', function () {
        $this->blade('<x-ui.input label="Name" name="name" hint="Vor- und Nachname" />')
            ->assertSee('Vor- und Nachname')
            ->assertSee('aria-describedby="f-name-hint"', false);
    });

    it('marks required fields', function () {
        $this->blade('<x-ui.input label="Name" name="name" required />')
            ->assertSee('required', false)
            ->assertSee('aria-hidden="true">*</span>', false);
    });

    it('renders select options with a placeholder', function () {
        $this->blade('<x-ui.select label="Rolle" name="role" placeholder="Bitte wählen" :options="[\'admin\' => \'Admin\', \'staff\' => \'Mitarbeiter\']" />')
            ->assertSeeInOrder(['Bitte wählen', 'value="admin"', 'Admin', 'value="staff"', 'Mitarbeiter'], false);
    });

    it('renders textarea content and errors', function () {
        $this->withViewErrors(['note' => 'Zu lang.'])
            ->blade('<x-ui.textarea label="Notiz" name="note">Hallo</x-ui.textarea>')
            ->assertSee('>Hallo</textarea>', false)
            ->assertSee('Zu lang.');
    });

    it('renders a checkbox with label and description', function () {
        $this->blade('<x-ui.checkbox label="Aktiv" description="Kann sich anmelden" wire:model="active" />')
            ->assertSee('type="checkbox"', false)
            ->assertSee('wire:model="active"', false)
            ->assertSee('Kann sich anmelden');
    });
});

it('renders a card with header, actions and footer', function () {
    $this->blade(<<<'BLADE'
        <x-ui.card title="Benutzer" description="Alle Mitarbeiter">
            <x-slot:actions><span>Aktion</span></x-slot:actions>
            Inhalt
            <x-slot:footer>Fuß</x-slot:footer>
        </x-ui.card>
        BLADE)
        ->assertSeeInOrder(['Benutzer', 'Alle Mitarbeiter', 'Aktion', 'Inhalt', 'Fuß']);
});

it('renders pills in the given color and falls back to gray', function () {
    $this->blade('<x-ui.badge color="green" dot>Aktiv</x-ui.badge>')
        ->assertSee('text-green-700', false)
        ->assertSee('bg-green-500', false);

    $this->blade('<x-ui.badge color="unknown">X</x-ui.badge>')
        ->assertSee('text-zinc-700', false);
});

it('renders an accessible modal that listens for its name', function () {
    $this->blade('<x-ui.modal name="confirm" title="Wirklich?">Text</x-ui.modal>')
        ->assertSee('role="dialog"', false)
        ->assertSee('aria-modal="true"', false)
        ->assertSee('aria-labelledby="modal-confirm-title"', false)
        ->assertSee('id="modal-confirm-title"', false)
        ->assertSee("\$event.detail?.name === 'confirm'", false)
        ->assertSee('x-trap.inert.noscroll', false);
});

it('renders tables with sortable headings', function () {
    $view = $this->blade(<<<'BLADE'
        <x-ui.table>
            <x-slot:head>
                <x-ui.table.heading sortable direction="desc" wire:click="sortBy('name')">Name</x-ui.table.heading>
                <x-ui.table.heading>E-Mail</x-ui.table.heading>
            </x-slot:head>
            <x-ui.table.row><x-ui.table.cell>Anna</x-ui.table.cell></x-ui.table.row>
        </x-ui.table>
        BLADE)
        ->assertSee('scope="col"', false)
        ->assertSee('aria-sort="descending"', false)
        ->assertSee('<button type="button" wire:click="sortBy(\'name\')"', false)
        ->assertSeeInOrder(['Name', 'E-Mail', 'Anna']);

    // The click handler belongs to the button only, not to the <th> as well.
    expect(substr_count((string) $view, 'wire:click'))->toBe(1);
});

it('renders an empty state with action', function () {
    $this->blade('<x-ui.empty-state icon="users" title="Keine Benutzer" description="Lege den ersten an."><a>Anlegen</a></x-ui.empty-state>')
        ->assertSeeInOrder(['<svg', 'Keine Benutzer', 'Lege den ersten an.', 'Anlegen'], false);
});

it('passes a flashed toast to the toast container', function () {
    session()->flash('toast', ['message' => 'Gespeichert.', 'type' => 'success']);

    $this->blade('<x-ui.toasts />')
        ->assertSee('aria-live="polite"', false)
        ->assertSee('Gespeichert.');
});

it('dispatches toasts from Livewire components', function () {
    $component = new class extends Component
    {
        use InteractsWithToasts;

        public function save(): void
        {
            $this->toast('Gespeichert.');
        }

        public function render(): string
        {
            return '<div></div>';
        }
    };

    Livewire::test($component::class)
        ->call('save')
        ->assertDispatched('toast', message: 'Gespeichert.', type: 'success');
});

it('only exposes the styleguide locally', function () {
    $this->get('/intern/styleguide')->assertNotFound();
});

it('renders a segmented control bound to a property or an action', function () {
    $this->blade('<x-ui.segmented model="state" current="open" :options="[\'open\' => \'Offen\', \'all\' => [\'Alle\', 12]]" label="Status" />')
        ->assertSee('role="tablist"', false)
        ->assertSee('aria-label="Status"', false)
        ->assertSee('aria-selected="true"', false)
        ->assertSee("\$set('state', 'open')")
        ->assertSee("\$set('state', 'all')")
        ->assertSee('12');

    $this->blade('<x-ui.segmented action="setView(\'%s\')" current="mine" :options="[\'mine\' => \'Meine\']" />')
        ->assertSee("setView('mine')");
});

it('gives avatars a stable color per name', function () {
    $first = (string) $this->blade('<x-ui.avatar name="Anna Berg" />');

    expect($first)->toBe((string) $this->blade('<x-ui.avatar name="Anna Berg" />'))
        ->toContain('AB')
        ->toContain('bg-gradient-to-b')
        ->toMatch('/ from-[a-z]+-400 to-[a-z]+-[56]00 /');
});
