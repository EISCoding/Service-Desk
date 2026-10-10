<div>
    <x-ui.card :padding="false">
        @if ($this->contacts->isEmpty())
            <x-ui.empty-state icon="user" title="Noch keine Ansprechpartner" description="Lege Ansprechpartner an, um Tickets und Portal-Zugänge zuzuordnen." />
        @else
            <x-ui.table>
                <x-slot:head>
                    <x-ui.table.heading>Name</x-ui.table.heading>
                    <x-ui.table.heading class="hidden md:table-cell">Kontakt</x-ui.table.heading>
                    <x-ui.table.heading class="hidden sm:table-cell">Status</x-ui.table.heading>
                </x-slot:head>

                @foreach ($this->contacts as $contact)
                    <x-ui.table.row wire:key="contact-{{ $contact->id }}">
                        <x-ui.table.cell>
                            <p class="font-medium">
                                @if ($contact->salutation)
                                    <span class="font-normal text-ink-muted">{{ $contact->salutation->label() }}</span>
                                @endif
                                {{ $contact->fullName() }}
                            </p>
                            @if ($contact->position)
                                <p class="text-[13px] text-ink-muted">{{ $contact->position }}</p>
                            @endif
                        </x-ui.table.cell>
                        <x-ui.table.cell class="hidden text-[13px] md:table-cell">
                            @if ($contact->email)
                                <a href="mailto:{{ $contact->email }}" class="text-accent hover:underline">{{ $contact->email }}</a>
                            @endif
                            <p class="text-ink-muted">{{ $contact->phone }}</p>
                        </x-ui.table.cell>
                        <x-ui.table.cell class="hidden sm:table-cell">
                            <div class="flex flex-wrap gap-1">
                                @if ($contact->is_primary)
                                    <x-ui.badge color="blue">Hauptansprechpartner</x-ui.badge>
                                @endif
                                @switch($contact->portalStatus())
                                    @case('active') <x-ui.badge color="green" dot>Portal aktiv</x-ui.badge> @break
                                    @case('invited') <x-ui.badge color="yellow" dot>Eingeladen</x-ui.badge> @break
                                    @case('not_invited') <x-ui.badge dot>Portal: nicht eingeladen</x-ui.badge> @break
                                @endswitch
                            </div>
                        </x-ui.table.cell>
                    </x-ui.table.row>
                @endforeach
            </x-ui.table>
        @endif
    </x-ui.card>
</div>
