<div class="max-w-2xl">
    <x-settings-nav />

    <div class="space-y-6">
        <x-ui.card title="Darstellung" description="Hell, Dunkel oder wie im System eingestellt. Gilt auf allen deinen Geräten.">
            <x-theme-switcher class="max-w-sm" />
        </x-ui.card>

        <x-ui.card title="Ticketliste" description="Mit welcher Ansicht die Ticketliste startet.">
            <x-ui.select label="Standard-Ansicht" wire:model.live="ticketView" :options="$viewOptions" class="max-w-xs" />
        </x-ui.card>
    </div>
</div>
