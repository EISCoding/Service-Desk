<div>
    <x-ui.modal name="customer-form" wire:model="show" max-width="xl" :title="$customerId ? 'Kunde bearbeiten' : 'Neuer Kunde'">
        <form wire:submit="save" id="customer-form" class="space-y-4">
            <x-ui.segmented model="type" :current="$type" :options="['company' => 'Firma', 'private' => 'Privatperson']" label="Kundentyp" />

            @if ($type === 'private')
                <div class="grid gap-4 sm:grid-cols-6">
                    <x-ui.select label="Anrede" wire:model="salutation" placeholder="–" :options="$salutations" class="sm:col-span-2" />
                    <x-ui.input label="Vorname" wire:model="first_name" class="sm:col-span-2" />
                    <x-ui.input label="Nachname" wire:model="last_name" required class="sm:col-span-2" />
                </div>
                <x-ui.input label="Kundennummer" wire:model="number" :placeholder="$nextNumber" :hint="$customerId ? null : 'Leer lassen für automatisch'" />
            @else
                <div class="grid gap-4 sm:grid-cols-3">
                    <x-ui.input label="Firma" wire:model="company" required class="sm:col-span-2" />
                    <x-ui.input label="Kundennummer" wire:model="number" :placeholder="$nextNumber" :hint="$customerId ? null : 'Leer lassen für automatisch'" />
                </div>
            @endif
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
            @if ($type === 'private')
                <p class="text-[12px] text-ink-muted">Die Person wird automatisch als Ansprechpartner angelegt – darüber laufen Portal und E-Mails.</p>
            @else
                <x-ui.input label="Website" wire:model="website" placeholder="www.example.com" />
            @endif
        </form>

        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="show = false">Abbrechen</x-ui.button>
            <x-ui.button type="submit" form="customer-form">{{ $customerId ? 'Speichern' : 'Kunde anlegen' }}</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>
</div>
