@php
    $canReply = auth()->user()->can('reply', $ticket);
    $canNote = auth()->user()->can('addNote', $ticket);
    $isNote = $mode === 'note';
@endphp

<section aria-label="Antworten" @class([
    'rounded-card p-4 ring-1 sm:p-5',
    'bg-yellow-500/10 ring-yellow-500/30' => $isNote,
    'bg-surface ring-separator/80' => ! $isNote,
])>
    @if ($canReply && $canNote)
        <div role="tablist" class="mb-3 inline-grid grid-cols-2 gap-0.5 rounded-[10px] bg-ink/[0.05] p-0.5">
            <button type="button" role="tab" aria-selected="{{ $isNote ? 'false' : 'true' }}" wire:click="setMode('reply')" @class(['rounded-lg px-3 py-1 text-[13px] font-medium', 'bg-surface shadow-xs' => ! $isNote, 'text-ink-muted hover:text-ink' => $isNote])>Antworten</button>
            <button type="button" role="tab" aria-selected="{{ $isNote ? 'true' : 'false' }}" wire:click="setMode('note')" @class(['inline-flex items-center justify-center gap-1 rounded-lg px-3 py-1 text-[13px] font-medium', 'bg-surface shadow-xs' => $isNote, 'text-ink-muted hover:text-ink' => ! $isNote])>
                <x-heroicon-m-lock-closed class="size-3.5" aria-hidden="true" /> Interne Notiz
            </button>
        </div>
    @endif

    <form wire:submit="send" class="space-y-3">
        @unless ($isNote)
            @if ($canMail)
                <div class="space-y-1.5 text-[13px]">
                    <p><span class="text-ink-muted">An:</span> <span class="font-medium">{{ $recipient['name'] ?? '' }}</span> &lt;{{ $recipient['email'] }}&gt;</p>
                    <div class="flex flex-wrap items-center gap-1.5">
                        <span class="text-ink-muted">CC:</span>
                        @foreach ($cc as $index => $address)
                            <span wire:key="cc-{{ $address['email'] }}" class="inline-flex items-center gap-1 rounded-full bg-ink/[0.06] py-0.5 pr-1 pl-2.5">
                                {{ $address['email'] }}
                                <button type="button" wire:click="removeCc({{ $index }})" class="rounded-full text-ink-faint hover:text-ink">
                                    <x-heroicon-m-x-mark class="size-3.5" aria-hidden="true" /><span class="sr-only">{{ $address['email'] }} entfernen</span>
                                </button>
                            </span>
                        @endforeach
                        <input type="email" wire:model="newCc" wire:keydown.enter.prevent="addCc" placeholder="Adresse hinzufügen …" aria-label="CC-Adresse hinzufügen"
                            class="h-7 w-48 rounded-full bg-transparent px-2.5 text-[13px] ring-1 ring-separator ring-inset placeholder:text-ink-faint focus:ring-2 focus:ring-accent focus:outline-none">
                    </div>
                    @error('newCc') <p class="text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                </div>
            @else
                <p class="text-[13px] text-ink-muted">Keine E-Mail-Adresse oder kein Postfach vorhanden – die Antwort wird nur gespeichert (sichtbar im Kundenportal).</p>
            @endif
        @endunless

        <x-ui.rich-editor wire:model="body" placeholder="Nachricht schreiben …" />
        @if ($isNote)
            <p class="-mt-1 text-[12px] text-ink-muted">Interne Notizen sind für Kunden nie sichtbar.</p>
        @endif

        @if ($files)
            <ul class="flex flex-wrap gap-2" aria-label="Ausgewählte Dateien">
                @foreach ($files as $index => $file)
                    <li wire:key="file-{{ $index }}" class="flex items-center gap-2 rounded-lg bg-surface-muted px-2.5 py-1.5 text-[13px] ring-1 ring-separator">
                        <x-heroicon-o-paper-clip class="size-4 text-ink-muted" aria-hidden="true" />
                        <span class="max-w-48 truncate">{{ $file->getClientOriginalName() }}</span>
                        <span class="text-[11px] text-ink-muted">{{ \Illuminate\Support\Number::fileSize($file->getSize(), precision: 1) }}</span>
                        <button type="button" wire:click="removeFile({{ $index }})" class="text-ink-faint hover:text-ink">
                            <x-heroicon-m-x-mark class="size-4" aria-hidden="true" /><span class="sr-only">Entfernen</span>
                        </button>
                    </li>
                @endforeach
            </ul>
        @endif
        @error('files') <p class="text-[13px] text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
        @error('files.*') <p class="text-[13px] text-red-600 dark:text-red-400">{{ $message }}</p> @enderror

        <div class="flex flex-wrap items-center justify-between gap-3">
            <label class="inline-flex cursor-pointer items-center gap-1.5 text-[13px] font-medium text-ink-muted hover:text-ink">
                <x-heroicon-o-paper-clip class="size-4" aria-hidden="true" />
                <span wire:loading.remove wire:target="files">Dateien anhängen</span>
                <span wire:loading wire:target="files">Wird hochgeladen …</span>
                <input type="file" wire:model="files" multiple class="sr-only">
            </label>

            <div class="flex items-center gap-2">
                @unless ($isNote)
                    <x-ui.select wire:model="statusAfter" placeholder="Status unverändert" :options="$statusOptions" aria-label="Status danach" class="w-48" />
                @endunless
                <x-ui.button type="submit" icon="paper-airplane" wire:loading.attr="disabled" wire:target="files,send">
                    {{ $isNote ? 'Notiz speichern' : 'Antwort senden' }}
                </x-ui.button>
            </div>
        </div>
    </form>
</section>
