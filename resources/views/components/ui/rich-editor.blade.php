{{--
    WYSIWYG editor (TipTap, resources/js/editor.js) bound via wire:model; the HTML is sanitized on save.
    toolbar: basic (portal, descriptions) | reply (replies, signatures) | full (templates, mail design)
    placeholders: offer {placeholders} as chips (templates)
    wire:model.live sends changes shortly after typing stops
--}}
@props([
    'label' => null,
    'placeholder' => '',
    'id' => null,
    'toolbar' => 'basic',
    'placeholders' => false,
])

@php
    $model = $attributes->wire('model')->value();
    $id ??= 'editor-'.\Illuminate\Support\Str::slug($model ?: \Illuminate\Support\Str::random(6));
    $error = $model ? ($errors ?? null)?->first($model) : null;
    $full = $toolbar === 'full';
    $rich = in_array($toolbar, ['reply', 'full'], true);
    $options = [
        'label' => $label ?: ($placeholder ?: 'Text'),
        'placeholder' => $placeholder,
        'placeholders' => $placeholders,
        'live' => $attributes->wire('model')->hasModifier('live'),
        'invalid' => (bool) $error,
        'describedBy' => $error ? $id.'-error' : null,
    ];
    $tool = 'grid size-8 shrink-0 place-items-center rounded-lg text-ink-muted transition hover:bg-ink/[0.06] hover:text-ink aria-pressed:bg-accent/12 aria-pressed:text-accent aria-expanded:bg-ink/[0.06] aria-expanded:text-ink';
    $group = 'flex items-center gap-0.5';
    $divider = '<span class="mx-1 h-5 w-px shrink-0 bg-hairline" aria-hidden="true"></span>';
@endphp

