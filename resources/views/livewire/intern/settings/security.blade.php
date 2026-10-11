@php
    $user = $this->user;
    $twoFactorEnabled = $user->hasTwoFactorEnabled();
    $twoFactorPending = ! $twoFactorEnabled && $user->two_factor_secret;
@endphp

<div class="max-w-2xl">
    <x-settings-nav />

    <div class="space-y-6">

    <x-ui.card title="Passwort ändern" description="Mindestens 10 Zeichen mit Buchstaben und Zahlen.">
        <form wire:submit="updatePassword" class="space-y-4" id="password-form">
            <x-ui.input label="Aktuelles Passwort" wire:model="current_password" type="password" autocomplete="current-password" required />
            <x-ui.input label="Neues Passwort" wire:model="password" type="password" autocomplete="new-password" required />
            <x-ui.input label="Neues Passwort wiederholen" wire:model="password_confirmation" type="password" autocomplete="new-password" required />
        </form>
        <x-slot:footer>
            <x-ui.button type="submit" form="password-form">Passwort speichern</x-ui.button>
        </x-slot:footer>
    </x-ui.card>

    <x-ui.card title="Zwei-Faktor-Authentifizierung" description="Zusätzlich zum Passwort wird bei der Anmeldung ein Code aus einer Authenticator-App abgefragt (z. B. Apple Passwörter, Google Authenticator, Microsoft Authenticator).">
        <x-slot:actions>
            @if ($twoFactorEnabled)
                <x-ui.badge color="green" dot>Aktiv</x-ui.badge>
            @elseif ($twoFactorPending)
                <x-ui.badge color="yellow" dot>Einrichtung läuft</x-ui.badge>
            @else
                <x-ui.badge dot>Aus</x-ui.badge>
            @endif
        </x-slot:actions>

        @if ($twoFactorPending)
            <div class="space-y-5">
                <ol class="space-y-5 text-sm">
                    <li>
                        <p class="font-medium">1. QR-Code mit der Authenticator-App scannen</p>
                        <div class="mt-3 flex flex-col gap-4 sm:flex-row sm:items-center">
                            <div class="w-fit rounded-xl bg-white p-3 ring-1 ring-separator [&_svg]:size-40">
                                {!! $user->twoFactorQrCodeSvg() !!}
                            </div>
                            <p class="text-[13px] text-ink-muted">
                                Oder diesen Schlüssel manuell eingeben:<br>
                                <code class="mt-1 inline-block rounded-md bg-surface-muted px-2 py-1 font-mono text-[13px] text-ink select-all">{{ decrypt($user->two_factor_secret) }}</code>
                            </p>
                        </div>
                    </li>
                    <li>
                        <p class="font-medium">2. Angezeigten Code eingeben</p>
                        <form wire:submit="confirmTwoFactor" class="mt-3 flex items-start gap-2">
                            <x-ui.input wire:model="code" inputmode="numeric" autocomplete="one-time-code" placeholder="123456" class="w-40" aria-label="Code" />
                            <x-ui.button type="submit">Bestätigen</x-ui.button>
                        </form>
                    </li>
                </ol>
                <x-ui.button variant="ghost" size="sm" wire:click="cancelTwoFactorSetup">Einrichtung abbrechen</x-ui.button>
            </div>
        @elseif ($twoFactorEnabled)
            <div class="space-y-4">
                @if ($showingRecoveryCodes)
                    <x-ui.alert type="warning">
                        Bewahre diese Wiederherstellungscodes sicher auf. Mit jedem Code kannst du dich einmal anmelden, falls du keinen Zugriff auf die App hast.
                    </x-ui.alert>
                    <ul class="grid grid-cols-2 gap-2 rounded-xl bg-surface-muted p-4 font-mono text-[13px]" aria-label="Wiederherstellungscodes">
                        @foreach ($user->recoveryCodes() as $recoveryCode)
                            <li class="select-all">{{ $recoveryCode }}</li>
                        @endforeach
                    </ul>
                @endif
                <div class="flex flex-wrap gap-2">
                    @if ($showingRecoveryCodes)
                        <x-ui.button variant="secondary" size="sm" wire:click="regenerateRecoveryCodes">Neue Codes erzeugen</x-ui.button>
                    @else
                        <x-ui.button variant="secondary" size="sm" wire:click="$set('showingRecoveryCodes', true)">Wiederherstellungscodes anzeigen</x-ui.button>
                    @endif
                    <x-ui.button variant="danger" size="sm" wire:click="disableTwoFactor" wire:confirm="Zwei-Faktor-Authentifizierung wirklich deaktivieren?">Deaktivieren</x-ui.button>
                </div>
            </div>
        @else
            <x-ui.button wire:click="enableTwoFactor" icon="shield-check">Aktivieren</x-ui.button>
        @endif
    </x-ui.card>
    </div>
</div>
