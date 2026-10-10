<x-layouts::guest title="Kundenportal" heading="Passwort gespeichert">
    <x-ui.alert type="success">
        Ihr Zugang ist eingerichtet. Sie melden sich im Kundenportal mit Ihrer E-Mail-Adresse und dem eben festgelegten Passwort an.
    </x-ui.alert>

    @if (Route::has('portal.login'))
        <x-ui.button :href="route('portal.login')" class="mt-4 w-full">Zur Anmeldung</x-ui.button>
    @endif
</x-layouts::guest>
