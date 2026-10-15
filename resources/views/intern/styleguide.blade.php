{{-- Local-only overview of the design system (route: /intern/styleguide). --}}
<x-layouts::base title="Design-System">
    <main x-data class="mx-auto max-w-5xl space-y-8 px-4 py-10 sm:px-6">
        <header class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">Design-System</h1>
                <p class="mt-1 text-sm text-ink-muted">Alle Blade-Komponenten unter <code>resources/views/components/ui</code>.</p>
            </div>
            <x-theme-switcher class="w-64" />
        </header>

        <x-ui.card title="Buttons" description="primary, secondary, ghost, danger – in sm, md, lg">
            <div class="flex flex-wrap items-center gap-3">
                <x-ui.button icon="plus">Neues Ticket</x-ui.button>
                <x-ui.button variant="secondary">Abbrechen</x-ui.button>
                <x-ui.button variant="ghost">Mehr</x-ui.button>
                <x-ui.button variant="danger" icon="trash">Löschen</x-ui.button>
                <x-ui.button size="sm">Klein</x-ui.button>
                <x-ui.button size="lg">Groß</x-ui.button>
                <x-ui.button disabled>Deaktiviert</x-ui.button>
            </div>
        </x-ui.card>

        <x-ui.card title="Formularfelder">
            <div class="grid gap-5 sm:grid-cols-2">
                <x-ui.input label="Name" name="demo_name" placeholder="Max Mustermann" required />
                <x-ui.input label="E-Mail" name="demo_email" type="email" hint="Wird für den Login verwendet." />
                <x-ui.select label="Priorität" name="demo_priority" placeholder="Bitte wählen" :options="['low' => 'Niedrig', 'normal' => 'Normal', 'high' => 'Hoch', 'urgent' => 'Dringend']" />
                <x-ui.textarea label="Notiz" name="demo_note" rows="3" placeholder="Interne Notiz …" />
                <x-ui.checkbox label="Aktiv" description="Deaktivierte Benutzer können sich nicht anmelden." checked />
            </div>
        </x-ui.card>

        <x-ui.card title="Pills">
            <div class="flex flex-wrap gap-2">
                <x-ui.badge color="blue" dot>Neu</x-ui.badge>
                <x-ui.badge color="indigo" dot>In Arbeit</x-ui.badge>
                <x-ui.badge color="yellow" dot>Warte auf Kunden</x-ui.badge>
                <x-ui.badge color="orange" dot>Warte auf Intern</x-ui.badge>
                <x-ui.badge color="purple" dot>Warte auf Extern</x-ui.badge>
                <x-ui.badge color="teal" dot>VOS</x-ui.badge>
                <x-ui.badge color="gray" dot>Geschlossen</x-ui.badge>
                <x-ui.badge color="red">Dringend</x-ui.badge>
                <x-ui.badge color="green">Aktiv</x-ui.badge>
            </div>
        </x-ui.card>

        <x-ui.card title="Tabelle" :padding="false">
            <x-ui.table>
                <x-slot:head>
                    <x-ui.table.heading sortable direction="asc">Name</x-ui.table.heading>
                    <x-ui.table.heading sortable>E-Mail</x-ui.table.heading>
                    <x-ui.table.heading>Rolle</x-ui.table.heading>
                </x-slot:head>
                <x-ui.table.row>
                    <x-ui.table.cell class="font-medium">Anna Becker</x-ui.table.cell>
                    <x-ui.table.cell class="text-ink-muted">anna@example.com</x-ui.table.cell>
                    <x-ui.table.cell><x-ui.badge color="blue">Admin</x-ui.badge></x-ui.table.cell>
                </x-ui.table.row>
                <x-ui.table.row>
                    <x-ui.table.cell class="font-medium">Jonas Weber</x-ui.table.cell>
                    <x-ui.table.cell class="text-ink-muted">jonas@example.com</x-ui.table.cell>
                    <x-ui.table.cell><x-ui.badge>Mitarbeiter</x-ui.badge></x-ui.table.cell>
                </x-ui.table.row>
            </x-ui.table>
        </x-ui.card>

        <div class="grid gap-8 sm:grid-cols-2">
            <x-ui.card title="Leerzustand" :padding="false">
                <x-ui.empty-state icon="purchase-tag" title="Keine Tickets" description="Sobald ein Ticket eingeht, erscheint es hier.">
                    <x-ui.button variant="secondary" size="sm">Ticket anlegen</x-ui.button>
                </x-ui.empty-state>
            </x-ui.card>

            <x-ui.card title="Modal und Toast">
                <div class="flex flex-wrap gap-3">
                    <x-ui.button x-on:click="$dispatch('open-modal', { name: 'demo' })">Modal öffnen</x-ui.button>
                    <x-ui.button variant="secondary" x-on:click="$dispatch('toast', { message: 'Änderungen gespeichert.' })">Toast</x-ui.button>
                    <x-ui.button variant="secondary" x-on:click="$dispatch('toast', { message: 'Das hat nicht geklappt.', type: 'error' })">Fehler-Toast</x-ui.button>
                </div>
            </x-ui.card>
        </div>
    </main>

    <x-ui.modal name="demo" title="Benutzer deaktivieren?" description="Der Benutzer kann sich danach nicht mehr anmelden.">
        <p class="text-sm text-ink-muted">Offene Tickets bleiben zugewiesen und können neu verteilt werden.</p>
        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="$dispatch('close-modal', { name: 'demo' })">Abbrechen</x-ui.button>
            <x-ui.button variant="danger">Deaktivieren</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>
</x-layouts::base>
