<div>
    <x-ui.page-header title="Benutzer" description="Mitarbeiterkonten, Rollen und Gruppen verwalten.">
        <x-slot:actions>
            @can('create', \App\Models\User::class)
                <x-ui.button icon="plus" wire:click="create">Neuer Benutzer</x-ui.button>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    <div class="mb-4 flex flex-wrap items-center gap-3">
        <x-ui.input wire:model.live.debounce.300ms="search" type="search" placeholder="Name oder E-Mail …" aria-label="Benutzer durchsuchen" class="w-full sm:w-72" />
        <x-ui.select wire:model.live="status" aria-label="Status" class="w-40" :options="['active' => 'Aktive', 'inactive' => 'Deaktivierte', 'all' => 'Alle']" />
    </div>

    <x-ui.card :padding="false">
        @if ($this->users->isEmpty())
            @if ($search !== '' || $status !== 'active')
                <x-ui.empty-state icon="magnifying-glass" title="Keine Treffer" description="Kein Benutzer passt zu Suche und Filter." />
            @else
                <x-ui.empty-state icon="users" title="Noch keine Benutzer" />
            @endif
        @else
            <x-ui.table>
                <x-slot:head>
                    <x-ui.table.heading sortable :direction="$sortBy === 'name' ? $sortDirection : null" wire:click="sort('name')">Name</x-ui.table.heading>
                    <x-ui.table.heading class="hidden md:table-cell">Rollen</x-ui.table.heading>
                    <x-ui.table.heading class="hidden lg:table-cell">Gruppen</x-ui.table.heading>
                    <x-ui.table.heading class="hidden sm:table-cell">Status</x-ui.table.heading>
                    <x-ui.table.heading><span class="sr-only">Aktionen</span></x-ui.table.heading>
                </x-slot:head>

                @foreach ($this->users as $user)
                    <x-ui.table.row wire:key="user-{{ $user->id }}" @class(['opacity-60' => ! $user->is_active])>
                        <x-ui.table.cell>
                            <p class="font-medium">
                                {{ $user->name }}
                                @if ($user->is(auth()->user()))
                                    <span class="ml-1 text-[12px] font-normal text-ink-faint">(du)</span>
                                @endif
                            </p>
                            <p class="text-[13px] text-ink-muted">{{ $user->email }}</p>
                        </x-ui.table.cell>
                        <x-ui.table.cell class="hidden md:table-cell">
                            <div class="flex flex-wrap gap-1">
                                @forelse ($user->roles as $role)
                                    <x-ui.badge color="blue">{{ $role->name }}</x-ui.badge>
                                @empty
                                    <span class="text-[13px] text-ink-faint">Keine</span>
                                @endforelse
                            </div>
                        </x-ui.table.cell>
                        <x-ui.table.cell class="hidden text-[13px] text-ink-muted lg:table-cell">
                            {{ $user->groups->pluck('name')->sort()->join(', ') ?: '–' }}
                        </x-ui.table.cell>
                        <x-ui.table.cell class="hidden sm:table-cell">
                            <div class="flex flex-wrap gap-1">
                                @if ($user->is_active)
                                    <x-ui.badge color="green" dot>Aktiv</x-ui.badge>
                                @else
                                    <x-ui.badge dot>Deaktiviert</x-ui.badge>
                                @endif
                                @if ($user->hasTwoFactorEnabled())
                                    <x-ui.badge color="indigo">2FA</x-ui.badge>
                                @endif
                            </div>
                        </x-ui.table.cell>
                        <x-ui.table.cell class="text-right whitespace-nowrap">
                            <div class="flex items-center justify-end gap-1">
                                @can('update', $user)
                                    <x-ui.button variant="ghost" size="sm" wire:click="edit({{ $user->id }})">Bearbeiten</x-ui.button>
                                @endcan

                                @if (auth()->user()->can('deactivate', $user) || (auth()->user()->can('resetTwoFactor', $user) && $user->hasTwoFactorEnabled()))
                                    <div x-data="{ open: false }" class="relative" x-on:keydown.escape="open = false">
                                        <button type="button" x-on:click="open = ! open" x-ref="trigger" x-bind:aria-expanded="open" class="rounded-lg p-1.5 text-ink-muted hover:bg-surface-muted hover:text-ink focus-visible:outline-2 focus-visible:outline-accent">
                                            <x-heroicon-m-ellipsis-horizontal class="size-5" aria-hidden="true" />
                                            <span class="sr-only">Weitere Aktionen für {{ $user->name }}</span>
                                        </button>
                                        <div x-show="open" x-cloak x-anchor.bottom-end.offset.4.fixed="$refs.trigger" x-on:click.outside="open = false" x-transition.opacity class="z-40 w-60 rounded-xl bg-surface p-1 text-left shadow-pop">
                                            @can('deactivate', $user)
                                                <button type="button" x-on:click="open = false" wire:click="toggleActive({{ $user->id }})"
                                                    @if ($user->is_active) wire:confirm="{{ $user->name }} deaktivieren? Die Person kann sich danach nicht mehr anmelden." @endif
                                                    class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-sm hover:bg-surface-muted">
                                                    @if ($user->is_active)
                                                        <x-heroicon-o-no-symbol class="size-4 text-ink-muted" aria-hidden="true" /> Deaktivieren
                                                    @else
                                                        <x-heroicon-o-check-circle class="size-4 text-ink-muted" aria-hidden="true" /> Aktivieren
                                                    @endif
                                                </button>
                                            @endcan
                                            @if (auth()->user()->can('resetTwoFactor', $user) && $user->hasTwoFactorEnabled())
                                                <button type="button" x-on:click="open = false" wire:click="resetTwoFactor({{ $user->id }})"
                                                    wire:confirm="Zwei-Faktor-Authentifizierung von {{ $user->name }} zurücksetzen? Die Person meldet sich danach nur mit Passwort an und kann 2FA neu einrichten."
                                                    class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-sm hover:bg-surface-muted">
                                                    <x-heroicon-o-shield-exclamation class="size-4 text-ink-muted" aria-hidden="true" /> 2FA zurücksetzen
                                                </button>
                                            @endif
                                        </div>
                                    </div>
                                @endif
                            </div>
                        </x-ui.table.cell>
                    </x-ui.table.row>
                @endforeach
            </x-ui.table>

            @if ($this->users->hasPages())
                <div class="border-t border-separator px-5 py-3">
                    {{ $this->users->links() }}
                </div>
            @endif
        @endif
    </x-ui.card>

    <x-ui.modal name="user-form" wire:model="showForm" max-width="xl" :title="$editingId ? 'Benutzer bearbeiten' : 'Neuer Benutzer'">
        @php
            $editingSelf = $this->editing?->is(auth()->user()) ?? false;
        @endphp

        <form wire:submit="save" id="user-form" class="space-y-5">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.input label="Name" wire:model="name" required autocomplete="off" />
                <x-ui.input label="E-Mail-Adresse" wire:model="email" type="email" required autocomplete="off" />
            </div>

            <div x-data="{ visible: false }" x-on:password-generated.window="visible = true">
                <div class="flex items-end gap-2">
                    <x-ui.input
                        :label="$editingId ? 'Neues Passwort (optional)' : 'Startpasswort'"
                        wire:model="password"
                        x-bind:type="visible ? 'text' : 'password'"
                        type="password"
                        autocomplete="new-password"
                        :required="! $editingId"
                        class="flex-1"
                    />
                    <x-ui.button variant="secondary" x-on:click="visible = ! visible" x-bind:aria-pressed="visible">
                        <span x-text="visible ? 'Verbergen' : 'Anzeigen'">Anzeigen</span>
                    </x-ui.button>
                    <x-ui.button variant="secondary" icon="sparkles" wire:click="generatePassword" x-on:click="visible = true">Generieren</x-ui.button>
                </div>
                <p class="mt-1.5 text-[13px] text-ink-muted">Mindestens 10 Zeichen mit Buchstaben und Zahlen. Bitte sicher an die Person weitergeben; sie kann es unter „Sicherheit“ ändern.</p>
            </div>

            <fieldset>
                <legend class="text-[13px] font-medium">Rollen</legend>
                @if ($editingSelf)
                    <p class="mt-1 text-[13px] text-ink-muted">Die eigenen Rollen können nicht geändert werden.</p>
                @endif
                <div class="mt-2 grid gap-2 sm:grid-cols-2">
                    @foreach ($this->availableRoles as $role)
                        <x-ui.checkbox
                            wire:key="role-{{ $role->id }}"
                            :label="$role->name"
                            :description="$role->permissions_count.' Rechte'"
                            wire:model="roles"
                            value="{{ $role->name }}"
                            :disabled="$editingSelf"
                        />
                    @endforeach
                </div>
                @error('roles.*') <p class="mt-1.5 text-[13px] text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
            </fieldset>

            <fieldset>
                <legend class="text-[13px] font-medium">Gruppen</legend>
                @if ($this->availableGroups->isEmpty())
                    <p class="mt-1 text-[13px] text-ink-muted">Noch keine Gruppen angelegt.</p>
                @else
                    <div class="mt-2 grid max-h-48 gap-2 overflow-y-auto sm:grid-cols-2">
                        @foreach ($this->availableGroups as $group)
                            <x-ui.checkbox wire:key="group-{{ $group->id }}" :label="$group->name" wire:model="groups" value="{{ $group->id }}" />
                        @endforeach
                    </div>
                @endif
                @error('groups.*') <p class="mt-1.5 text-[13px] text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
            </fieldset>

            @if ($editingId && ! $editingSelf)
                <x-ui.checkbox label="Aktiv" description="Deaktivierte Benutzer können sich nicht anmelden. Ihre Tickets und der Verlauf bleiben erhalten." wire:model="isActive" />
            @endif
        </form>

        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="show = false">Abbrechen</x-ui.button>
            <x-ui.button type="submit" form="user-form">Speichern</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>
</div>
