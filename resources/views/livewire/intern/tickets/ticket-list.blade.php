<div class="flex min-h-full flex-col">
    <div x-data="{ filters: {{ $this->activeFilterCount() > 0 ? 'true' : 'false' }} }" class="sticky top-0 z-10 space-y-3 border-b border-separator bg-canvas/90 px-4 pt-4 pb-3 backdrop-blur-xl">
        <div class="flex items-center justify-between gap-3">
            <h1 class="text-[22px] font-semibold tracking-tight">Tickets</h1>
            @can('create', \App\Models\Ticket::class)
                <x-ui.button size="sm" icon="plus" x-on:click="$dispatch('create-ticket')">Neu</x-ui.button>
            @endcan
        </div>

        <div role="tablist" aria-label="Ansicht" class="flex flex-wrap gap-1.5">
            @foreach (\App\Livewire\Intern\Tickets\TicketList::VIEWS as $key => $label)
                <button
                    type="button"
                    role="tab"
                    aria-selected="{{ $view === $key ? 'true' : 'false' }}"
                    wire:click="setView('{{ $key }}')"
                    @class([
                        'inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-[13px] font-medium transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent',
                        'bg-accent-fill text-on-accent' => $view === $key,
                        'bg-ink/[0.05] text-ink-muted hover:text-ink' => $view !== $key,
                    ])
                >
                    {{ $label }}
                    <span @class(['text-[12px] tabular-nums', 'text-on-accent' => $view === $key, 'text-ink-muted' => $view !== $key])>{{ $this->counts[$key] }}</span>
                </button>
            @endforeach
        </div>

        <div class="flex gap-2">
            <div class="flex-1">
                <x-ui.input wire:model.live.debounce.300ms="search" type="search" placeholder="Betreff oder Nummer …" aria-label="Tickets durchsuchen" />
            </div>
            <x-ui.button variant="secondary" x-on:click="filters = ! filters" x-bind:aria-expanded="filters" aria-controls="ticket-filters">
                <x-heroicon-m-adjustments-horizontal class="size-4" aria-hidden="true" />
                <span class="sr-only sm:not-sr-only">Filter</span>
                @if ($count = $this->activeFilterCount())
                    <span class="rounded-full bg-accent-fill px-1.5 text-[11px] text-on-accent">{{ $count }}</span>
                @endif
            </x-ui.button>

        </div>

        <div id="ticket-filters" x-show="filters" x-cloak x-collapse>
                    <div class="grid grid-cols-2 gap-3 rounded-xl bg-surface-muted/70 p-3">
                        <x-ui.select label="Status" wire:model.live="status" :options="$statusOptions" />
                        <x-ui.select label="Priorität" wire:model.live="priority" placeholder="Alle" :options="$priorityOptions" />
                        <x-ui.select label="Gruppe" wire:model.live="group" placeholder="Alle" :options="$this->groupOptions" />
                        <x-ui.select label="Bearbeiter" wire:model.live="assignee" placeholder="Alle" :options="$this->assigneeOptions" />
                        <x-ui.input label="Kunde" wire:model.live.debounce.300ms="customer" placeholder="Firma oder Nr." class="col-span-2" />
                        <x-ui.checkbox label="Ohne Kunde" wire:model.live="withoutCustomer" />
                        <x-ui.checkbox label="Wiedervorlage fällig" wire:model.live="dueOnly" />
                        <x-ui.checkbox label="Nur ungelesene" wire:model.live="unreadOnly" />
                        <x-ui.select label="Sortierung" wire:model.live="sort" :options="['activity' => 'Letzte Aktivität', 'priority' => 'Priorität', 'created' => 'Neueste zuerst']" />
                        <div class="col-span-2 flex items-end justify-end">
                            <x-ui.button variant="ghost" size="sm" wire:click="resetFilters">Zurücksetzen</x-ui.button>
                        </div>
                    </div>
        </div>
    </div>

    @if ($items->isEmpty())
        <x-ui.empty-state icon="inbox" title="Keine Tickets" :description="$search !== '' || $this->activeFilterCount() ? 'Kein Ticket passt zu Suche und Filtern.' : 'In dieser Ansicht ist gerade nichts offen.'" />
    @else
        <ul class="divide-y divide-separator transition-opacity" aria-label="Tickets" wire:loading.delay.class="opacity-50">
            @foreach ($items as $ticket)
                @php($selected = $ticket->id === $selectedId)
                @php($unread = ! $selected && $ticket->isUnreadFor(auth()->user()))
                <li wire:key="ticket-{{ $ticket->id }}">
                    <a
                        href="{{ route('intern.tickets.show', $ticket) }}"
                        wire:navigate
                        @if ($selected) aria-current="page" @endif
                        @class([
                            'block px-4 py-3 transition-colors focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-accent',
                            'bg-accent/10' => $selected,
                            'hover:bg-ink/[0.03]' => ! $selected,
                        ])
                    >
                        <div class="flex items-center justify-between gap-2 text-[12px] text-ink-muted">
                            <span class="tabular-nums">{{ $ticket->reference() }}</span>
                            <time datetime="{{ $ticket->last_activity_at?->toIso8601String() }}">{{ $ticket->last_activity_at?->diffForHumans(short: true) }}</time>
                        </div>
                        <p class="mt-0.5 flex items-center gap-1.5 text-sm text-ink">
                            @if ($unread)
                                <span class="size-2 shrink-0 rounded-full bg-accent" aria-hidden="true"></span>
                                <span class="sr-only">Neue Kundenantwort:</span>
                            @endif
                            <span @class(['truncate', 'font-semibold' => $unread, 'font-medium' => ! $unread])>{{ $ticket->subject }}</span>
                        </p>
                        <p class="truncate text-[13px] text-ink-muted">
                            @if ($ticket->customer)
                                {{ $ticket->customer->company }}@if ($ticket->contact) · {{ $ticket->contact->fullName() }}@endif
                            @else
                                <span class="italic">Ohne Kunde</span>
                            @endif
                        </p>
                        <div class="mt-1.5 flex flex-wrap items-center gap-1">
                            <x-ui.badge :color="$ticket->status->color()" dot>{{ $ticket->status->label() }}</x-ui.badge>
                            @if ($ticket->priority->isHighlighted())
                                <x-ui.badge :color="$ticket->priority->color()">{{ $ticket->priority->label() }}</x-ui.badge>
                            @endif
                            @if ($ticket->follow_up_due)
                                <x-ui.badge color="orange">
                                    <x-heroicon-m-bell-alert class="size-3" aria-hidden="true" /> Wiedervorlage fällig
                                </x-ui.badge>
                            @endif
                            <span class="ml-auto">
                                @if ($ticket->assignee)
                                    <x-ui.avatar :name="$ticket->assignee->name" size="sm" />
                                @endif
                            </span>
                        </div>
                    </a>
                </li>
            @endforeach
        </ul>

        @if ($hasMore)
            <div class="p-4 text-center">
                <x-ui.button variant="secondary" size="sm" wire:click="loadMore">Weitere laden</x-ui.button>
            </div>
        @endif
    @endif
</div>
