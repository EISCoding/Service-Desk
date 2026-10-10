<div>
    <x-ui.page-header title="Kunden" description="Firmen, Ansprechpartner und Notizen." />

    <div class="mb-4 flex flex-wrap items-center gap-3">
        <x-ui.input wire:model.live.debounce.300ms="search" type="search" placeholder="Firma, Nummer, Ort, Ansprechpartner …" aria-label="Kunden durchsuchen" class="w-full sm:w-80" />
        <x-ui.select wire:model.live="status" aria-label="Status" class="w-40" :options="['active' => 'Aktive', 'archived' => 'Archivierte', 'all' => 'Alle']" />
    </div>

    <x-ui.card :padding="false">
        @if ($this->customers->isEmpty())
            @if ($search !== '' || $status !== 'active')
                <x-ui.empty-state icon="magnifying-glass" title="Keine Treffer" description="Kein Kunde passt zu Suche und Filter." />
            @else
                <x-ui.empty-state icon="building-office-2" title="Noch keine Kunden" description="Lege den ersten Kunden an." />
            @endif
        @else
            <x-ui.table>
                <x-slot:head>
                    <x-ui.table.heading sortable :direction="$sortBy === 'number' ? $sortDirection : null" wire:click="sort('number')" class="w-32">Nr.</x-ui.table.heading>
                    <x-ui.table.heading sortable :direction="$sortBy === 'company' ? $sortDirection : null" wire:click="sort('company')">Firma</x-ui.table.heading>
                    <x-ui.table.heading sortable :direction="$sortBy === 'city' ? $sortDirection : null" wire:click="sort('city')" class="hidden md:table-cell">Ort</x-ui.table.heading>
                    <x-ui.table.heading class="hidden lg:table-cell">Kontakt</x-ui.table.heading>
                    <x-ui.table.heading class="hidden sm:table-cell">Ansprechpartner</x-ui.table.heading>
                </x-slot:head>

                @foreach ($this->customers as $customer)
                    <x-ui.table.row wire:key="customer-{{ $customer->id }}" @class(['opacity-60' => $customer->isArchived()])>
                        <x-ui.table.cell class="font-mono text-[13px] text-ink-muted">{{ $customer->number }}</x-ui.table.cell>
                        <x-ui.table.cell>
                            <a href="{{ route('intern.customers.show', $customer) }}" wire:navigate class="font-medium hover:text-accent">{{ $customer->company }}</a>
                            @if ($customer->isArchived())
                                <x-ui.badge class="ml-1.5">Archiviert</x-ui.badge>
                            @endif
                            <p class="text-[13px] text-ink-muted md:hidden">{{ trim("{$customer->postal_code} {$customer->city}") }}</p>
                        </x-ui.table.cell>
                        <x-ui.table.cell class="hidden text-ink-muted md:table-cell">{{ trim("{$customer->postal_code} {$customer->city}") ?: '–' }}</x-ui.table.cell>
                        <x-ui.table.cell class="hidden text-[13px] text-ink-muted lg:table-cell">
                            <p>{{ $customer->email }}</p>
                            <p>{{ $customer->phone }}</p>
                        </x-ui.table.cell>
                        <x-ui.table.cell class="hidden text-ink-muted sm:table-cell">{{ $customer->contacts_count }}</x-ui.table.cell>
                    </x-ui.table.row>
                @endforeach
            </x-ui.table>

            @if ($this->customers->hasPages())
                <div class="border-t border-separator px-5 py-3">
                    {{ $this->customers->links() }}
                </div>
            @endif
        @endif
    </x-ui.card>
</div>
