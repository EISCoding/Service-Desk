@php
    $canReply = auth()->user()->can('reply', $ticket);
    $canNote = auth()->user()->can('addNote', $ticket);
    $isNote = $mode === 'note';
    $replyTo = $recipient['name'] ?? $recipient['email'] ?? null;
@endphp

{{--
    Always at hand: a slim bar at the bottom of the ticket that expands into
    the full editor (click, or the shortcuts R / N).
--}}
<section aria-label="Antworten" data-composer
    x-data="{ expanded: false, full: false, open(mode) { $wire.setMode(mode).then(() => { this.expanded = true; this.$nextTick(() => this.$root.querySelector('trix-editor')?.focus()) }) } }"
    x-on:composer-focus.window="open($event.detail.mode)"
    x-on:composer-sent.window="expanded = false; full = false"
    x-on:keydown.escape="full ? full = false : expanded = false"
    x-bind:class="full && 'fixed! inset-0! z-50! overflow-y-auto bg-canvas! pt-6! pb-10!'"
    class="sticky bottom-[calc(56px+env(safe-area-inset-bottom))] z-10 border-t border-hairline bg-canvas/92 px-4 py-3 backdrop-blur-2xl backdrop-saturate-150 sm:px-6 lg:bottom-0 lg:px-8"
