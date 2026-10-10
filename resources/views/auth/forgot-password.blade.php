<x-layouts::guest title="Passwort vergessen" heading="Passwort vergessen?" subheading="Wir schicken einen Link zum Zurücksetzen an deine E-Mail-Adresse.">
    <form method="POST" action="{{ route('password.email') }}" class="space-y-4">
        @csrf

        <x-ui.input label="E-Mail-Adresse" name="email" type="email" :value="old('email')" required autofocus autocomplete="username" />

        <x-ui.button type="submit" class="w-full">Link anfordern</x-ui.button>
    </form>

    <x-slot:footer>
        <a href="{{ route('login') }}" class="font-medium text-accent hover:underline">Zurück zur Anmeldung</a>
    </x-slot:footer>
</x-layouts::guest>
