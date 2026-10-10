<div>
    <x-ui.modal name="customer-form" wire:model="show" max-width="xl" :title="$customerId ? 'Kunde bearbeiten' : 'Neuer Kunde'">
        <form wire:submit="save" id="customer-form" class="space-y-4">
            <div class="grid gap-4 sm:grid-cols-3">
                <x-ui.input label="Firma" wire:model="company" required class="sm:col-span-2" />
                <x-ui.input label="Kundennummer" wire:model="number" :placeholder="$nextNumber" :hint="$customerId ? null : 'Leer lassen für automatisch'" />
            </div>
            <x-ui.input label="Straße und Hausnummer" wire:model="street" autocomplete="off" />
            <div class="grid gap-4 sm:grid-cols-6">
                <x-ui.input label="PLZ" wire:model="postal_code" class="sm:col-span-2" />
                <x-ui.input label="Ort" wire:model="city" class="sm:col-span-4" />
            </div>
            <x-ui.select label="Land" wire:model="country" :options="$countries" required />
            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.input label="E-Mail" wire:model="email" type="email" />
                <x-ui.input label="Telefon" wire:model="phone" type="tel" />
            </div>
            <x-ui.input label="Website" wire:model="website" placeholder="www.example.com" />
        </form>

        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="show = false">Abbrechen</x-ui.button>
            <x-ui.button type="submit" form="customer-form">{{ $customerId ? 'Speichern' : 'Kunde anlegen' }}</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>
</div>
