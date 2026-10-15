{{-- Ticket list (design E): view menu with counts, search, filters; rows with requester, preview and status. --}}
<div wire:poll.15s="checkForChanges" class="flex min-h-full flex-col bg-surface">
    <div x-data="{ filters: {{ $this->activeFilterCount() > 0 ? 'true' : 'false' }} }" class="sticky top-0 z-10 border-b border-separator bg-surface/95 backdrop-blur-xl">
        <div class="flex h-14 items-center gap-2 px-4">
            {{-- View menu (also the way to switch views on phones, where the sidebar is hidden) --}}
            <div x-data="{ open: false }" class="relative min-w-0" x-on:keydown.escape.stop="open = false">
                <button type="button" x-on:click="open = ! open" x-bind:aria-expanded="open" aria-haspopup="true"
                    class="-ml-1.5 flex h-9 min-w-0 items-center gap-2 rounded-lg px-1.5 hover:bg-ink/[0.04] focus-visible:outline-2 focus-visible:outline-accent">
                    <h1 class="truncate text-[17px] font-semibold tracking-tight">{{ \App\Livewire\Intern\Tickets\TicketList::VIEWS[$view] }}</h1>
                    <span class="rounded-full bg-ink/[0.06] px-2 text-[12px] leading-5 font-semibold text-ink-muted tabular-nums">{{ $this->counts[$view] }}</span>
                    <x-bx-chevron-down class="size-4 shrink-0 text-ink-faint" aria-hidden="true" />
                    <span class="sr-only">Ansicht wechseln</span>
                </button>
                <div x-show="open" x-cloak x-on:click.outside="open = false" x-transition.opacity class="absolute top-full left-0 z-30 mt-1 w-60 rounded-xl bg-surface p-1 shadow-pop" role="menu" aria-label="Ansicht">
                    @foreach (\App\Livewire\Intern\Tickets\TicketList::VIEWS as $key => $label)
                        <button type="button" role="menuitemradio" aria-checked="{{ $view === $key ? 'true' : 'false' }}" wire:click="setView('{{ $key }}')" x-on:click="open = false"
                            @class(['flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-sm hover:bg-surface-muted', 'font-semibold' => $view === $key])>
                            <span class="flex-1">{{ $label }}</span>
                            <span class="text-[12px] text-ink-muted tabular-nums">{{ $this->counts[$key] }}</span>
                            <x-bx-check @class(['size-4 text-accent', 'invisible' => $view !== $key]) aria-hidden="true" />
                        </button>
                    @endforeach
                </div>
            </div>

            <div class="ml-auto flex items-center gap-1">
                <button type="button" x-on:click="filters = ! filters" x-bind:aria-expanded="filters" aria-controls="ticket-filters" title="Filter und Sortierung"
                    class="relative grid size-8 place-items-center rounded-lg text-ink-muted hover:bg-ink/[0.05] hover:text-ink aria-expanded:bg-ink/[0.06] aria-expanded:text-ink">
                    <x-bx-filter class="size-[18px]" aria-hidden="true" />
                    <span class="sr-only">Filter und Sortierung</span>
                    @if ($count = $this->activeFilterCount())
                        <span class="absolute -top-0.5 -right-0.5 grid min-w-4 place-items-center rounded-full bg-accent-fill px-1 text-[10px] leading-4 font-semibold text-on-accent">{{ $count }}</span>
                    @endif
                </button>
                <x-ticket.layout-switch current="list" compact />
            </div>
        </div>

        <div class="px-4 pb-3">
            <label class="relative block">
                <span class="sr-only">Tickets durchsuchen</span>
                <x-bx-search class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-ink-faint" aria-hidden="true" />
                <input type="search" wire:model.live.debounce.300ms="search" placeholder="Betreff oder Nummer …"
                    class="block h-9 w-full rounded-lg bg-surface-muted pr-3 pl-9 text-sm text-ink placeholder:text-ink-faint focus:bg-surface focus:ring-2 focus:ring-accent focus:outline-none">
            </label>
        </div>

        <div id="ticket-filters" x-show="filters" x-cloak x-collapse>
            <div class="grid grid-cols-2 gap-3 border-t border-separator bg-surface-muted/50 p-4">
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
        <x-ui.empty-state icon="collection" title="Keine Tickets" :description="$search !== '' || $this->activeFilterCount() ? 'Kein Ticket passt zu Suche und Filtern.' : 'In dieser Ansicht ist gerade nichts offen.'" />
    @else
        <ul class="divide-y divide-separator/70 transition-opacity" aria-label="Tickets" wire:loading.delay.class="opacity-50">
            @foreach ($items as $ticket)
                @php
                    $selected = $ticket->id === $selectedId;
                    $unread = ! $selected && $ticket->isUnreadFor(auth()->user());
                    $requester = $ticket->contact?->fullName() ?: ($ticket->requester_name ?: ($ticket->customer?->company ?: ($ticket->requester_email ?: '?')));
                @endphp
                <li wire:key="ticket-{{ $ticket->id }}">
                    <a
                        href="{{ route('intern.tickets.show', $ticket) }}"
                        wire:navigate
                        @if ($selected) aria-current="page" @endif
                        @class([
                            'flex gap-3 px-4 py-3 transition-colors focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-accent',
                            'bg-accent/[0.08] dark:bg-accent/15' => $selected,
                            'hover:bg-ink/[0.025]' => ! $selected,
                        ])
                    >
                        <x-ui.avatar :name="$requester" class="mt-0.5" />
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2 text-[12px] text-ink-muted">
                                <span class="min-w-0 flex-1 truncate">
                                    @if ($ticket->customer)
                                        {{ $ticket->customer->company }}@if ($ticket->contact && ! $ticket->customer->isPrivate()) · {{ $ticket->contact->fullName() }}@endif
                                    @else
                                        <span class="italic">{{ $ticket->requester_name ?: ($ticket->requester_email ?: 'Ohne Kunde') }}</span>
                                    @endif
                                </span>
                                @if ($unread)
                                    <span class="size-2 shrink-0 rounded-full bg-accent" aria-hidden="true"></span>
                                    <span class="sr-only">Neue Kundenantwort</span>
                                @endif
                                <time class="shrink-0" datetime="{{ $ticket->last_activity_at?->toIso8601String() }}">{{ $ticket->last_activity_at?->diffForHumans(short: true) }}</time>
                            </div>
                            <p @class(['truncate text-sm text-ink', 'font-semibold' => $unread, 'font-medium' => ! $unread])>{{ $ticket->subject }}</p>
                            @if ($ticket->preview)
                                <p class="truncate text-[13px] text-ink-muted">{{ $ticket->preview }}</p>
                            @endif
                            <div class="mt-1.5 flex items-center gap-1.5">
                                <x-ui.badge :color="$ticket->status->color()" dot class="py-0! text-[11px]!">{{ $ticket->status->label() }}</x-ui.badge>
                                @if ($ticket->priority->isHighlighted())
                                    <x-ui.badge :color="$ticket->priority->color()" class="py-0! text-[11px]!"><x-bx-flag class="size-3" aria-hidden="true" />{{ $ticket->priority->label() }}</x-ui.badge>
                                @endif
                                @if ($ticket->follow_up_due)
                                    <x-ui.badge color="orange" class="py-0! text-[11px]!"><x-bxs-bell-ring class="size-3" aria-hidden="true" /> Wiedervorlage fällig</x-ui.badge>
                                @endif
                                <span class="ml-auto min-w-0 truncate text-[11px] text-ink-faint tabular-nums">{{ $ticket->reference() }}</span>
                                @if ($ticket->assignee)
                                    <x-ui.avatar :name="$ticket->assignee->name" size="xs" />
                                @endif
                            </div>
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
