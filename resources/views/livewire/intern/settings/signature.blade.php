<div class="max-w-2xl space-y-6">
    <x-ui.page-header title="Signatur" description="Wird automatisch unter deine Antworten an Kunden gesetzt (nicht unter interne Notizen)." class="mb-0!" />

    <x-ui.card>
        <form wire:submit="save" id="signature-form">
            <x-ui.rich-editor label="Signatur" wire:model="signature" placeholder="z. B. Viele Grüße, Name, Telefon …" />
        </form>
        <x-slot:footer>
            <x-ui.button type="submit" form="signature-form">Speichern</x-ui.button>
        </x-slot:footer>
    </x-ui.card>
</div>
