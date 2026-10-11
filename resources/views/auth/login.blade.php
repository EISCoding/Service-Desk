<x-layouts::guest title="Anmelden" heading="Anmelden" subheading="Mit deinem Mitarbeiterkonto">
    <form method="POST" action="{{ route('login.store') }}" class="space-y-4">
        @csrf

        <x-ui.input label="E-Mail-Adresse" name="email" type="email" :value="old('email')" required autofocus autocomplete="username" />
        <x-ui.input label="Passwort" name="password" type="password" required autocomplete="current-password" />

        <div class="flex items-center justify-between">
            <x-ui.checkbox label="Angemeldet bleiben" name="remember" />
            <a href="{{ route('password.request') }}" class="text-[13px] font-medium text-accent hover:underline">Passwort vergessen?</a>
        </div>

        <x-ui.button type="submit" class="w-full">Anmelden</x-ui.button>
    </form>
</x-layouts::guest>
