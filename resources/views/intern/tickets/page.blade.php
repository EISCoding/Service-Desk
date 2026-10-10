<x-layouts::intern :title="$ticket ? $ticket->number.' '.$ticket->subject : 'Tickets'" flush>
    <div class="lg:grid lg:h-[calc(100dvh-3.5rem)] lg:grid-cols-[minmax(320px,400px)_1fr]">
        <aside @class(['lg:block lg:overflow-y-auto lg:border-r lg:border-separator', 'hidden' => $ticket]) aria-label="Ticketliste">
            <livewire:intern.tickets.ticket-list :selected-id="$ticket?->id" />
        </aside>

        <section @class(['lg:block lg:overflow-y-auto', 'hidden' => ! $ticket]) aria-label="Ticket">
            @if ($ticket)
                <livewire:intern.tickets.ticket-view :ticket="$ticket" :key="'ticket-'.$ticket->id" />
            @else
                <div class="grid h-full place-items-center">
                    <x-ui.empty-state icon="inbox-stack" title="Kein Ticket ausgewählt" description="Wähle links ein Ticket aus." />
                </div>
            @endif
        </section>
    </div>
</x-layouts::intern>