>
    {{-- Collapsed --}}
    <div x-show="! expanded" class="flex items-center gap-2">
        <x-ui.avatar :name="auth()->user()->name" class="max-sm:hidden" />
        @if ($canReply)
            <button type="button" x-on:click="open('reply')" class="flex h-10 min-w-0 flex-1 items-center rounded-full bg-surface px-4 text-left text-sm text-ink-faint shadow-card hover:text-ink-muted">
                <span class="truncate">{{ $replyTo ? "Antworten an {$replyTo} …" : 'Antworten …' }}</span>
            </button>
        @endif
        @if ($canNote)
            <button type="button" x-on:click="open('note')" @class([
                'inline-flex h-10 shrink-0 items-center gap-1.5 rounded-full px-4 text-sm font-medium',
                'flex-1 bg-surface text-ink-faint shadow-card' => ! $canReply,
                'bg-amber-400/15 text-amber-800 hover:bg-amber-400/25 dark:text-amber-300' => $canReply,
            ])>
                <x-bx-lock-alt class="size-4" aria-hidden="true" /> {{ $canReply ? 'Notiz' : 'Interne Notiz schreiben …' }}
            </button>
        @endif
    </div>

    {{-- Expanded --}}
    <div x-show="expanded" x-cloak @class(['-mx-1 rounded-2xl p-1', 'bg-amber-400/10' => $isNote]) x-bind:class="full && 'mx-auto! max-w-4xl'">
        <div class="mb-3 flex items-center justify-between gap-3">
            @if ($canReply && $canNote)
                <div role="tablist" class="inline-flex gap-0.5 rounded-[10px] bg-ink/[0.06] p-0.5">
                    <button type="button" role="tab" aria-selected="{{ $isNote ? 'false' : 'true' }}" wire:click="setMode('reply')" @class(['rounded-lg px-3 py-1 text-[13px] font-medium', 'bg-surface shadow-[0_1px_2px_rgb(0_0_0/0.08)] dark:bg-white/15' => ! $isNote, 'text-ink-muted hover:text-ink' => $isNote])>Antworten</button>
                    <button type="button" role="tab" aria-selected="{{ $isNote ? 'true' : 'false' }}" wire:click="setMode('note')" @class(['inline-flex items-center justify-center gap-1 rounded-lg px-3 py-1 text-[13px] font-medium', 'bg-surface shadow-[0_1px_2px_rgb(0_0_0/0.08)] dark:bg-white/15' => $isNote, 'text-ink-muted hover:text-ink' => ! $isNote])>
                        <x-bx-lock-alt class="size-3.5" aria-hidden="true" /> Interne Notiz
                    </button>
                </div>
            @else
                <p class="text-[13px] font-semibold">{{ $isNote ? 'Interne Notiz' : 'Antworten' }}</p>
            @endif
            <div class="flex items-center gap-1">
                <button type="button" x-on:click="full = ! full; $nextTick(() => $root.querySelector('trix-editor')?.focus())" class="rounded-full p-1.5 text-ink-faint hover:bg-ink/[0.06] hover:text-ink" x-bind:aria-pressed="full">
                    <x-bx-fullscreen class="size-5" aria-hidden="true" x-show="! full" />
                    <x-bx-exit-fullscreen class="size-5" aria-hidden="true" x-show="full" x-cloak />
                    <span class="sr-only">Vollbild</span>
                </button>
                <button type="button" x-on:click="expanded = false; full = false" class="rounded-full p-1.5 text-ink-faint hover:bg-ink/[0.06] hover:text-ink">
                    <x-bx-chevron-down class="size-5" aria-hidden="true" /><span class="sr-only">Einklappen</span>
                </button>
            </div>
        </div>

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
                                        <x-bx-x class="size-3.5" aria-hidden="true" /><span class="sr-only">{{ $address['email'] }} entfernen</span>
                                    </button>
                                </span>
                            @endforeach
                            <input type="email" wire:model="newCc" wire:keydown.enter.prevent="addCc" placeholder="Adresse hinzufügen …" aria-label="CC-Adresse hinzufügen"
                                class="h-7 w-48 rounded-full bg-transparent px-2.5 text-[13px] ring-1 ring-ink/15 ring-inset placeholder:text-ink-faint focus:ring-2 focus:ring-accent focus:outline-none">
                        </div>
                        @error('newCc') <p class="text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                    </div>
                @else
                    <p class="text-[13px] text-ink-muted">Keine E-Mail-Adresse oder kein Postfach vorhanden – die Antwort wird nur gespeichert (sichtbar im Kundenportal).</p>
                @endif
            @endunless

            <div x-bind:class="full ? '[&_trix-editor]:min-h-[55vh]!' : '[&_trix-editor]:max-h-[40vh] [&_trix-editor]:overflow-y-auto'">
                <x-ui.rich-editor wire:model="body" placeholder="Nachricht schreiben …" />
            </div>
            @if ($isNote)
                <p class="-mt-1 text-[12px] text-ink-muted">Interne Notizen sind für Kunden nie sichtbar.</p>
            @else
                <p class="-mt-1 text-[12px] text-ink-muted">
                    {{ auth()->user()->signature ? 'Deine Signatur wird automatisch angehängt.' : 'Noch keine Signatur hinterlegt.' }}
                    <a href="{{ route('intern.settings.signature') }}" wire:navigate class="text-accent underline decoration-accent/40 underline-offset-2 hover:decoration-accent">{{ auth()->user()->signature ? 'Bearbeiten' : 'Signatur einrichten' }}</a>
                </p>
            @endif

            @if ($files)
                <ul class="flex flex-wrap gap-2" aria-label="Ausgewählte Dateien">
                    @foreach ($files as $index => $file)
                        <li wire:key="file-{{ $index }}" class="flex items-center gap-2 rounded-lg bg-surface px-2.5 py-1.5 text-[13px] shadow-card">
                            <x-bx-paperclip class="size-4 text-ink-muted" aria-hidden="true" />
                            <span class="max-w-48 truncate">{{ $file->getClientOriginalName() }}</span>
                            <span class="text-[11px] text-ink-muted">{{ \Illuminate\Support\Number::fileSize($file->getSize(), precision: 1) }}</span>
                            <button type="button" wire:click="removeFile({{ $index }})" class="text-ink-faint hover:text-ink">
                                <x-bx-x class="size-4" aria-hidden="true" /><span class="sr-only">Entfernen</span>
                            </button>
                        </li>
                    @endforeach
                </ul>
            @endif
            @error('files') <p class="text-[13px] text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
            @error('files.*') <p class="text-[13px] text-red-600 dark:text-red-400">{{ $message }}</p> @enderror

            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex flex-wrap items-center gap-4">
                    <label class="inline-flex cursor-pointer items-center gap-1.5 text-[13px] font-medium text-ink-muted hover:text-ink">
                        <x-bx-paperclip class="size-4" aria-hidden="true" />
                        <span wire:loading.remove wire:target="files">Dateien anhängen</span>
                        <span wire:loading wire:target="files">Wird hochgeladen …</span>
                        <input type="file" wire:model="files" multiple class="sr-only">
                    </label>

                    @if ($this->snippets->isNotEmpty())
                        {{-- Text snippets, inserted at the cursor with filled placeholders --}}
                        <div x-data="{ open: false }" class="relative" x-on:keydown.escape.stop="open = false">
                            <button type="button" x-on:click="open = ! open" x-bind:aria-expanded="open" class="inline-flex items-center gap-1.5 text-[13px] font-medium text-ink-muted hover:text-ink">
                                <x-bx-copy-alt class="size-4" aria-hidden="true" /> Textbaustein
                            </button>
                            <div x-show="open" x-cloak x-on:click.outside="open = false" x-transition.opacity class="absolute bottom-full left-0 z-20 mb-1 max-h-64 w-64 overflow-y-auto rounded-xl bg-surface p-1 shadow-pop">
                                @foreach ($this->snippets as $snippet)
                                    <button type="button" wire:key="snippet-{{ $snippet->id }}" class="block w-full truncate rounded-lg px-3 py-2 text-left text-sm hover:bg-surface-muted"
                                        x-on:click="open = false; $wire.snippet({{ $snippet->id }}).then(html => $root.closest('section').querySelector('trix-editor').editor.insertHTML(html))">{{ $snippet->name }}</button>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>

                <div class="flex items-center gap-2">
                    @unless ($isNote)
                        <x-ui.select wire:model="statusAfter" placeholder="Status unverändert" :options="$statusOptions" aria-label="Status danach" class="w-48" />
                    @endunless
                    @if (! $isNote && $ticket->status !== \App\Enums\TicketStatus::Closed)
                        <x-ui.button variant="secondary" x-on:click="$wire.statusAfter = 'closed'; $wire.send()" wire:loading.attr="disabled" wire:target="files,send" title="Antwort senden und Ticket schließen">Senden &amp; schließen</x-ui.button>
                    @endif
                    <x-ui.button type="submit" icon="send" wire:loading.attr="disabled" wire:target="files,send">
                        {{ $isNote ? 'Notiz speichern' : 'Antwort senden' }}
                    </x-ui.button>
                </div>
            </div>
        </form>

        <p class="mt-3 hidden text-[12px] text-ink-faint lg:block" aria-hidden="true">
            Tastenkürzel: <kbd class="rounded border border-hairline px-1 font-sans">R</kbd> antworten ·
            <kbd class="rounded border border-hairline px-1 font-sans">N</kbd> interne Notiz ·
            <kbd class="rounded border border-hairline px-1 font-sans">E</kbd> Status ·
            <kbd class="rounded border border-hairline px-1 font-sans">J</kbd> / <kbd class="rounded border border-hairline px-1 font-sans">K</kbd> nächstes / vorheriges Ticket ·
            <kbd class="rounded border border-hairline px-1 font-sans">F</kbd> Liste aus/ein ·
            <kbd class="rounded border border-hairline px-1 font-sans">Esc</kbd> einklappen
        </p>
    </div>
</section>
