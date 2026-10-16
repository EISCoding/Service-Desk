@php
    // Temporary uploads are only previewed when the browser can show them (validated on save).
    $preview = fn ($upload) => $upload?->isPreviewable() ? $upload->temporaryUrl() : null;
    $appIconPreview = $preview($appIcon) ?? ($branding->hasAppIcon() ? $branding->iconUrl('apple-touch-icon.png') : null);
    $maskablePreview = $preview($appIcon) ?? ($branding->hasAppIcon() ? $branding->iconUrl('icon-192.png') : null);
    $faviconPreview = $preview($favicon) ?? $branding->favicon()['url'];
    $pad = $appIcon ? 'p-[14%]' : '';
    // Live preview of the color field, only with a valid value (it ends up in a style attribute).
    $previewBackground = preg_match('/^#[0-9a-fA-F]{6}$/', $iconBackground) ? $iconBackground : $branding->iconBackground();
@endphp

<div class="max-w-3xl">
    <x-ui.page-header title="Erscheinungsbild" description="Logo, App-Icon, Favicon, Akzentfarbe und die Startseite für den Mitarbeiterbereich, das Kundenportal und E-Mails." />

    <form wire:submit="save" class="space-y-6">
        {{-- Logos --}}
        <x-ui.card title="Logo" description="Erscheint neben dem Firmennamen in der Seitenleiste, auf den Anmeldeseiten und in E-Mails. Am besten quadratisch, PNG mit transparentem Hintergrund.">
            <div class="grid gap-4 sm:grid-cols-2">
                @foreach ([
                    ['logo', 'Für helle Flächen', 'Wird auch in E-Mails verwendet.', $logo, $branding->logoUrl(), 'bg-white'],
                    ['logoDark', 'Für dunkle Flächen', 'Optional. Ohne eigenes Logo gilt das helle.', $logoDark, $branding->hasDarkLogo() ? $branding->darkLogoUrl() : null, 'bg-[#1c1c1e]'],
                ] as [$field, $label, $hint, $upload, $current, $surface])
                    <div class="flex items-center gap-4 rounded-xl p-3 ring-1 ring-hairline">
                        <div class="grid size-20 shrink-0 place-items-center rounded-xl ring-1 ring-black/10 {{ $surface }}">
                            @if ($preview($upload))
                                <img src="{{ $preview($upload) }}" alt="Neues Logo: {{ $label }}" class="size-14 object-contain">
                            @elseif ($current)
                                <img src="{{ $current }}" alt="Aktuelles Logo: {{ $label }}" class="size-14 object-contain">
                            @else
                                <x-brand-mark size="lg" />
                            @endif
                        </div>
                        <div class="min-w-0 space-y-1">
                            <p class="text-[13px] font-semibold">{{ $label }}</p>
                            <p class="text-[12px] text-ink-muted">{{ $hint }}</p>
                            <div class="flex flex-wrap gap-x-3 gap-y-1">
                                <label class="inline-flex cursor-pointer items-center gap-1 text-[13px] font-medium text-accent hover:underline focus-within:underline">
                                    <span wire:loading.remove wire:target="{{ $field }}">{{ $current || $upload ? 'Anderes wählen' : 'Hochladen' }}</span>
                                    <span wire:loading wire:target="{{ $field }}">Wird hochgeladen …</span>
                                    <input type="file" wire:model="{{ $field }}" accept="image/png,image/jpeg,image/webp" class="sr-only" aria-label="{{ $label }}: Logo hochladen">
                                </label>
                                @if ($current)
                                    <button type="button" wire:click="remove('{{ $field }}')" wire:confirm="Logo entfernen?" class="text-[13px] font-medium text-red-600 hover:underline dark:text-red-400">Entfernen</button>
                                @endif
                            </div>
                            @error($field) <p class="text-[13px] text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                        </div>
                    </div>
                @endforeach
            </div>
            <p class="mt-3 text-[12px] text-ink-muted">PNG, JPG oder WebP, höchstens 1 MB.</p>
        </x-ui.card>

        {{-- App icon --}}
        <x-ui.card title="App-Icon" description="Für den Home-Bildschirm auf iPhone und Android, Push-Benachrichtigungen und Lesezeichen. Alle Größen werden automatisch erzeugt.">
            @unless ($gd)
                <x-ui.alert type="warning" class="mb-4">Auf dem Server fehlt die PHP-Erweiterung <strong>GD</strong>. Ohne sie können keine Icons erzeugt werden. In Plesk unter „PHP-Einstellungen“ aktivieren.</x-ui.alert>
            @endunless

            <div class="flex flex-col gap-6 sm:flex-row sm:items-start">
                {{-- Previews: iPhone (rounded square), Android (circle), browser tab --}}
                <div class="flex shrink-0 items-end gap-5" aria-hidden="true">
                    <figure class="flex flex-col items-center gap-1.5">
                        <span class="grid size-16 place-items-center overflow-hidden rounded-[22%] shadow-sm ring-1 ring-black/10" style="background: {{ $previewBackground }}">
                            @if ($appIconPreview)
                                <img src="{{ $appIconPreview }}" alt="" class="size-full object-contain {{ $pad }}">
                            @else
                                <img src="{{ asset('icons/apple-touch-icon.png') }}" alt="" class="size-full">
                            @endif
                        </span>
                        <figcaption class="text-[11px] text-ink-muted">iPhone</figcaption>
                    </figure>
                    <figure class="flex flex-col items-center gap-1.5">
                        <span class="grid size-14 place-items-center overflow-hidden rounded-full shadow-sm ring-1 ring-black/10" style="background: {{ $previewBackground }}">
                            @if ($maskablePreview)
                                <img src="{{ $maskablePreview }}" alt="" class="size-full object-contain {{ $appIcon ? 'p-[22%]' : '' }}">
                            @else
                                <img src="{{ asset('icons/icon-192.png') }}" alt="" class="size-full">
                            @endif
                        </span>
                        <figcaption class="text-[11px] text-ink-muted">Android</figcaption>
                    </figure>
                    <figure class="flex flex-col items-center gap-1.5">
                        <span class="flex h-9 w-40 items-center gap-2 rounded-t-lg bg-surface-muted px-2.5 ring-1 ring-hairline">
                            <img src="{{ $faviconPreview }}" alt="" class="size-4 object-contain">
                            <span class="truncate text-[12px]">{{ config('app.name') }}</span>
                        </span>
                        <figcaption class="text-[11px] text-ink-muted">Browser-Tab</figcaption>
                    </figure>
                </div>

                <div class="min-w-0 flex-1 space-y-4">
                    <div class="space-y-1">
                        <div class="flex flex-wrap gap-x-3 gap-y-1">
                            <label @class(['inline-flex items-center gap-1 text-[13px] font-medium', 'cursor-pointer text-accent hover:underline focus-within:underline' => $gd, 'text-ink-faint' => ! $gd])>
                                <span wire:loading.remove wire:target="appIcon">{{ $branding->hasAppIcon() || $appIcon ? 'Anderes App-Icon wählen' : 'App-Icon hochladen' }}</span>
                                <span wire:loading wire:target="appIcon">Wird hochgeladen …</span>
                                <input type="file" wire:model="appIcon" accept="image/png,image/jpeg,image/webp" class="sr-only" @disabled(! $gd) aria-label="App-Icon hochladen">
                            </label>
                            @if ($branding->hasAppIcon())
                                <button type="button" wire:click="remove('appIcon')" wire:confirm="App-Icon entfernen? Danach gilt wieder das Standard-Icon." class="text-[13px] font-medium text-red-600 hover:underline dark:text-red-400">Entfernen</button>
                            @endif
                        </div>
                        <p class="text-[12px] text-ink-muted">Quadratisch, mindestens 512 × 512 Pixel, PNG, JPG oder WebP bis 2 MB. Ein Logo mit transparentem Hintergrund wird mit etwas Rand auf die Hintergrundfarbe gesetzt.</p>
                        @error('appIcon') <p class="text-[13px] text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="icon-background" class="mb-1.5 block text-[13px] font-medium">Hintergrundfarbe</label>
                        <div class="flex items-center gap-2">
                            <input type="color" id="icon-background-picker" wire:model.live.debounce.250ms="iconBackground" aria-label="Hintergrundfarbe wählen" class="h-9 w-12 cursor-pointer rounded-[10px] bg-surface p-1 ring-1 ring-ink/15">
                            <input id="icon-background" type="text" wire:model.live.blur="iconBackground" maxlength="7" class="block h-9 w-28 rounded-[10px] bg-surface px-3 font-mono text-sm uppercase ring-1 ring-ink/15 ring-inset focus:ring-2 focus:ring-accent focus:outline-none">
                        </div>
                        <p class="mt-1 text-[12px] text-ink-muted">Füllt die Fläche hinter dem Icon auf iPhone und Android.</p>
                        @error('iconBackground') <p class="mt-1 text-[13px] text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>
        </x-ui.card>

        {{-- Favicon --}}
        <x-ui.card title="Favicon" description="Das kleine Symbol im Browser-Tab und in Lesezeichen. Ohne eigenes Favicon wird das App-Icon verwendet.">
            <div class="flex items-center gap-4">
                <span class="grid size-12 shrink-0 place-items-center rounded-xl bg-surface-muted ring-1 ring-hairline">
                    @if ($favicon && ! $preview($favicon))
                        <x-bx-file class="size-5 text-ink-muted" aria-hidden="true" />
                    @else
                        <img src="{{ $faviconPreview }}" alt="{{ $favicon ? 'Neues Favicon' : 'Aktuelles Favicon' }}" class="size-6 object-contain">
                    @endif
                </span>
                <div class="min-w-0 space-y-1">
                    <div class="flex flex-wrap gap-x-3 gap-y-1">
                        <label class="inline-flex cursor-pointer items-center gap-1 text-[13px] font-medium text-accent hover:underline focus-within:underline">
                            <span wire:loading.remove wire:target="favicon">{{ $favicon ? $favicon->getClientOriginalName() : 'Favicon hochladen' }}</span>
                            <span wire:loading wire:target="favicon">Wird hochgeladen …</span>
                            <input type="file" wire:model="favicon" accept="image/png,image/x-icon,image/vnd.microsoft.icon,.ico,image/jpeg,image/webp" class="sr-only" aria-label="Favicon hochladen">
                        </label>
                        @if (app(\App\Support\AppSettings::class)->get('favicon'))
                            <button type="button" wire:click="remove('favicon')" wire:confirm="Favicon entfernen?" class="text-[13px] font-medium text-red-600 hover:underline dark:text-red-400">Entfernen</button>
                        @endif
                    </div>
                    <p class="text-[12px] text-ink-muted">ICO oder PNG, quadratisch, bis 512 KB. PNG wird auf 32 × 32 Pixel verkleinert.</p>
                    @error('favicon') <p class="text-[13px] text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                </div>
            </div>
        </x-ui.card>

        {{-- Accent color --}}
        <x-ui.card title="Akzentfarbe" description="Für Buttons, Links und Markierungen. Die Farben sind für Hell und Dunkel abgestimmt.">
            <div role="radiogroup" aria-label="Akzentfarbe" class="flex flex-wrap gap-3">
                @foreach ($palette as $key => [$label, $light, , $dark])
                    <label class="flex cursor-pointer flex-col items-center gap-1.5 text-[12px] text-ink-muted">
                        <input type="radio" wire:model="accent" value="{{ $key }}" class="peer sr-only">
                        <span class="size-8 rounded-full ring-2 ring-transparent ring-offset-2 ring-offset-surface peer-checked:ring-ink/60 peer-focus-visible:ring-accent"
                            style="background: linear-gradient(135deg, {{ $light }} 50%, {{ $dark }} 50%)"></span>
                        {{ $label }}
                    </label>
                @endforeach
            </div>
            @error('accent') <p class="mt-2 text-[13px] text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
        </x-ui.card>

        {{-- Public home page ("/") --}}
        <x-ui.card title="Startseite" description="Die Seite direkt unter deiner Domain mit den Wegen zum Support. Leere Felder zeigen Standardtexte; als E-Mail erscheint das Standard-Postfach.">
            <x-slot:actions>
                <x-ui.button variant="secondary" size="sm" icon="link-external" :href="route('home')" target="_blank">Ansehen</x-ui.button>
            </x-slot:actions>
            <div class="space-y-4">
                <x-ui.input label="Überschrift" wire:model="homeHeading" maxlength="120" :placeholder="\App\Support\HomePage::DEFAULT_HEADING" />
                <x-ui.rich-editor label="Einleitung" wire:model="homeIntro" toolbar="reply" :placeholder="\App\Support\Html::toText(\App\Support\HomePage::defaultIntro())" />
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.input label="Telefon" type="tel" wire:model="homePhone" maxlength="40" placeholder="z. B. 07331 123 45-6" hint="Leer: kein Telefon auf der Startseite." />
                    <x-ui.input label="Erreichbarkeit" wire:model="homeHours" maxlength="120" placeholder="z. B. Mo–Fr 8–17 Uhr" />
                    <x-ui.input label="Impressum (Link)" type="url" wire:model="homeImprintUrl" maxlength="300" placeholder="https://…" />
                    <x-ui.input label="Datenschutz (Link)" type="url" wire:model="homePrivacyUrl" maxlength="300" placeholder="https://…" />
                </div>
            </div>
        </x-ui.card>

        <div class="flex justify-end">
            <x-ui.button type="submit" wire:loading.attr="disabled" wire:target="logo,logoDark,appIcon,favicon,save">Speichern</x-ui.button>
        </div>
    </form>
</div>
