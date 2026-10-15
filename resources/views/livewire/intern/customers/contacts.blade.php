<div>
    @can('create', \App\Models\Contact::class)
        <div class="mb-4 flex justify-end">
            <x-ui.button icon="plus" wire:click="create">Neuer Ansprechpartner</x-ui.button>
        </div>
    @endcan

    <x-ui.card :padding="false">
        @if ($this->contacts->isEmpty())
            <x-ui.empty-state icon="user" title="Noch keine Ansprechpartner" description="Lege Ansprechpartner an, um Tickets und Portal-Zugänge zuzuordnen." />
        @else
            <x-ui.table>
                <x-slot:head>
                    <x-ui.table.heading>Name</x-ui.table.heading>
                    <x-ui.table.heading class="hidden md:table-cell">Kontakt</x-ui.table.heading>
                    <x-ui.table.heading class="hidden sm:table-cell">Status</x-ui.table.heading>
                    <x-ui.table.heading><span class="sr-only">Aktionen</span></x-ui.table.heading>
                </x-slot:head>

                @foreach ($this->contacts as $contact)
                    <x-ui.table.row wire:key="contact-{{ $contact->id }}">
                        <x-ui.table.cell>
                            <p class="font-medium">
                                @if ($contact->salutation)
                                    <span class="font-normal text-ink-muted">{{ $contact->salutation->label() }}</span>
                                @endif
                                {{ $contact->fullName() }}
                            </p>
                            @if ($contact->position)
                                <p class="text-[13px] text-ink-muted">{{ $contact->position }}</p>
                            @endif
                        </x-ui.table.cell>
                        <x-ui.table.cell class="hidden text-[13px] md:table-cell">
                            @if ($contact->email)
                                <a href="mailto:{{ $contact->email }}" class="text-accent hover:underline">{{ $contact->email }}</a>
                            @endif
                            <p class="text-ink-muted">{{ $contact->phone }}</p>
                        </x-ui.table.cell>
                        <x-ui.table.cell class="hidden sm:table-cell">
                            <div class="flex flex-wrap gap-1">
                                @if ($contact->is_primary)
                                    <x-ui.badge color="blue">Hauptansprechpartner</x-ui.badge>
                                @endif
                                @switch($contact->portalStatus())
                                    @case('active') <x-ui.badge color="green" dot>Portal aktiv</x-ui.badge> @break
                                    @case('invited') <x-ui.badge color="yellow" dot>Eingeladen</x-ui.badge> @break
                                    @case('not_invited') <x-ui.badge dot>Portal: nicht eingeladen</x-ui.badge> @break
                                @endswitch
                            </div>
                        </x-ui.table.cell>
                        <x-ui.table.cell class="text-right whitespace-nowrap">
                            <div class="flex items-center justify-end gap-1">
                                @can('update', $contact)
                                    <x-ui.button variant="ghost" size="sm" wire:click="edit({{ $contact->id }})">Bearbeiten</x-ui.button>
                                @endcan
                                <div x-data="{ open: false }" class="relative" x-on:keydown.escape="open = false">
                                    <button type="button" x-on:click="open = ! open" x-ref="trigger" x-bind:aria-expanded="open" class="rounded-lg p-1.5 text-ink-muted hover:bg-surface-muted hover:text-ink focus-visible:outline-2 focus-visible:outline-accent">
                                        <x-heroicon-m-ellipsis-horizontal class="size-5" aria-hidden="true" />
                                        <span class="sr-only">Weitere Aktionen für {{ $contact->fullName() }}</span>
                                    </button>
                                    <div x-show="open" x-cloak x-anchor.bottom-end.offset.4.fixed="$refs.trigger" x-on:click.outside="open = false" x-transition.opacity class="z-40 w-56 rounded-xl bg-surface p-1 text-left shadow-pop">
                                        @if ($contact->portal_access && $contact->email && $contact->portalStatus() !== 'active')
                                            @can('managePortal', $contact)
                                                <button type="button" x-on:click="open = false" wire:click="invite({{ $contact->id }})" class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-sm hover:bg-surface-muted">
                                                    <x-heroicon-o-paper-airplane class="size-4 text-ink-muted" aria-hidden="true" />
                                                    {{ $contact->invited_at ? 'Erneut einladen' : 'Ins Portal einladen' }}
                                                </button>
                                            @endcan
                                        @endif
                                        @can('delete', $contact)
                                            <button type="button" x-on:click="open = false" wire:click="delete({{ $contact->id }})" wire:confirm="Ansprechpartner {{ $contact->fullName() }} löschen?" class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-sm text-red-600 hover:bg-surface-muted dark:text-red-400">
                                                <x-heroicon-o-trash class="size-4" aria-hidden="true" /> Löschen
                                            </button>
                                        @endcan
                                    </div>
                                </div>
                            </div>
                        </x-ui.table.cell>
                    </x-ui.table.row>
                @endforeach
            </x-ui.table>
        @endif
    </x-ui.card>

    <x-ui.modal name="contact-form" wire:model="showForm" max-width="xl" :title="$editingId ? 'Ansprechpartner bearbeiten' : 'Neuer Ansprechpartner'">
        <form wire:submit="save" id="contact-form" class="space-y-4">
            <div class="grid gap-4 sm:grid-cols-6">
                <x-ui.select label="Anrede" wire:model="salutation" placeholder="–" :options="collect(\App\Enums\Salutation::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()])->all()" class="sm:col-span-2" />
                <x-ui.input label="Vorname" wire:model="first_name" class="sm:col-span-2" />
                <x-ui.input label="Nachname" wire:model="last_name" required class="sm:col-span-2" />
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.input label="E-Mail" wire:model="email" type="email" />
                <x-ui.input label="Telefon" wire:model="phone" type="tel" />
            </div>
            <x-ui.input label="Position" wire:model="position" placeholder="z. B. IT-Leitung" />

            <div class="space-y-3 rounded-xl bg-surface-muted/60 p-4">
                <x-ui.checkbox label="Hauptansprechpartner" description="Sieht im Kundenportal alle Tickets der Firma." wire:model="is_primary" />

                @if (auth()->user()->can('managePortal', $this->editing ?? new \App\Models\Contact))
                    <x-ui.checkbox label="Portal-Zugang" description="Kann sich im Kundenportal anmelden. Erfordert eine E-Mail-Adresse." wire:model="portal_access" />

                    @if (! $this->editing?->portal_access)
                        <div x-show="$wire.portal_access" x-cloak class="pl-7">
                            <x-ui.checkbox label="Einladung jetzt per E-Mail senden" description="Mit Link zum Festlegen des Passworts (7 Tage gültig)." wire:model="sendInvitation" />
                        </div>
                    @endif
                @endif
            </div>
        </form>

        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="show = false">Abbrechen</x-ui.button>
            <x-ui.button type="submit" form="contact-form">Speichern</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>
</div>
