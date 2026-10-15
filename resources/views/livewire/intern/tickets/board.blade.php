@php
    $dots = ['blue' => 'bg-blue-500', 'indigo' => 'bg-indigo-500', 'yellow' => 'bg-yellow-500', 'orange' => 'bg-orange-500', 'purple' => 'bg-purple-500', 'teal' => 'bg-teal-500', 'gray' => 'bg-zinc-400'];
    $moving = $this->movingTicket();
    $target = $movingStatus ? \App\Enums\TicketStatus::tryFrom($movingStatus) : null;
    $notification = $moving && $target ? $this->notification($moving, $target) : null;
@endphp

<div wire:poll.15s="checkForChanges">
    <div class="mb-5 flex flex-wrap items-center gap-3">
        <h1 class="text-[22px] leading-tight font-semibold tracking-tight">Tickets</h1>
        <x-ticket.layout-switch current="board" />
    </div>

    <div class="mb-4 flex flex-wrap items-center gap-3">
        <x-ui.segmented model="view" :current="$view" :options="\App\Livewire\Intern\Tickets\TicketList::VIEWS" label="Ansicht" />
        <x-ui.input wire:model.live.debounce.300ms="search" type="search" placeholder="Betreff oder Nummer …" aria-label="Tickets durchsuchen" class="w-full sm:ml-auto sm:w-64" />
    </div>

    <p class="sr-only">Karten lassen sich per Drag &amp; Drop in eine andere Spalte ziehen, um den Status zu ändern. Alternativ den Status im Ticket ändern.</p>

    <div class="-mx-4 flex snap-x snap-mandatory scroll-px-4 items-start gap-3 overflow-x-auto px-4 pb-6 sm:-mx-6 sm:scroll-px-6 sm:px-6 lg:-mx-8 lg:snap-none lg:px-8" wire:loading.class.delay="opacity-60" wire:target="view,search">
        @foreach ($this->columns as $value => $tickets)
            @php($status = \App\Enums\TicketStatus::from($value))
            <section wire:key="column-{{ $value }}" aria-labelledby="column-{{ $value }}" class="flex w-[17rem] shrink-0 snap-start flex-col rounded-xl bg-ink/[0.035] p-2 dark:bg-white/[0.04]">
                <header class="flex items-center gap-2 px-2 pt-1 pb-2.5">
                    <span class="size-2 rounded-full {{ $dots[$status->color()] ?? 'bg-zinc-400' }}" aria-hidden="true"></span>
                    <h2 id="column-{{ $value }}" class="text-[13px] font-semibold">{{ $status->label() }}</h2>
                    <span class="text-[12px] text-ink-muted tabular-nums">{{ $tickets->count() }}</span>
                    @if ($status === \App\Enums\TicketStatus::Closed)
                        <span class="ml-auto text-[11px] text-ink-muted">letzte {{ \App\Livewire\Intern\Tickets\Board::CLOSED_DAYS }} Tage</span>
                    @endif
                </header>
                <ul wire:sort="moveTicket" wire:sort:group="board" wire:sort:group-id="{{ $value }}" wire:sort:config="{ forceFallback: true, fallbackOnBody: true, fallbackTolerance: 4, delayOnTouchOnly: true, delay: 150 }" aria-labelledby="column-{{ $value }}" class="flex min-h-28 flex-col gap-2">
                    @foreach ($tickets as $ticket)
                        <li wire:key="card-{{ $ticket->id }}" wire:sort:item="{{ $ticket->id }}" class="cursor-grab active:cursor-grabbing">
                            <x-ticket.card :ticket="$ticket" />
                        </li>
                    @endforeach
                </ul>
            </section>
        @endforeach
    </div>

    {{-- Confirm a drop: follow-up date and/or mail to the customer --}}
    <x-ui.modal name="move" :title="$target ? 'Status: '.$target->label() : 'Status ändern'" max-width="sm"
        x-init="$watch('show', value => { if (! value && $wire.movingId) $wire.cancelMove() })"
        x-effect="show = $wire.movingId !== null">
        @if ($moving)
            <p class="mb-4 text-sm text-ink-muted"><span class="tabular-nums">{{ $moving->reference() }}</span> · {{ $moving->subject }}</p>
        @endif
        <form wire:submit="confirmMove" id="move-form" class="space-y-4">
            @if ($target?->requiresFollowUp())
                <div class="space-y-3">
                    <x-ui.input label="Wiedervorlage am" type="date" wire:model="followUpDate" :min="today()->toDateString()" required />
                    <div class="flex flex-wrap gap-1.5">
                        @foreach (['Morgen' => today()->addWeekday(), 'In 2 Werktagen' => today()->addWeekdays(2), 'Nächste Woche' => today()->next('Monday')] as $label => $date)
                            <button type="button" wire:click="$set('followUpDate', '{{ $date->toDateString() }}')" class="rounded-full bg-ink/[0.05] px-2.5 py-1 text-[12px] font-medium text-ink-muted hover:text-ink">{{ $label }}</button>
                        @endforeach
                    </div>
                </div>
            @endif
            @if ($notification)
                <x-ui.checkbox label="Kunden per E-Mail informieren" wire:model="notifyCustomer"
                    :description="'Vorlage „'.$notification->label().'“ an '.$moving->recipient()['email']" />
            @endif
        </form>
        <x-slot:footer>
            <x-ui.button variant="secondary" wire:click="cancelMove">Abbrechen</x-ui.button>
            <x-ui.button type="submit" form="move-form">Status setzen</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>

</div>
