<div class="max-w-2xl space-y-6">
    <x-ui.page-header title="Profil" :description="$this->contact->customer->company" class="mb-0!" />

    <x-ui.card title="Ihre Angaben">
        <form wire:submit="saveProfile" id="profile-form" class="space-y-4">
            <div class="grid gap-4 sm:grid-cols-[10rem_1fr_1fr]">
                <x-ui.select label="Anrede" wire:model="salutation" placeholder="–" :options="$salutationOptions" />
                <x-ui.input label="Vorname" wire:model="first_name" autocomplete="given-name" />
                <x-ui.input label="Nachname" wire:model="last_name" required autocomplete="family-name" />
            </div>
            <x-ui.input label="Telefon" wire:model="phone" type="tel" autocomplete="tel" />
            <x-ui.input label="E-Mail-Adresse" :value="$this->contact->email" type="email" disabled hint="Ihre Anmeldung. Für eine Änderung wenden Sie sich bitte an uns." />
        </form>
        <x-slot:footer>
            <x-ui.button type="submit" form="profile-form">Speichern</x-ui.button>
        </x-slot:footer>
    </x-ui.card>

    <x-ui.card title="Passwort ändern" description="Mindestens 10 Zeichen mit Buchstaben und Zahlen.">
        <form wire:submit="savePassword" id="password-form" class="space-y-4">
            <x-ui.input label="Aktuelles Passwort" wire:model="current_password" type="password" autocomplete="current-password" required />
            <x-ui.input label="Neues Passwort" wire:model="password" type="password" autocomplete="new-password" required />
            <x-ui.input label="Neues Passwort wiederholen" wire:model="password_confirmation" type="password" autocomplete="new-password" required />
        </form>
        <x-slot:footer>
            <x-ui.button type="submit" form="password-form">Passwort ändern</x-ui.button>
        </x-slot:footer>
    </x-ui.card>

    <x-ui.card title="Darstellung" description="Hell, Dunkel oder wie in Ihrem System eingestellt.">
        <x-theme-switcher :url="route('portal.theme.update')" class="max-w-sm" />
    </x-ui.card>
</div>
