<div class="max-w-2xl">
    <x-ui.page-header title="Systemeinstellungen" description="Gelten für den Mitarbeiterbereich, das Kundenportal und E-Mails." />

    <form wire:submit="save" class="space-y-6">
        <x-ui.card title="Firma">
            <div class="space-y-4">
                <x-ui.input label="Firmenname" wire:model="companyName" required maxlength="100" hint="Erscheint in der Seitenleiste, im Portal, in Seitentiteln und E-Mails." />

                <div>
                    <p class="mb-1.5 text-[13px] font-medium">Logo</p>
                    <div class="flex flex-wrap items-center gap-4">
                        <div class="grid size-16 place-items-center rounded-xl bg-surface-muted ring-1 ring-separator">
                            @if ($logo)
                                <img src="{{ $logo->temporaryUrl() }}" alt="Neues Logo" class="size-14 object-contain">
                            @elseif ($logoUrl)
                                <img src="{{ $logoUrl }}" alt="Aktuelles Logo" class="size-14 object-contain">
                            @else
                                <x-brand-mark size="lg" />
                            @endif
                        </div>
                        <div class="space-y-1.5">
                            <label class="inline-flex cursor-pointer items-center gap-1.5 text-[13px] font-medium text-accent hover:underline">
                                <span wire:loading.remove wire:target="logo">{{ $logoUrl ? 'Anderes Logo wählen' : 'Logo hochladen' }}</span>
                                <span wire:loading wire:target="logo">Wird hochgeladen …</span>
                                <input type="file" wire:model="logo" accept="image/png,image/jpeg,image/webp" class="sr-only">
                            </label>
                            @if ($logoUrl)
                                <button type="button" wire:click="removeLogo" wire:confirm="Logo entfernen?" class="block text-[13px] font-medium text-red-600 hover:underline dark:text-red-400">Logo entfernen</button>
                            @endif
                            <p class="text-[12px] text-ink-muted">PNG, JPG oder WebP, quadratisch, höchstens 1 MB.</p>
                            @error('logo') <p class="text-[13px] text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </div>
            </div>
        </x-ui.card>

        <x-ui.card title="Akzentfarbe" description="Für Buttons, Links und Markierungen. Die Farben sind für Hell und Dunkel abgestimmt.">
            <div role="radiogroup" aria-label="Akzentfarbe" class="flex flex-wrap gap-3">
                @foreach ($palette as $key => [$label, $light, , $dark])
                    <label class="flex cursor-pointer flex-col items-center gap-1.5 text-[12px] text-ink-muted">
                        <input type="radio" wire:model="accent" value="{{ $key }}" class="peer sr-only">
                        <span class="size-8 rounded-full ring-2 ring-transparent ring-offset-2 ring-offset-surface peer-checked:ring-ink/60 peer-focus-visible:ring-accent"
                            style="background: linear-gradient(135deg, {{ $light }} 50%, {{ $dark }} 50%)"></span>
                        {{ $label }}
                    </label>
                @endforeach
            </div>
            @error('accent') <p class="mt-2 text-[13px] text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
        </x-ui.card>

        <x-ui.card title="E-Mails" description="Gelten für alle Mails an Kunden. Die Texte der automatischen Mails bearbeitest du unter Vorlagen.">
            <x-slot:actions>
                <x-ui.button variant="secondary" size="sm" icon="eye" :href="route('intern.admin.settings.mail-preview')" target="_blank">Vorschau</x-ui.button>
            </x-slot:actions>
            <div class="space-y-4">
                <x-ui.input label="Hinweis unter Ticket-Mails" wire:model="mailReplyHint" maxlength="300" />
                <x-ui.textarea label="Fußzeile" wire:model="mailFooter" rows="4" maxlength="1000"
                    placeholder="z. B. Firma GmbH · Musterstraße 1 · 12345 Musterstadt&#10;Telefon 01234 5678 · www.firma.de&#10;Geschäftsführer: … · Amtsgericht … HRB …"
                    hint="Steht klein unter jeder Mail, z. B. Anschrift, Telefon und Pflichtangaben. Leer: nur der Firmenname." />
            </div>
        </x-ui.card>

        <x-ui.card title="Tickets">
            <x-ui.input label="Wiedereröffnungsfrist (Tage)" type="number" min="0" max="365" wire:model="reopenDays" required class="max-w-48"
                hint="Antwortet ein Kunde innerhalb dieser Frist auf ein geschlossenes Ticket, wird es wieder geöffnet. Danach entsteht ein neues Ticket." />
        </x-ui.card>

        <div class="flex justify-end">
            <x-ui.button type="submit" wire:loading.attr="disabled" wire:target="logo,save">Speichern</x-ui.button>
        </div>
    </form>
</div>
