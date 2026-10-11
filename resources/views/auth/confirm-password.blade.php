<x-layouts::guest title="Passwort bestätigen" heading="Passwort bestätigen" subheading="Bitte bestätige dein Passwort, um fortzufahren.">
    <form method="POST" action="{{ route('password.confirm.store') }}" class="space-y-4">
        @csrf

        <x-ui.input label="Passwort" name="password" type="password" required autofocus autocomplete="current-password" />

        <x-ui.button type="submit" class="w-full">Bestätigen</x-ui.button>
    </form>
</x-layouts::guest>
