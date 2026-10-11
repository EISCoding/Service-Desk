<div class="max-w-2xl">
    <x-settings-nav />

    <x-ui.card title="Signatur" description="Wird automatisch unter deine Antworten an Kunden gesetzt (nicht unter interne Notizen).">
        <form wire:submit="save" id="signature-form">
            <x-ui.rich-editor label="Signatur" wire:model="signature" placeholder="z. B. Viele Grüße, Name, Telefon …" />
        </form>
        <x-slot:footer>
            <x-ui.button type="submit" form="signature-form">Speichern</x-ui.button>
        </x-slot:footer>
    </x-ui.card>
</div>
