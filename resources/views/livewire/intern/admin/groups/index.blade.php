<div>
    <x-ui.page-header title="Gruppen" description="Teams, denen Tickets zugewiesen werden. Mitglieder sehen die Tickets ihrer Gruppen.">
        <x-slot:actions>
            @can('create', \App\Models\Group::class)
                <x-ui.button icon="plus" wire:click="create">Neue Gruppe</x-ui.button>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    <div class="mb-4 max-w-xs">
        <x-ui.input wire:model.live.debounce.300ms="search" type="search" placeholder="Gruppen durchsuchen …" aria-label="Gruppen durchsuchen" />
    </div>

    <x-ui.card :padding="false">
        @if ($this->groups->isEmpty())
            @if ($search !== '')
                <x-ui.empty-state icon="magnifying-glass" title="Keine Treffer" description="Keine Gruppe passt zu „{{ $search }}“." />
            @else
                <x-ui.empty-state icon="user-group" title="Noch keine Gruppen" description="Lege Gruppen wie „Support“ oder „Technik“ an und weise ihnen Mitarbeiter zu.">
                    <x-ui.button variant="secondary" size="sm" icon="plus" wire:click="create">Neue Gruppe</x-ui.button>
                </x-ui.empty-state>
            @endif
        @else
            <x-ui.table>
                <x-slot:head>
                    <x-ui.table.heading>Name</x-ui.table.heading>
                    <x-ui.table.heading>Mitglieder</x-ui.table.heading>
                    <x-ui.table.heading><span class="sr-only">Aktionen</span></x-ui.table.heading>
                </x-slot:head>

                @foreach ($this->groups as $group)
                    <x-ui.table.row wire:key="group-{{ $group->id }}">
                        <x-ui.table.cell>
                            <p class="font-medium">{{ $group->name }}</p>
                            @if ($group->description)
                                <p class="mt-0.5 text-[13px] text-ink-muted">{{ $group->description }}</p>
                            @endif
                        </x-ui.table.cell>
                        <x-ui.table.cell class="text-ink-muted">
                            @if ($group->users_count === 0)
                                <span class="text-ink-faint">Keine</span>
                            @else
                                <span class="font-medium text-ink">{{ $group->users_count }}</span>
                                <span class="hidden sm:inline">· {{ $group->users->take(3)->pluck('name')->join(', ') }}{{ $group->users_count > 3 ? ' …' : '' }}</span>
                            @endif
                        </x-ui.table.cell>
                        <x-ui.table.cell class="text-right whitespace-nowrap">
                            @can('update', $group)
                                <x-ui.button variant="ghost" size="sm" wire:click="edit({{ $group->id }})">Bearbeiten</x-ui.button>
                            @endcan
                            @can('delete', $group)
                                <x-ui.button
                                    variant="ghost"
                                    size="sm"
                                    class="text-red-600 hover:text-red-700 dark:text-red-400"
                                    wire:click="delete({{ $group->id }})"
                                    wire:confirm="Gruppe „{{ $group->name }}“ wirklich löschen? Die Mitglieder bleiben erhalten."
                                >Löschen</x-ui.button>
                            @endcan
                        </x-ui.table.cell>
                    </x-ui.table.row>
                @endforeach
            </x-ui.table>
        @endif
    </x-ui.card>

    <x-ui.modal name="group-form" wire:model="showForm" :title="$editingId ? 'Gruppe bearbeiten' : 'Neue Gruppe'">
        <form wire:submit="save" id="group-form" class="space-y-4">
            <x-ui.input label="Name" wire:model="name" required maxlength="100" />
            <x-ui.textarea label="Beschreibung" wire:model="description" rows="2" maxlength="500" />

            <div x-data="{ filter: '' }">
                <div class="mb-1.5 flex items-center justify-between">
                    <span class="text-[13px] font-medium">Mitglieder</span>
                    <span class="text-[12px] text-ink-muted"><span x-text="$wire.members.length"></span> ausgewählt</span>
                </div>
                <x-ui.input x-model="filter" type="search" placeholder="Mitarbeiter filtern …" aria-label="Mitarbeiter filtern" />
                <div class="mt-2 max-h-56 space-y-2 overflow-y-auto rounded-xl bg-surface-muted/60 p-3">
                    @forelse ($this->users as $user)
                        <div wire:key="member-{{ $user->id }}" x-show="@js(mb_strtolower($user->name.' '.$user->email)).includes(filter.toLowerCase())">
                            <x-ui.checkbox :label="$user->name.($user->is_active ? '' : ' (deaktiviert)')" :description="$user->email" wire:model="members" value="{{ $user->id }}" />
                        </div>
                    @empty
                        <p class="text-[13px] text-ink-muted">Noch keine Mitarbeiter angelegt.</p>
                    @endforelse
                </div>
                @error('members.*') <p class="mt-1.5 text-[13px] text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
            </div>
        </form>

        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="show = false">Abbrechen</x-ui.button>
            <x-ui.button type="submit" form="group-form">Speichern</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>
</div>
