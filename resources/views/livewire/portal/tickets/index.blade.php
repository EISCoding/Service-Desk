@php($summary = $this->summary)

<div wire:poll.30s="checkForChanges">
    <header class="mb-6 flex flex-wrap items-end justify-between gap-4">
        <div class="min-w-0">
            @unless ($this->contact->customer->isPrivate())
                <p class="text-[13px] font-medium text-ink-muted">{{ $this->contact->customer->company }}</p>
            @endunless
            <h1 class="mt-0.5 text-[28px] leading-tight font-bold tracking-tight">{{ $this->greeting() }}</h1>
            <p class="mt-1 text-[15px] text-ink-muted">{{ $this->summaryText() }}</p>
        </div>
        @if (Route::has('portal.tickets.create'))
            <x-ui.button icon="plus" :href="route('portal.tickets.create')" wire:navigate>Neues Ticket</x-ui.button>
        @endif
    </header>

    @if ($this->waiting->isNotEmpty())
        <section aria-labelledby="waiting-title" class="mb-8">
            <h2 id="waiting-title" class="mb-3 flex items-center gap-2 text-[15px] font-semibold">
                <span class="grid size-6 place-items-center rounded-full bg-yellow-400/25 text-yellow-800 dark:text-yellow-300">
                    <x-bxs-message-rounded-dots class="size-3.5" aria-hidden="true" />
                </span>
                Wartet auf Ihre Antwort
                @if ($summary['waiting'] > $this->waiting->count())
                    <span class="text-[13px] font-normal text-ink-muted">({{ $summary['waiting'] }})</span>
                @endif
            </h2>
            <ul class="grid gap-3 sm:grid-cols-2">
                @foreach ($this->waiting as $ticket)
                    <li wire:key="waiting-{{ $ticket->id }}">
                        <a href="{{ route('portal.tickets.show', $ticket) }}" wire:navigate class="group flex h-full flex-col rounded-card bg-surface p-4 shadow-card ring-1 ring-yellow-500/30 transition hover:-translate-y-px hover:shadow-pop">
                            <span class="ref text-ink-muted">{{ $ticket->reference() }}</span>
                            <span class="mt-0.5 line-clamp-2 text-[15px] font-semibold">{{ $ticket->subject }}</span>
                            <span class="mt-1 text-[13px] text-ink-muted">
                                Unsere letzte Nachricht {{ ($ticket->team_replied_at ?? $ticket->last_activity_at)?->diffForHumans() }}
                            </span>
                            <span class="mt-auto inline-flex items-center gap-0.5 pt-3 text-[13px] font-medium text-accent">
                                Jetzt antworten <x-bx-chevron-right class="size-4 transition group-hover:translate-x-0.5" aria-hidden="true" />
                            </span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    <h2 class="mb-3 text-[15px] font-semibold">{{ $this->contact->is_primary && ! $this->contact->customer->isPrivate() ? 'Anfragen von '.$this->contact->customer->company : 'Ihre Anfragen' }}</h2>

    <div class="mb-4 flex flex-wrap items-center gap-3">
        <x-ui.segmented model="state" :current="$state" :options="['open' => 'Offen', 'closed' => 'Geschlossen', 'all' => 'Alle']" label="Status" />
        @if ($this->contact->is_primary && ! $this->contact->customer->isPrivate())
            <x-ui.select wire:model.live="scope" aria-label="Tickets von" class="w-48" :options="['company' => 'Ganze Firma', 'mine' => 'Nur meine']" />
        @endif
        <x-ui.input wire:model.live.debounce.300ms="search" type="search" placeholder="Betreff oder Nummer …" aria-label="Tickets durchsuchen" class="w-full sm:ml-auto sm:w-64" />
    </div>

    <x-ui.card :padding="false">
        @if ($this->tickets->isEmpty())
            @if ($search !== '' || $state !== 'open')
                <x-ui.empty-state icon="search" title="Keine Tickets gefunden" description="Kein Ticket passt zu Suche und Filter." />
            @else
                <x-ui.empty-state icon="collection" title="Keine offenen Tickets" description="Wenn Sie ein Anliegen haben, legen Sie einfach ein neues Ticket an." />
            @endif
        @else
            <ul class="divide-y divide-hairline transition-opacity" wire:loading.delay.class="opacity-50">
                @foreach ($this->tickets as $ticket)
                    @php($unread = $ticket->isUnreadFor($this->contact))
                    <li wire:key="ticket-{{ $ticket->id }}" class="relative flex flex-wrap items-center gap-x-4 gap-y-1.5 px-5 py-3.5 hover:bg-surface-muted/60">
                        <div class="min-w-0 flex-1 basis-64">
                            <p @class(['flex items-center gap-1.5 text-sm', 'font-semibold' => $unread, 'font-medium' => ! $unread])>
                                @if ($unread)
                                    <span class="size-2 shrink-0 rounded-full bg-accent" aria-hidden="true"></span>
                                    <span class="sr-only">Neue Antwort:</span>
                                @endif
                                <span class="truncate">
                                @if (Route::has('portal.tickets.show'))
                                    <a href="{{ route('portal.tickets.show', $ticket) }}" wire:navigate class="after:absolute after:inset-0">{{ $ticket->subject }}</a>
                                @else
                                    {{ $ticket->subject }}
                                @endif
                                </span>
                            </p>
                            <p class="mt-0.5 truncate text-[12px] text-ink-muted">
                                <span class="tabular-nums">{{ $ticket->reference() }}</span>
                                · aktualisiert <time datetime="{{ $ticket->last_activity_at?->toIso8601String() }}">{{ $ticket->last_activity_at?->diffForHumans() }}</time>
                                @if ($this->contact->is_primary && $ticket->contact)
                                    · {{ $ticket->contact->fullName() }}
                                @endif
                            </p>
                            @if ($appointment = $ticket->appointments->first())
                                <p class="mt-1 inline-flex items-center gap-1 text-[12px] font-medium text-teal-700 dark:text-teal-300">
                                    <x-bx-calendar class="size-3.5" aria-hidden="true" /> Vor-Ort-Termin {{ $appointment->timeRange() }}
                                </p>
                            @endif
                        </div>
                        <x-ui.badge :color="$ticket->status->portalColor()" dot class="shrink-0">{{ $ticket->status->portalLabel() }}</x-ui.badge>
                    </li>
                @endforeach
            </ul>
            @if ($this->tickets->hasPages())
                <div class="border-t border-hairline px-5 py-3">{{ $this->tickets->links() }}</div>
            @endif
        @endif
    </x-ui.card>
</div>
