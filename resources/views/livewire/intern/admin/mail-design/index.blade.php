@php
    $colorField = 'h-9 w-12 shrink-0 cursor-pointer rounded-[10px] bg-surface p-1 ring-1 ring-ink/15';
    $hexField = 'block h-9 w-28 rounded-[10px] bg-surface px-3 font-mono text-sm uppercase ring-1 ring-ink/15 ring-inset focus:ring-2 focus:ring-accent focus:outline-none';
    $choice = 'flex cursor-pointer items-center justify-center rounded-lg px-3 py-1.5 text-[13px] font-medium text-ink-muted ring-1 ring-hairline transition hover:text-ink has-checked:bg-accent/10 has-checked:text-accent has-checked:ring-accent/40 has-focus-visible:ring-2 has-focus-visible:ring-accent';
@endphp

<div class="grid gap-6 xl:grid-cols-[minmax(0,34rem)_minmax(0,1fr)]">
    <div class="min-w-0">
        <x-ui.page-header title="Mail-Design" description="So sehen alle Mails an Kunden aus: Ticket-Antworten, automatische Mails und Portal-Mails." />

        <form wire:submit="save" class="space-y-6">
            <x-ui.card title="Farben und Schrift">
                <div class="space-y-5">
                    <div class="space-y-2">
                        <x-ui.checkbox wire:model.live="customAccent" label="Eigene Akzentfarbe" description="Für Buttons, Links und den Streifen über der Nachricht. Sonst gilt die Farbe aus dem Erscheinungsbild." />
                        @if ($customAccent)
                            <div class="flex items-center gap-2 pl-7">
                                <input type="color" wire:model.live.debounce.250ms="accent" aria-label="Akzentfarbe wählen" class="{{ $colorField }}">
                                <input type="text" wire:model.live.blur="accent" maxlength="7" aria-label="Akzentfarbe als Hex-Wert" class="{{ $hexField }}">
                            </div>
                        @endif
                        @error('accent') <p class="pl-7 text-[13px] text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="mail-background" class="mb-1.5 block text-[13px] font-medium">Hintergrund</label>
                        <div class="flex items-center gap-2">
                            <input type="color" wire:model.live.debounce.250ms="background" aria-label="Hintergrundfarbe wählen" class="{{ $colorField }}">
                            <input id="mail-background" type="text" wire:model.live.blur="background" maxlength="7" class="{{ $hexField }}">
                        </div>
                        @error('background') <p class="mt-1 text-[13px] text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                    </div>

                    <x-ui.select label="Schrift" wire:model.live="font" :options="collect($fonts)->map(fn ($font) => $font[0])->all()" hint="Nur Schriften, die jedes Mailprogramm kennt." />
                </div>
            </x-ui.card>

            <x-ui.card title="Kopf">
                <div class="space-y-5">
                    <fieldset>
                        <legend class="mb-1.5 text-[13px] font-medium">Logo</legend>
                        <div class="grid grid-cols-3 gap-2">
                            @foreach (['left' => 'Links', 'center' => 'Zentriert', 'none' => 'Ohne Logo'] as $value => $text)
                                <label class="{{ $choice }}">
                                    <input type="radio" wire:model.live="logo" value="{{ $value }}" class="sr-only">{{ $text }}
                                </label>
                            @endforeach
                        </div>
                        @unless ($hasLogo)
                            <p class="mt-2 text-[12px] text-ink-muted">Noch kein Logo hochgeladen. Das geht unter <a href="{{ route('intern.admin.branding') }}" wire:navigate class="font-medium text-accent hover:underline">Erscheinungsbild</a>.</p>
                        @endunless
                    </fieldset>

                    @if ($logo !== 'none')
                        <fieldset>
                            <legend class="mb-1.5 text-[13px] font-medium">Logo-Größe</legend>
                            <div class="grid grid-cols-3 gap-2">
                                @foreach ($logoSizes as $size => $text)
                                    <label class="{{ $choice }}">
                                        <input type="radio" wire:model.live="logoSize" value="{{ $size }}" class="sr-only">{{ $text }}
                                    </label>
                                @endforeach
                            </div>
                        </fieldset>
                    @endif

                    <x-ui.checkbox wire:model.live="showName" label="Firmenname anzeigen" description="Ohne Logo steht der Name immer da." />

                    <x-ui.rich-editor label="Kopfzeile (optional)" wire:model.live="header" toolbar="reply" placeholder="z. B. Hotline 0123 456 78 · Mo–Fr 8–17 Uhr" />
                </div>
            </x-ui.card>

            <x-ui.card title="Nachricht">
                <div class="space-y-4">
                    <x-ui.checkbox wire:model.live="accentBar" label="Farbstreifen über der Nachricht" />
                    <x-ui.checkbox wire:model.live="rounded" label="Abgerundete Ecken" />
                    <x-ui.input label="Hinweis unter Ticket-Mails" wire:model.live.debounce.500ms="replyHint" maxlength="300" :placeholder="\App\Support\MailBranding::DEFAULT_REPLY_HINT" hint="Leer: der Standardtext." />
                </div>
            </x-ui.card>

            <x-ui.card title="Fußzeile" description="Steht klein unter jeder Mail, z. B. Anschrift, Telefon, Website und Pflichtangaben. Leer: nur der Firmenname.">
                <x-ui.rich-editor label="Fußzeile" wire:model.live="footer" toolbar="reply" placeholder="Firma GmbH · Musterstraße 1 · 12345 Musterstadt" />
            </x-ui.card>

            <div class="flex flex-wrap items-center justify-between gap-3">
                <x-ui.button variant="secondary" type="button" wire:click="restoreDefaults" wire:confirm="Alle Einstellungen auf den Standard zurücksetzen? Gespeichert wird erst mit „Speichern“.">Standard wiederherstellen</x-ui.button>
                <x-ui.button type="submit" wire:target="save">Speichern</x-ui.button>
            </div>
        </form>
    </div>

    {{-- Live preview of the current (unsaved) design --}}
    <div class="min-w-0 xl:sticky xl:top-20 xl:self-start" x-data="{ width: 'desktop' }">
        <x-ui.card :padding="false">
            <div class="flex flex-wrap items-center gap-3 border-b border-hairline px-4 py-3">
                <h2 class="text-[15px] font-semibold">Vorschau</h2>
                <div role="group" aria-label="Breite der Vorschau" class="inline-flex gap-0.5 rounded-[10px] bg-ink/[0.06] p-0.5">
                    <button type="button" x-on:click="width = 'desktop'" x-bind:aria-pressed="width === 'desktop'" class="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1 text-[13px] font-medium text-ink-muted aria-pressed:bg-surface aria-pressed:text-ink aria-pressed:shadow-xs dark:aria-pressed:bg-white/15">
                        <x-bx-desktop class="size-4" aria-hidden="true" /> Computer
                    </button>
                    <button type="button" x-on:click="width = 'mobile'" x-bind:aria-pressed="width === 'mobile'" class="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1 text-[13px] font-medium text-ink-muted aria-pressed:bg-surface aria-pressed:text-ink aria-pressed:shadow-xs dark:aria-pressed:bg-white/15">
                        <x-bx-mobile class="size-4" aria-hidden="true" /> Handy
                    </button>
                </div>
                <span wire:loading.delay class="text-[12px] text-ink-muted">Aktualisiert …</span>
                <a href="{{ route('intern.admin.settings.mail-preview') }}" target="_blank" class="ml-auto inline-flex items-center gap-1 text-[13px] font-medium text-accent hover:underline" title="Zeigt die gespeicherte Fassung">
                    Gespeicherte Fassung <x-bx-link-external class="size-4" aria-hidden="true" />
                </a>
            </div>
            <div class="bg-surface-muted/60 p-3">
                <iframe
                    title="Vorschau der Mail"
                    sandbox
                    srcdoc="{{ $this->preview }}"
                    x-bind:class="width === 'mobile' ? 'w-[375px]' : 'w-full'"
                    class="mx-auto block h-[640px] max-w-full rounded-xl bg-white ring-1 ring-hairline transition-[width]"
                ></iframe>
            </div>
            <form wire:submit="sendTest" class="flex flex-wrap items-end gap-2 border-t border-hairline px-4 py-3">
                <x-ui.input label="Testmail an" type="email" wire:model="testRecipient" class="min-w-56 flex-1" />
                <x-ui.button type="submit" variant="secondary" icon="send" wire:target="sendTest">Testmail senden</x-ui.button>
                <p class="w-full text-[12px] text-ink-muted">Geht über das Standard-Postfach raus und zeigt die aktuellen, auch ungespeicherten Einstellungen.</p>
            </form>
        </x-ui.card>
    </div>
</div>
