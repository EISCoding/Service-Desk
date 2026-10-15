<div>
    <a href="{{ route('intern.customers.index') }}" wire:navigate class="mb-3 inline-flex items-center gap-1 text-[13px] font-medium text-ink-muted hover:text-ink">
        <x-bx-chevron-left class="size-4" aria-hidden="true" /> Kunden
    </a>

    <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-2">
                <h1 class="text-[26px] leading-tight font-semibold tracking-tight">{{ $customer->company }}</h1>
                @if ($customer->isPrivate())
                    <x-ui.badge color="purple">Privatperson</x-ui.badge>
                @endif
                @if ($customer->isArchived())
                    <x-ui.badge dot>Archiviert</x-ui.badge>
                @endif
            </div>
            <p class="mt-1 text-sm text-ink-muted">
                <span class="tabular-nums">{{ $customer->number }}</span>
                @if ($customer->city)
                    · {{ trim("{$customer->postal_code} {$customer->city}") }}
                @endif
            </p>
        </div>
        <div class="flex shrink-0 items-center gap-2">
            @can('update', $customer)
                <x-ui.button variant="secondary" icon="pencil" x-on:click="$dispatch('edit-customer', { id: {{ $customer->id }} })">Bearbeiten</x-ui.button>
            @endcan
            @canany(['archive', 'delete'], $customer)
                <div x-data="{ open: false }" class="relative" x-on:keydown.escape="open = false">
                    <x-ui.button variant="secondary" x-on:click="open = ! open" x-bind:aria-expanded="open">
                        <x-bx-dots-horizontal-rounded class="size-5" aria-hidden="true" />
                        <span class="sr-only">Weitere Aktionen</span>
                    </x-ui.button>
                    <div x-show="open" x-cloak x-on:click.outside="open = false" x-transition.opacity class="absolute right-0 z-20 mt-1 w-56 rounded-xl bg-surface p-1 shadow-pop">
                        @can('archive', $customer)
                            @if ($customer->isArchived())
                                <button type="button" x-on:click="open = false" wire:click="restore" class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-sm hover:bg-surface-muted">
                                    <x-bx-undo class="size-4 text-ink-muted" aria-hidden="true" /> Wiederherstellen
                                </button>
                            @else
                                <button type="button" x-on:click="open = false" wire:click="archive" wire:confirm="Kunde archivieren? Er erscheint dann nicht mehr in der Kundenliste, bleibt aber mit Verlauf erhalten." class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-sm hover:bg-surface-muted">
                                    <x-bx-archive class="size-4 text-ink-muted" aria-hidden="true" /> Archivieren
                                </button>
                            @endif
                        @endcan
                        @can('delete', $customer)
                            <button type="button" x-on:click="open = false" wire:click="delete" wire:confirm="Kunde {{ $customer->number }} mit allen Ansprechpartnern und Notizen endgültig löschen?" class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-sm text-red-600 hover:bg-surface-muted dark:text-red-400">
                                <x-bx-trash class="size-4" aria-hidden="true" /> Löschen
                            </button>
                        @endcan
                    </div>
                </div>
            @endcanany
        </div>
    </div>

    <nav role="tablist" aria-label="Kundenbereiche" class="mb-6 -mx-1 flex gap-1 overflow-x-auto overflow-y-hidden border-b border-hairline px-1 [scrollbar-width:none]">
        @foreach ($tabs as $key => $label)
            @php($active = $tab === $key)
            <button
                type="button"
                role="tab"
                aria-selected="{{ $active ? 'true' : 'false' }}"
                wire:click="showTab('{{ $key }}')"
                @class([
                    'relative shrink-0 px-3 pt-1 pb-2.5 text-sm font-medium transition-colors focus-visible:outline-2 focus-visible:outline-accent',
                    'text-ink after:absolute after:inset-x-2 after:bottom-0 after:h-0.5 after:rounded-full after:bg-accent' => $active,
                    'text-ink-muted hover:text-ink' => ! $active,
                ])
            >
                {{ $label }}
                @if ($key === 'ansprechpartner' && $this->counts['contacts'])
                    <span class="ml-1 rounded-full bg-ink/[0.06] px-1.5 text-[11px] text-ink-muted">{{ $this->counts['contacts'] }}</span>
                @elseif ($key === 'notizen' && $this->counts['notes'])
                    <span class="ml-1 rounded-full bg-ink/[0.06] px-1.5 text-[11px] text-ink-muted">{{ $this->counts['notes'] }}</span>
                @endif
            </button>
        @endforeach
    </nav>

    <div role="tabpanel">
        @switch($tab)
            @case('ansprechpartner')
                <livewire:intern.customers.contacts :customer="$customer" :key="'contacts-'.$customer->id" />
                @break

            @case('tickets')
                <livewire:intern.customers.tickets :customer="$customer" :key="'tickets-'.$customer->id" />
                @break

            @case('notizen')
                <livewire:intern.customers.notes :customer="$customer" :key="'notes-'.$customer->id" />
                @break

            @case('verlauf')
                <livewire:intern.customers.history :customer="$customer" :key="'history-'.$customer->id" />
                @break

            @default
                <div class="grid gap-6 lg:grid-cols-3">
                    <div class="space-y-6 lg:col-span-2">
                        <x-ui.card title="Stammdaten">
                            <dl class="grid gap-x-6 gap-y-4 text-sm sm:grid-cols-2">
                                <div>
                                    <dt class="text-[12px] font-medium text-ink-muted">Adresse</dt>
                                    <dd class="mt-0.5">
                                        @forelse ($customer->addressLines() as $line)
                                            {{ $line }}<br>
                                        @empty
                                            <span class="text-ink-faint">–</span>
                                        @endforelse
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-[12px] font-medium text-ink-muted">Kundennummer</dt>
                                    <dd class="mt-0.5 tabular-nums">{{ $customer->number }}</dd>
                                </div>
                                <div>
                                    <dt class="text-[12px] font-medium text-ink-muted">E-Mail</dt>
                                    <dd class="mt-0.5">
                                        @if ($customer->email)
                                            <a href="mailto:{{ $customer->email }}" class="text-accent hover:underline">{{ $customer->email }}</a>
                                        @else
                                            <span class="text-ink-faint">–</span>
                                        @endif
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-[12px] font-medium text-ink-muted">Telefon</dt>
                                    <dd class="mt-0.5">
                                        @if ($customer->phone)
                                            <a href="tel:{{ preg_replace('/[^\d+]/', '', $customer->phone) }}" class="text-accent hover:underline">{{ $customer->phone }}</a>
                                        @else
                                            <span class="text-ink-faint">–</span>
                                        @endif
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-[12px] font-medium text-ink-muted">Website</dt>
                                    <dd class="mt-0.5 truncate">
                                        @if ($customer->website)
                                            <a href="{{ $customer->website }}" target="_blank" rel="noopener noreferrer" class="text-accent hover:underline">{{ preg_replace('#^https?://#', '', $customer->website) }}</a>
                                        @else
                                            <span class="text-ink-faint">–</span>
                                        @endif
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-[12px] font-medium text-ink-muted">Kunde seit</dt>
                                    <dd class="mt-0.5">{{ $customer->created_at->isoFormat('D. MMMM YYYY') }}</dd>
                                </div>
                            </dl>
                        </x-ui.card>

                        @if ($pinnedNotes->isNotEmpty())
                            <x-ui.card title="Angeheftete Notizen">
                                <ul class="space-y-3">
                                    @foreach ($pinnedNotes as $note)
                                        <li class="rounded-xl bg-yellow-500/10 px-4 py-3 text-sm">
                                            <p class="whitespace-pre-line">{{ $note->body }}</p>
                                            <p class="mt-1.5 text-[12px] text-ink-muted">{{ $note->author?->name ?? 'Unbekannt' }} · {{ $note->created_at->isoFormat('D. MMM YYYY') }}</p>
                                        </li>
                                    @endforeach
                                </ul>
                            </x-ui.card>
                        @endif
                    </div>

                    <x-ui.card title="Hauptansprechpartner" class="self-start">
                        @forelse ($primaryContacts as $contact)
                            <div @class(['text-sm', 'mt-4 border-t border-separator pt-4' => ! $loop->first])>
                                <p class="font-medium">{{ $contact->fullName() }}</p>
                                @if ($contact->position)
                                    <p class="text-[13px] text-ink-muted">{{ $contact->position }}</p>
                                @endif
                                @if ($contact->email)
                                    <a href="mailto:{{ $contact->email }}" class="mt-1 block truncate text-[13px] text-accent hover:underline">{{ $contact->email }}</a>
                                @endif
                                @if ($contact->phone)
                                    <p class="text-[13px] text-ink-muted">{{ $contact->phone }}</p>
                                @endif
                            </div>
                        @empty
                            <p class="text-sm text-ink-muted">Kein Hauptansprechpartner festgelegt.</p>
                        @endforelse
                        <x-slot:footer>
                            <x-ui.button variant="ghost" size="sm" wire:click="showTab('ansprechpartner')">Alle Ansprechpartner</x-ui.button>
                        </x-slot:footer>
                    </x-ui.card>
                </div>
        @endswitch
    </div>

    <livewire:intern.customers.customer-form />
</div>
