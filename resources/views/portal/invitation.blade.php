<x-layouts::guest title="Kundenportal" heading="Willkommen im Kundenportal" :subheading="$valid ? 'Bitte legen Sie Ihr Passwort fest.' : null">
    @if ($valid)
        <form method="POST" action="{{ route('portal.invitation.store') }}" class="space-y-4">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">
            <input type="hidden" name="email" value="{{ $email }}">

            <x-ui.input label="E-Mail-Adresse" :value="$email" type="email" disabled autocomplete="username" />
            <x-ui.input label="Passwort" name="password" type="password" required autofocus autocomplete="new-password" hint="Mindestens 10 Zeichen mit Buchstaben und Zahlen." />
            <x-ui.input label="Passwort wiederholen" name="password_confirmation" type="password" required autocomplete="new-password" />

            @error('email')
                <x-ui.alert type="error">{{ $message }}</x-ui.alert>
            @enderror

            <x-ui.button type="submit" class="w-full">Passwort festlegen</x-ui.button>
        </form>
    @else
        <x-ui.alert type="error">
            Dieser Einladungslink ist ungültig oder abgelaufen. Bitte fordern Sie bei uns eine neue Einladung an.
        </x-ui.alert>
    @endif
</x-layouts::guest>
