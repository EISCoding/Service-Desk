<div class="max-w-3xl space-y-4">
    @can('create', \App\Models\CustomerNote::class)
        <x-ui.card>
            <form wire:submit="add" class="space-y-3">
                <x-ui.textarea wire:model="body" rows="3" placeholder="Interne Notiz zu diesem Kunden … (im Portal nicht sichtbar)" aria-label="Neue Notiz" />
                <div class="flex justify-end">
                    <x-ui.button type="submit" size="sm">Notiz speichern</x-ui.button>
                </div>
            </form>
        </x-ui.card>
    @endcan

    @forelse ($this->notes as $note)
        <article wire:key="note-{{ $note->id }}" @class([
            'rounded-card p-4 ring-1',
            'bg-yellow-500/10 ring-yellow-500/25' => $note->is_pinned,
            'bg-surface ring-separator/80' => ! $note->is_pinned,
        ])>
            <header class="mb-2 flex items-center justify-between gap-3">
                <p class="text-[13px] text-ink-muted">
                    <span class="font-medium text-ink">{{ $note->author?->name ?? 'Unbekannt' }}</span>
                    · <time datetime="{{ $note->created_at->toIso8601String() }}" title="{{ $note->created_at->isoFormat('D. MMMM YYYY, HH:mm') }}">{{ $note->created_at->diffForHumans() }}</time>
                    @if ($note->updated_at->gt($note->created_at->addMinute()))
                        · bearbeitet
                    @endif
                </p>
                <div class="flex items-center gap-0.5">
                    @can('pin', $note)
                        <x-ui.button variant="ghost" size="sm" wire:click="togglePin({{ $note->id }})" :title="$note->is_pinned ? 'Lösen' : 'Anheften'">
                            <x-dynamic-component :component="$note->is_pinned ? 'heroicon-s-bookmark' : 'heroicon-o-bookmark'" class="size-4" aria-hidden="true" />
                            <span class="sr-only">{{ $note->is_pinned ? 'Lösen' : 'Anheften' }}</span>
                        </x-ui.button>
                    @endcan
                    @can('update', $note)
                        <x-ui.button variant="ghost" size="sm" wire:click="edit({{ $note->id }})">Bearbeiten</x-ui.button>
                    @endcan
                    @can('delete', $note)
                        <x-ui.button variant="ghost" size="sm" class="text-red-600 dark:text-red-400" wire:click="delete({{ $note->id }})" wire:confirm="Notiz wirklich löschen?">Löschen</x-ui.button>
                    @endcan
                </div>
            </header>

            @if ($editingId === $note->id)
                <form wire:submit="saveEdit" class="space-y-3">
                    <x-ui.textarea wire:model="editBody" rows="4" aria-label="Notiz bearbeiten" />
                    <div class="flex justify-end gap-2">
                        <x-ui.button variant="secondary" size="sm" wire:click="cancelEdit">Abbrechen</x-ui.button>
                        <x-ui.button type="submit" size="sm">Speichern</x-ui.button>
                    </div>
                </form>
            @else
                <p class="text-sm whitespace-pre-line">{{ $note->body }}</p>
            @endif
        </article>
    @empty
        <x-ui.card :padding="false">
            <x-ui.empty-state icon="pencil-square" title="Noch keine Notizen" description="Interne Hinweise zu diesem Kunden – für Kunden nie sichtbar." />
        </x-ui.card>
    @endforelse
</div>
