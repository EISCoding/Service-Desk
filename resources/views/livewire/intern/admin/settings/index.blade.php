<div class="max-w-2xl">
    <x-ui.page-header title="Systemeinstellungen" description="Gelten für den Mitarbeiterbereich, das Kundenportal und E-Mails." />

    <form wire:submit="save" class="space-y-6">
        <x-ui.card title="Firma">
            <div class="space-y-4">
                <x-ui.input label="Firmenname" wire:model="companyName" required maxlength="100" hint="Erscheint in der Seitenleiste, im Portal, in Seitentiteln und E-Mails." />

                <p class="text-[13px] text-ink-muted">Logo, App-Icon und Akzentfarbe stellst du unter <a href="{{ route('intern.admin.branding') }}" wire:navigate class="font-medium text-accent hover:underline">Erscheinungsbild</a> ein, Aussehen und Fußzeile der Mails unter <a href="{{ route('intern.admin.mail-design') }}" wire:navigate class="font-medium text-accent hover:underline">Mail-Design</a>.</p>
            </div>
        </x-ui.card>

        <x-ui.card title="Tickets">
            <x-ui.input label="Wiedereröffnungsfrist (Tage)" type="number" min="0" max="365" wire:model="reopenDays" required class="max-w-48"
                hint="Antwortet ein Kunde innerhalb dieser Frist auf ein geschlossenes Ticket, wird es wieder geöffnet. Danach entsteht ein neues Ticket." />
        </x-ui.card>

        <div class="flex justify-end">
            <x-ui.button type="submit" wire:loading.attr="disabled" wire:target="save">Speichern</x-ui.button>
        </div>
    </form>
</div>
