<div class="max-w-3xl">
    <x-ui.page-header title="Neues Ticket" description="Beschreiben Sie Ihr Anliegen möglichst genau. Screenshots oder Dokumente können Sie anhängen." />

    @if ($previous = $this->previous())
        <x-ui.alert class="mb-4">Neues Ticket zu {{ $previous->reference() }} „{{ $previous->subject }}“.</x-ui.alert>
    @endif

    <x-ui.card>
        <form wire:submit="save" id="ticket-form" class="space-y-4">
            <div class="grid gap-4 sm:grid-cols-[1fr_12rem]">
                <x-ui.input label="Betreff" wire:model="subject" required maxlength="255" placeholder="z. B. Drucker im Büro druckt nicht" />
                <x-ui.select label="Priorität" wire:model="priority" :options="$priorityOptions" />
            </div>
            <x-ui.rich-editor label="Beschreibung" wire:model="body" placeholder="Was ist passiert? Seit wann? Wer ist betroffen?" />
            @include('livewire.portal.partials.files')
        </form>
        <x-slot:footer>
            <x-ui.button variant="secondary" :href="route('portal.home')" wire:navigate>Abbrechen</x-ui.button>
            <x-ui.button type="submit" form="ticket-form" icon="send" wire:loading.attr="disabled" wire:target="files,save">Ticket senden</x-ui.button>
        </x-slot:footer>
    </x-ui.card>
</div>
