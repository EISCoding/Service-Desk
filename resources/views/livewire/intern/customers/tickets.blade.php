<div>
    @can('create', \App\Models\Ticket::class)
        <div class="mb-4 flex justify-end">
            <x-ui.button icon="plus" x-on:click="$dispatch('create-ticket', { customerId: {{ $customer->id }} })">Neues Ticket</x-ui.button>
        </div>
    @endcan

    <x-ui.card :padding="false">
        @if ($this->tickets->isEmpty())
            <x-ui.empty-state icon="inbox-stack" title="Noch keine Tickets" description="Für diesen Kunden gibt es keine Tickets, die du sehen kannst." />
        @else
            <x-ui.table>
                <x-slot:head>
                    <x-ui.table.heading class="w-32">Nr.</x-ui.table.heading>
                    <x-ui.table.heading>Betreff</x-ui.table.heading>
                    <x-ui.table.heading>Status</x-ui.table.heading>
                    <x-ui.table.heading class="hidden md:table-cell">Bearbeiter</x-ui.table.heading>
                    <x-ui.table.heading class="hidden sm:table-cell">Aktivität</x-ui.table.heading>
                </x-slot:head>
                @foreach ($this->tickets as $ticket)
                    <x-ui.table.row wire:key="customer-ticket-{{ $ticket->id }}">
                        <x-ui.table.cell class="tabular-nums text-[13px] text-ink-muted">{{ $ticket->reference() }}</x-ui.table.cell>
                        <x-ui.table.cell>
                            <a href="{{ route('intern.tickets.show', $ticket) }}" wire:navigate class="font-medium hover:text-accent">{{ $ticket->subject }}</a>
                            @if ($ticket->contact)
                                <p class="text-[13px] text-ink-muted">{{ $ticket->contact->fullName() }}</p>
                            @endif
                        </x-ui.table.cell>
                        <x-ui.table.cell><x-ui.badge :color="$ticket->status->color()" dot>{{ $ticket->status->label() }}</x-ui.badge></x-ui.table.cell>
                        <x-ui.table.cell class="hidden text-ink-muted md:table-cell">{{ $ticket->assignee?->name ?? '–' }}</x-ui.table.cell>
                        <x-ui.table.cell class="hidden text-[13px] text-ink-muted sm:table-cell">{{ $ticket->last_activity_at?->diffForHumans() }}</x-ui.table.cell>
                    </x-ui.table.row>
                @endforeach
            </x-ui.table>
            @if ($this->tickets->hasPages())
                <div class="border-t border-separator px-5 py-3">{{ $this->tickets->links() }}</div>
            @endif
        @endif
    </x-ui.card>
</div>
