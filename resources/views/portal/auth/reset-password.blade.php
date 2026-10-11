<x-layouts::guest title="Neues Passwort · Kundenportal" heading="Neues Passwort" subheading="Bitte legen Sie ein neues Passwort fest.">
    <form method="POST" action="{{ route('portal.password.update') }}" class="space-y-4">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">

        <x-ui.input label="E-Mail-Adresse" name="email" type="email" :value="old('email', $email)" required autocomplete="username" />
        <x-ui.input label="Neues Passwort" name="password" type="password" required autofocus autocomplete="new-password" hint="Mindestens 10 Zeichen mit Buchstaben und Zahlen." />
        <x-ui.input label="Passwort wiederholen" name="password_confirmation" type="password" required autocomplete="new-password" />

        <x-ui.button type="submit" class="w-full">Passwort speichern</x-ui.button>
    </form>
</x-layouts::guest>
