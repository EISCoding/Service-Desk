<div>
    <x-ui.page-header title="Ihre Tickets" :description="$this->contact->is_primary ? 'Alle Anfragen von '.$this->contact->customer->company.'.' : 'Ihre Anfragen an uns.'">
        @if (Route::has('portal.tickets.create'))
            <x-slot:actions>
                <x-ui.button icon="plus" :href="route('portal.tickets.create')" wire:navigate>Neues Ticket</x-ui.button>
            </x-slot:actions>
        @endif
    </x-ui.page-header>

    <div class="mb-4 flex flex-wrap items-center gap-3">
        <div role="tablist" aria-label="Status" class="inline-grid grid-cols-3 gap-0.5 rounded-[10px] bg-ink/[0.05] p-0.5">
            @foreach (['open' => 'Offen', 'closed' => 'Geschlossen', 'all' => 'Alle'] as $value => $label)
                <button type="button" role="tab" aria-selected="{{ $state === $value ? 'true' : 'false' }}" wire:click="$set('state', '{{ $value }}')"
                    @class(['rounded-lg px-3 py-1 text-[13px] font-medium', 'bg-surface shadow-xs' => $state === $value, 'text-ink-muted hover:text-ink' => $state !== $value])>{{ $label }}</button>
            @endforeach
        </div>
        @if ($this->contact->is_primary)
            <x-ui.select wire:model.live="scope" aria-label="Tickets von" class="w-48" :options="['company' => 'Ganze Firma', 'mine' => 'Nur meine']" />
        @endif
        <x-ui.input wire:model.live.debounce.300ms="search" type="search" placeholder="Betreff oder Nummer …" aria-label="Tickets durchsuchen" class="w-full sm:ml-auto sm:w-64" />
    </div>

    <x-ui.card :padding="false">
        @if ($this->tickets->isEmpty())
            @if ($search !== '' || $state !== 'open')
                <x-ui.empty-state icon="magnifying-glass" title="Keine Tickets gefunden" description="Kein Ticket passt zu Suche und Filter." />
            @else
                <x-ui.empty-state icon="inbox" title="Keine offenen Tickets" description="Wenn Sie ein Anliegen haben, legen Sie einfach ein neues Ticket an." />
            @endif
        @else
            <ul class="divide-y divide-separator">
                @foreach ($this->tickets as $ticket)
                    <li wire:key="ticket-{{ $ticket->id }}" class="relative flex items-center gap-4 px-5 py-3.5 hover:bg-surface-muted/60">
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium">
                                @if (Route::has('portal.tickets.show'))
                                    <a href="{{ route('portal.tickets.show', $ticket) }}" wire:navigate class="after:absolute after:inset-0">{{ $ticket->subject }}</a>
                                @else
                                    {{ $ticket->subject }}
                                @endif
                            </p>
                            <p class="mt-0.5 truncate text-[12px] text-ink-muted">
                                <span class="font-mono">{{ $ticket->reference() }}</span>
                                · aktualisiert <time datetime="{{ $ticket->last_activity_at?->toIso8601String() }}">{{ $ticket->last_activity_at?->diffForHumans() }}</time>
                                @if ($this->contact->is_primary && $ticket->contact)
                                    · {{ $ticket->contact->fullName() }}
                                @endif
                            </p>
                            @if ($appointment = $ticket->appointments->first())
                                <p class="mt-1 inline-flex items-center gap-1 text-[12px] font-medium text-teal-700 dark:text-teal-300">
                                    <x-heroicon-m-calendar-days class="size-3.5" aria-hidden="true" /> Vor-Ort-Termin {{ $appointment->timeRange() }}
                                </p>
                            @endif
                        </div>
                        <x-ui.badge :color="$ticket->status->portalColor()" dot class="shrink-0">{{ $ticket->status->portalLabel() }}</x-ui.badge>
                    </li>
                @endforeach
            </ul>
            @if ($this->tickets->hasPages())
                <div class="border-t border-separator px-5 py-3">{{ $this->tickets->links() }}</div>
            @endif
        @endif
    </x-ui.card>
</div>
