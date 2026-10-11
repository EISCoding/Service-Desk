<x-layouts::guest title="Anmelden · Kundenportal" heading="Kundenportal" subheading="Bitte melden Sie sich mit Ihrer E-Mail-Adresse an.">
    <form method="POST" action="{{ route('portal.login.store') }}" class="space-y-4">
        @csrf

        <x-ui.input label="E-Mail-Adresse" name="email" type="email" :value="old('email')" required autofocus autocomplete="username" />
        <x-ui.input label="Passwort" name="password" type="password" required autocomplete="current-password" />

        <div class="flex items-center justify-between">
            <x-ui.checkbox label="Angemeldet bleiben" name="remember" />
            <a href="{{ route('portal.password.request') }}" class="text-[13px] font-medium text-accent hover:underline">Passwort vergessen?</a>
        </div>

        <x-ui.button type="submit" class="w-full">Anmelden</x-ui.button>
    </form>

    <x-slot:footer>
        Noch keinen Zugang? Wenden Sie sich bitte an uns, wir schicken Ihnen eine Einladung.
    </x-slot:footer>
</x-layouts::guest>
