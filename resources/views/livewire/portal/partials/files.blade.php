{{-- Attachment picker of portal forms ($files, removeFile()) --}}
<div class="space-y-2">
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

    <label class="inline-flex cursor-pointer items-center gap-1.5 text-[13px] font-medium text-ink-muted hover:text-ink">
        <x-heroicon-o-paper-clip class="size-4" aria-hidden="true" />
        <span wire:loading.remove wire:target="files">Dateien anhängen</span>
        <span wire:loading wire:target="files">Wird hochgeladen …</span>
        <input type="file" wire:model="files" multiple class="sr-only">
    </label>
</div>
