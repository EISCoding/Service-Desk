<x-layouts::intern :title="$ticket ? $ticket->reference().' '.$ticket->subject : 'Tickets'" flush>
    {{-- Focus mode (button in the ticket or F) hides the list and gives the ticket the full width. --}}
    <div x-data="{ focus: $persist(false).as('ticket-focus') }" x-on:toggle-ticket-focus.window="focus = ! focus"
        x-bind:class="focus && @js((bool) $ticket) && 'lg:grid-cols-[minmax(0,1fr)]!'"
        class="lg:grid lg:h-[calc(100dvh-3.5rem)] lg:grid-cols-[minmax(320px,400px)_1fr] lg:grid-rows-[minmax(0,1fr)]">
        {{-- The list keeps its scroll position while switching tickets. --}}
        <aside
            x-data
            x-init="$el.scrollTop = Number(sessionStorage.getItem('ticket-list-scroll') || 0)"
            x-on:scroll.debounce.100ms="sessionStorage.setItem('ticket-list-scroll', $el.scrollTop)"
            @class(['relative lg:block lg:overflow-y-auto lg:border-r lg:border-hairline', 'hidden' => $ticket])
            @if ($ticket) x-bind:class="focus && 'lg:!hidden'" @endif
            aria-label="Ticketliste"
        >
            <livewire:intern.tickets.ticket-list :selected-id="$ticket?->id" :preset="$ticket ? null : request()->query('ansicht')" />
        </aside>

        <section @class(['relative lg:block lg:overflow-y-auto', 'hidden' => ! $ticket]) aria-label="Ticket">
            @if ($ticket)
                <livewire:intern.tickets.ticket-view :ticket="$ticket" :key="'ticket-'.$ticket->id" />
            @else
                <div class="grid h-full place-items-center">
                    <x-ui.empty-state icon="collection" title="Kein Ticket ausgewählt" description="Wähle links ein Ticket aus." />
                </div>
            @endif
        </section>
    </div>

    <livewire:intern.tickets.create-ticket />
</x-layouts::intern>
