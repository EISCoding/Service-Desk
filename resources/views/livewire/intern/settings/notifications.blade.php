<div class="max-w-2xl">
    <x-settings-nav />

    <x-ui.card title="Benachrichtigungen" description="Die Glocke oben rechts zeigt neue Benachrichtigungen an. E-Mails gehen an {{ auth()->user()->email }}, Push an die Geräte unten." :padding="false">
        <form wire:submit="save" id="notifications-form">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-separator text-[12px] text-ink-muted">
                        <th scope="col" class="px-5 py-2 text-left font-medium">Ereignis</th>
                        @foreach ($channels as $label)
                            <th scope="col" class="w-20 px-3 py-2 text-center font-medium">{{ $label }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-separator">
                    @foreach ($events as $event)
                        <tr wire:key="event-{{ $event->value }}">
                            <th scope="row" class="px-5 py-3 text-left font-normal">
                                <span class="font-medium">{{ $event->label() }}</span>
                                <span class="block text-[13px] text-ink-muted">{{ $event->description() }}</span>
                            </th>
                            @foreach ($channels as $channel => $label)
                                <td class="px-3 py-3 text-center">
                                    <input type="checkbox" wire:model="settings.{{ $event->value }}.{{ $channel }}" aria-label="{{ $event->label() }}: {{ $label }}"
                                        class="size-4 cursor-pointer rounded border-separator accent-(--accent)">
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </form>
        <x-slot:footer>
            <x-ui.button type="submit" form="notifications-form">Speichern</x-ui.button>
        </x-slot:footer>
    </x-ui.card>

    {{-- Web push on this device (browser or app on the home screen) --}}
    <x-ui.card title="Push auf Handy und Computer" description="Benachrichtigungen erscheinen auch, wenn {{ config('app.name') }} nicht geöffnet ist." class="mt-6">
        <div x-data="webPush(@js($publicKey))" class="space-y-4">
            <template x-if="! supported">
                <p class="text-sm text-ink-muted">Dieser Browser unterstützt keine Push-Benachrichtigungen.
                    <span x-show="ios">Auf dem iPhone: Seite im Safari über <strong>Teilen → Zum Home-Bildschirm</strong> hinzufügen und dort öffnen (ab iOS 16.4).</span>
                </p>
            </template>
            <template x-if="supported">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <p class="flex items-center gap-2 text-sm">
                        <span class="size-2 rounded-full" x-bind:class="subscribed ? 'bg-green-500' : 'bg-zinc-400'" aria-hidden="true"></span>
                        <span x-show="subscribed">Auf diesem Gerät aktiv.</span>
                        <span x-show="! subscribed && permission !== 'denied'">Auf diesem Gerät noch nicht aktiviert.</span>
                        <span x-show="! subscribed && permission === 'denied'" class="text-red-700 dark:text-red-400">Im Browser blockiert – bitte in den Website-Einstellungen erlauben.</span>
                    </p>
                    <p x-show="error" x-text="error" x-cloak class="w-full text-[13px] text-red-700 dark:text-red-400" role="alert"></p>
                    <div class="flex gap-2">
                        <x-ui.button x-show="! subscribed" x-on:click="enable" x-bind:disabled="busy || permission === 'denied'" icon="bxs-bell-ring">Auf diesem Gerät aktivieren</x-ui.button>
                        <x-ui.button x-show="subscribed" x-cloak variant="secondary" x-on:click="disable" x-bind:disabled="busy">Deaktivieren</x-ui.button>
                    </div>
                </div>
            </template>
        </div>

        @if ($this->devices->isNotEmpty())
            <ul class="mt-5 divide-y divide-hairline border-t border-hairline" aria-label="Geräte">
                @foreach ($this->devices as $device)
                    <li wire:key="device-{{ $device->id }}" class="flex items-center gap-3 py-2.5">
                        <x-bx-mobile class="size-5 text-ink-muted" aria-hidden="true" />
                        <span class="min-w-0 flex-1">
                            <span class="block text-sm font-medium">{{ $device->deviceName() }}</span>
                            <span class="text-[12px] text-ink-muted">seit {{ $device->created_at->isoFormat('D. MMM YYYY') }}</span>
                        </span>
                        <x-ui.button variant="ghost" size="sm" wire:click="removeDevice({{ $device->id }})">Entfernen</x-ui.button>
                    </li>
                @endforeach
            </ul>
        @endif

        <x-slot:footer>
            <x-ui.button variant="secondary" icon="send" wire:click="sendTest" :disabled="$this->devices->isEmpty()">Test senden</x-ui.button>
        </x-slot:footer>
    </x-ui.card>
</div>
