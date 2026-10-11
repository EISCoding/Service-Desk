<div class="max-w-2xl">
    <x-settings-nav />

    <x-ui.card title="Benachrichtigungen" description="Die Glocke oben rechts zeigt neue Benachrichtigungen an. E-Mails gehen an {{ auth()->user()->email }}." :padding="false">
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
</div>
