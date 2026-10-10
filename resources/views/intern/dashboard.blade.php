<x-layouts::intern title="Übersicht">
    <x-ui.card :padding="false">
        <x-ui.empty-state icon="ticket" title="Willkommen, {{ auth()->user()->name }}" description="Tickets, Kunden und Termine folgen in den nächsten Phasen." />
    </x-ui.card>
</x-layouts::intern>
