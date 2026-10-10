<div>
    <a href="{{ route('intern.customers.index') }}" wire:navigate class="mb-3 inline-flex items-center gap-1 text-[13px] font-medium text-ink-muted hover:text-ink">
        <x-heroicon-m-chevron-left class="size-4" aria-hidden="true" /> Kunden
    </a>

    <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-2">
                <h1 class="text-[26px] leading-tight font-semibold tracking-tight">{{ $customer->company }}</h1>
                @if ($customer->isArchived())
                    <x-ui.badge dot>Archiviert</x-ui.badge>
                @endif
            </div>
            <p class="mt-1 text-sm text-ink-muted">
                <span class="font-mono">{{ $customer->number }}</span>
                @if ($customer->city)
                    · {{ trim("{$customer->postal_code} {$customer->city}") }}
                @endif
            </p>
        </div>
        <div class="flex shrink-0 items-center gap-2">
            {{ $actions ?? '' }}
        </div>
    </div>

    <nav role="tablist" aria-label="Kundenbereiche" class="mb-6 -mx-1 flex gap-1 overflow-x-auto border-b border-separator px-1">
        @foreach ($tabs as $key => $label)
            @php($active = $tab === $key)
            <button
                type="button"
                role="tab"
                aria-selected="{{ $active ? 'true' : 'false' }}"
                wire:click="showTab('{{ $key }}')"
                @class([
                    'relative shrink-0 px-3 pt-1 pb-2.5 text-sm font-medium transition-colors focus-visible:outline-2 focus-visible:outline-accent',
                    'text-ink after:absolute after:inset-x-2 after:-bottom-px after:h-0.5 after:rounded-full after:bg-accent' => $active,
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
                <x-ui.card :padding="false">
                    <x-ui.empty-state icon="inbox-stack" title="Noch keine Tickets" description="Die Tickets dieses Kunden erscheinen hier, sobald die Ticketverwaltung da ist (Phase 3)." />
                </x-ui.card>
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
                                    <dd class="mt-0.5 font-mono">{{ $customer->number }}</dd>
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
</div>
