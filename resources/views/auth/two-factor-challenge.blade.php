<x-layouts::guest title="Zwei-Faktor-Anmeldung" heading="Bestätigungscode">
    <div x-data="{ recovery: {{ $errors->has('recovery_code') ? 'true' : 'false' }} }">
        <p class="mb-4 text-sm text-ink-muted" x-show="! recovery">
            Gib den 6-stelligen Code aus deiner Authenticator-App ein.
        </p>
        <p class="mb-4 text-sm text-ink-muted" x-show="recovery" x-cloak>
            Gib einen deiner Wiederherstellungscodes ein.
        </p>

        <form method="POST" action="{{ route('two-factor.login.store') }}" class="space-y-4">
            @csrf

            <div x-show="! recovery">
                <x-ui.input label="Code" name="code" inputmode="numeric" autocomplete="one-time-code" autofocus x-bind:disabled="recovery" placeholder="123456" />
            </div>
            <div x-show="recovery" x-cloak>
                <x-ui.input label="Wiederherstellungscode" name="recovery_code" autocomplete="off" x-bind:disabled="! recovery" />
            </div>

            <x-ui.button type="submit" class="w-full">Bestätigen</x-ui.button>
        </form>

        <button type="button" x-on:click="recovery = ! recovery" class="mt-4 w-full text-center text-[13px] font-medium text-accent hover:underline">
            <span x-show="! recovery">Wiederherstellungscode verwenden</span>
            <span x-show="recovery" x-cloak>Code aus der App verwenden</span>
        </button>
    </div>
</x-layouts::guest>