<x-ui.field :$id :$label :$error :class="$attributes->get('class')">
    <div
        wire:ignore
        x-data="richEditor($wire.entangle(@js($model)), @js($options))"
        data-rich-editor
        @class([
            'rich-editor rounded-xl bg-surface ring-1 ring-inset transition focus-within:ring-2',
            'ring-hairline focus-within:ring-accent/60' => ! $error,
            'ring-red-500 focus-within:ring-red-500' => $error,
        ])
    >
        <div role="toolbar" aria-label="Formatierung" aria-controls="{{ $id }}" class="relative flex flex-wrap items-center gap-y-0.5 border-b border-hairline px-1.5 py-1" x-on:keydown.escape.stop="linkOpen = false">
            @if ($full)
                <label class="sr-only" for="{{ $id }}-block">Absatzformat</label>
                <select id="{{ $id }}-block" x-model="heading" x-on:change="run('heading', $event.target.value)"
                    class="h-8 rounded-lg bg-transparent pr-7 pl-2 text-[13px] text-ink hover:bg-ink/[0.06] focus:outline-none focus-visible:ring-2 focus-visible:ring-accent">
                    <option value="paragraph">Text</option>
                    <option value="h2">Überschrift</option>
                    <option value="h3">Zwischenüberschrift</option>
                </select>
                {!! $divider !!}
            @endif

            <div class="{{ $group }}">
                @foreach ([['bold', 'bold', 'Fett (⌘B)'], ['italic', 'italic', 'Kursiv (⌘I)'], ['underline', 'underline', 'Unterstrichen (⌘U)']] as [$command, $icon, $title])
                    <button type="button" class="{{ $tool }}" title="{{ $title }}" aria-label="{{ $title }}" x-bind:aria-pressed="active.{{ $command }} ? 'true' : 'false'" x-on:mousedown.prevent x-on:click="run('{{ $command }}')">
                        <x-dynamic-component :component="'bx-'.$icon" class="size-[18px]" aria-hidden="true" />
                    </button>
                @endforeach

                @if ($rich)
                    {{-- Text color from the palette --}}
                    <div x-data="{ open: false }" class="relative" x-on:keydown.escape.stop="open = false">
                        <button type="button" class="{{ $tool }}" title="Textfarbe" aria-label="Textfarbe" aria-haspopup="true" x-bind:aria-expanded="open ? 'true' : 'false'" x-on:mousedown.prevent x-on:click="open = ! open">
                            <span class="relative grid place-items-center">
                                <x-bx-font-color class="size-[18px]" aria-hidden="true" />
                                <span class="absolute -bottom-0.5 h-[3px] w-3.5 rounded-full" x-bind:style="{ background: color || 'currentColor' }"></span>
                            </span>
                        </button>
                        <div x-show="open" x-cloak x-on:click.outside="open = false" x-transition.opacity class="absolute top-full left-0 z-30 mt-1 w-44 rounded-xl bg-surface p-1.5 shadow-pop">
                            <button type="button" x-on:mousedown.prevent x-on:click="run('color', null); open = false" class="flex w-full items-center gap-2 rounded-lg px-2 py-1.5 text-left text-[13px] hover:bg-surface-muted">
                                <span class="size-4 rounded-full bg-ink" aria-hidden="true"></span> Standard
                            </button>
                            @foreach (\App\Support\RichTextAttributes::COLORS as $hex => $name)
                                <button type="button" x-on:mousedown.prevent x-on:click="run('color', '{{ $hex }}'); open = false" class="flex w-full items-center gap-2 rounded-lg px-2 py-1.5 text-left text-[13px] hover:bg-surface-muted">
                                    <span class="size-4 rounded-full" style="background: {{ $hex }}" aria-hidden="true"></span> {{ $name }}
                                    <x-bx-check x-show="color === '{{ $hex }}'" class="ml-auto size-4 text-accent" aria-hidden="true" />
                                </button>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

            {!! $divider !!}

            <div class="{{ $group }}">
                <button type="button" class="{{ $tool }}" title="Aufzählung" aria-label="Aufzählung" x-bind:aria-pressed="active.bulletList ? 'true' : 'false'" x-on:mousedown.prevent x-on:click="run('bulletList')">
                    <x-bx-list-ul class="size-[18px]" aria-hidden="true" />
                </button>
                <button type="button" class="{{ $tool }}" title="Nummerierung" aria-label="Nummerierung" x-bind:aria-pressed="active.orderedList ? 'true' : 'false'" x-on:mousedown.prevent x-on:click="run('orderedList')">
                    <x-bx-list-ol class="size-[18px]" aria-hidden="true" />
                </button>
                @if ($rich)
                    <button type="button" class="{{ $tool }}" title="Zitat" aria-label="Zitat" x-bind:aria-pressed="active.blockquote ? 'true' : 'false'" x-on:mousedown.prevent x-on:click="run('blockquote')">
                        <x-bxs-quote-alt-left class="size-4" aria-hidden="true" />
                    </button>
                @endif
            </div>

            @if ($full)
                {!! $divider !!}
                <div class="{{ $group }}">
                    @foreach ([['left', 'align-left', 'Linksbündig'], ['center', 'align-middle', 'Zentriert'], ['right', 'align-right', 'Rechtsbündig']] as [$value, $icon, $title])
                        <button type="button" class="{{ $tool }}" title="{{ $title }}" aria-label="{{ $title }}" x-bind:aria-pressed="align === '{{ $value }}' ? 'true' : 'false'" x-on:mousedown.prevent x-on:click="run('align', '{{ $value }}')">
                            <x-dynamic-component :component="'bx-'.$icon" class="size-[18px]" aria-hidden="true" />
                        </button>
                    @endforeach
                </div>
            @endif

            {!! $divider !!}

            <div class="{{ $group }}">
                <button type="button" class="{{ $tool }}" title="Link (⌘K)" aria-label="Link" x-bind:aria-pressed="active.link && ! active.button ? 'true' : 'false'" x-on:mousedown.prevent x-on:click="openLink()">
                    <x-bx-link class="size-[18px]" aria-hidden="true" />
                </button>
                @if ($full)
                    <button type="button" class="{{ $tool }}" title="Button" aria-label="Button" x-bind:aria-pressed="active.button ? 'true' : 'false'" x-on:mousedown.prevent x-on:click="openLink(true)">
                        <x-bx-pointer class="size-[18px]" aria-hidden="true" />
                    </button>
                    <button type="button" class="{{ $tool }}" title="Trennlinie" aria-label="Trennlinie" x-on:mousedown.prevent x-on:click="run('rule')">
                        <x-bx-minus class="size-[18px]" aria-hidden="true" />
                    </button>
                @endif
            </div>

            @if ($placeholders)
                {!! $divider !!}
                <div x-data="{ open: false }" class="relative" x-on:keydown.escape.stop="open = false">
                    <button type="button" class="inline-flex h-8 items-center gap-1 rounded-lg px-2 text-[13px] text-ink-muted transition hover:bg-ink/[0.06] hover:text-ink aria-expanded:bg-ink/[0.06]" aria-haspopup="true" x-bind:aria-expanded="open ? 'true' : 'false'" x-on:mousedown.prevent x-on:click="open = ! open">
                        <x-bx-code-curly class="size-[18px]" aria-hidden="true" /> Platzhalter
                    </button>
                    <div x-show="open" x-cloak x-on:click.outside="open = false" x-transition.opacity class="absolute top-full right-0 z-30 mt-1 max-h-72 w-80 overflow-y-auto rounded-xl bg-surface p-1.5 shadow-pop sm:right-auto sm:left-0">
                        @foreach (\App\Support\Placeholders::DESCRIPTIONS as $key => $description)
                            <button type="button" x-on:mousedown.prevent x-on:click="run('placeholder', '{{ $key }}'); open = false" class="block w-full rounded-lg px-2.5 py-1.5 text-left hover:bg-surface-muted">
                                <span class="placeholder-chip">{{ '{'.$key.'}' }}</span>
                                <span class="mt-0.5 block text-[12px] text-ink-muted">{{ $description }}</span>
                            </button>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Link / button form --}}
            <div x-show="linkOpen" x-cloak x-on:click.outside="linkOpen = false" x-transition.opacity class="absolute top-full left-1.5 z-30 mt-1 w-[min(22rem,calc(100%-0.75rem))] space-y-2.5 rounded-xl bg-surface p-3 shadow-pop">
                <p class="text-[13px] font-semibold" x-text="linkButton ? 'Button' : 'Link'"></p>
                <template x-if="linkNeedsText">
                    <div>
                        <label for="{{ $id }}-link-text" class="mb-1 block text-[12px] font-medium text-ink-muted">Text</label>
                        <input id="{{ $id }}-link-text" type="text" x-model="linkText" x-on:keydown.enter.prevent="applyLink()" x-bind:placeholder="linkButton ? 'z. B. Termin bestätigen' : 'Wie die Adresse'"
                            class="block h-8 w-full rounded-lg bg-surface px-2.5 text-[13px] ring-1 ring-ink/15 ring-inset focus:ring-2 focus:ring-accent focus:outline-none">
                    </div>
                </template>
                <div>
                    <label for="{{ $id }}-link-url" class="mb-1 block text-[12px] font-medium text-ink-muted">Adresse</label>
                    <input id="{{ $id }}-link-url" x-ref="linkUrl" type="text" inputmode="url" x-model="linkUrl" x-on:keydown.enter.prevent="applyLink()" placeholder="https://… oder name@firma.de"
                        class="block h-8 w-full rounded-lg bg-surface px-2.5 text-[13px] ring-1 ring-ink/15 ring-inset focus:ring-2 focus:ring-accent focus:outline-none">
                </div>
                @if ($full)
                    <label class="flex items-center gap-2 text-[13px]">
                        <input type="checkbox" x-model="linkButton" class="size-4 rounded accent-[var(--accent)]"> Als Button anzeigen
                    </label>
                @endif
                <div class="flex items-center justify-end gap-2">
                    <button type="button" x-show="active.link" x-on:click="removeLink()" class="mr-auto text-[13px] font-medium text-red-600 hover:underline dark:text-red-400">Entfernen</button>
                    <button type="button" x-on:click="linkOpen = false" class="h-8 rounded-lg px-3 text-[13px] font-medium text-ink-muted hover:bg-ink/[0.06]">Abbrechen</button>
                    <button type="button" x-on:click="applyLink()" class="h-8 rounded-lg bg-accent-fill px-3 text-[13px] font-medium text-on-accent hover:bg-accent-fill-hover">Übernehmen</button>
                </div>
            </div>
        </div>

        <div x-ref="content" id="{{ $id }}" x-on:keydown.meta.k.prevent.stop="openLink()" x-on:keydown.ctrl.k.prevent.stop="openLink()"></div>
    </div>
</x-ui.field>
