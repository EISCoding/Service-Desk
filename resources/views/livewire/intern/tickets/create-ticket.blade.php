<div>
    <x-ui.modal name="create-ticket" wire:model="show" max-width="2xl" title="Neues Ticket">
        <form wire:submit="save" id="create-ticket-form" class="space-y-4">
            {{-- Customer --}}
            <div>
                <p class="mb-1.5 text-[13px] font-medium">Kunde <span class="text-ink-faint" aria-hidden="true">*</span></p>
                @if ($this->customer)
                    <div class="flex items-center justify-between gap-3 rounded-[10px] bg-surface-muted px-3 py-2 text-sm">
                        <span><span class="font-medium">{{ $this->customer->company }}</span> <span class="text-ink-muted">· {{ $this->customer->number }}</span></span>
                        <button type="button" wire:click="clearCustomer" class="text-[12px] text-ink-muted hover:text-ink">Ändern</button>
                    </div>
                @else
                    <div class="relative">
                        <input type="search" wire:model.live.debounce.250ms="customerSearch" placeholder="Firma oder Kundennummer suchen …" aria-label="Kunde suchen"
                            @class([
                                'block h-9 w-full rounded-[10px] bg-surface px-3 text-sm shadow-xs ring-1 ring-inset placeholder:text-ink-faint focus:ring-2 focus:outline-none',
                                'ring-separator focus:ring-accent' => ! $errors->has('customerId'),
                                'ring-red-500' => $errors->has('customerId'),
                            ])>
                        @if ($this->customerResults->isNotEmpty())
                            <ul class="absolute z-20 mt-1 w-full overflow-hidden rounded-xl bg-surface shadow-pop">
                                @foreach ($this->customerResults as $result)
                                    <li>
                                        <button type="button" wire:click="selectCustomer({{ $result->id }})" class="block w-full px-3 py-2 text-left text-sm hover:bg-surface-muted">
                                            <span class="font-medium">{{ $result->company }}</span>
                                            <span class="text-[12px] text-ink-muted">· {{ $result->number }} {{ $result->city }}</span>
                                        </button>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                    @error('customerId') <p class="mt-1.5 text-[13px] text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                @endif
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.select label="Ansprechpartner" wire:model="contactId" :placeholder="$this->customer ? 'Kein Ansprechpartner' : 'Erst Kunde wählen'" :options="$this->contactOptions" :disabled="! $this->customer" />
                <x-ui.select label="Gruppe" wire:model="groupId" placeholder="Ohne Gruppe" :options="$this->groupOptions" />
            </div>

            <x-ui.input label="Betreff" wire:model="subject" required maxlength="255" />

            <x-ui.rich-editor label="Beschreibung" wire:model="body" toolbar="reply" placeholder="Was ist das Anliegen?" />

            <div class="flex flex-wrap items-end justify-between gap-4">
                <x-ui.select label="Priorität" wire:model="priority" :options="$priorityOptions" class="w-40" />
                <x-ui.checkbox label="Mir zuweisen" description="Status „In Arbeit“" wire:model="assignToMe" />
            </div>
        </form>

        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="show = false">Abbrechen</x-ui.button>
            <x-ui.button type="submit" form="create-ticket-form">Ticket anlegen</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>
</div>
