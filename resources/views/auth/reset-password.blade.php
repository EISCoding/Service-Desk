<x-layouts::guest title="Neues Passwort" heading="Neues Passwort festlegen" subheading="Mindestens 10 Zeichen mit Buchstaben und Zahlen.">
    <form method="POST" action="{{ route('password.update') }}" class="space-y-4">
        @csrf
        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <x-ui.input label="E-Mail-Adresse" name="email" type="email" :value="old('email', $request->email)" required autocomplete="username" />
        <x-ui.input label="Neues Passwort" name="password" type="password" required autofocus autocomplete="new-password" />
        <x-ui.input label="Passwort wiederholen" name="password_confirmation" type="password" required autocomplete="new-password" />

        <x-ui.button type="submit" class="w-full">Passwort speichern</x-ui.button>
    </form>
</x-layouts::guest>
