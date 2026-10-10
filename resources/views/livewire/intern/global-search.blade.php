{{-- Global search: ⌘K / Strg+K focuses the field, arrow keys and Enter pick a result. --}}
<div
    class="relative w-full max-w-md"
    role="search"
    x-data="{
        open: false,
        active: 0,
        items() { return [...this.$refs.results?.querySelectorAll('a') ?? []] },
        move(step) {
            const items = this.items();
            if (! items.length) return;
            this.active = (this.active + step + items.length) % items.length;
            items[this.active].scrollIntoView({ block: 'nearest' });
        },
        go() { this.items()[this.active]?.click() },
    }"
    x-on:keydown.window.meta.k.prevent="$refs.search.focus(); $refs.search.select()"
    x-on:keydown.window.ctrl.k.prevent="$refs.search.focus(); $refs.search.select()"
    x-on:click.outside="open = false"
>
    <label for="global-search" class="sr-only">Tickets, Kunden und Ansprechpartner suchen</label>
    <x-heroicon-m-magnifying-glass class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-ink-faint" aria-hidden="true" />
    <input
        id="global-search"
        x-ref="search"
        type="search"
        wire:model.live.debounce.250ms="query"
        x-on:focus="open = true"
        x-on:input="open = true; active = 0"
        x-on:keydown.arrow-down.prevent="move(1)"
        x-on:keydown.arrow-up.prevent="move(-1)"
        x-on:keydown.enter.prevent="go()"
        x-on:keydown.escape="open = false; $el.blur()"
        placeholder="Suchen …"
        autocomplete="off"
        role="combobox"
        aria-controls="global-search-results"
        x-bind:aria-expanded="open"
        class="block h-9 w-full rounded-[10px] bg-ink/[0.05] pr-14 pl-9 text-sm text-ink placeholder:text-ink-faint focus:bg-surface focus:ring-2 focus:ring-accent focus:outline-none"
    >
    <kbd class="pointer-events-none absolute top-1/2 right-2.5 hidden -translate-y-1/2 rounded-md border border-separator px-1.5 font-sans text-[11px] text-ink-faint sm:block">⌘K</kbd>

    @if (mb_strlen(trim($query)) >= 2)
        <div x-show="open" x-cloak x-ref="results" id="global-search-results" class="absolute inset-x-0 top-full z-40 mt-1 max-h-[70vh] overflow-y-auto rounded-xl bg-surface p-1 shadow-lg ring-1 ring-separator">
            @forelse ($this->groups as $group)
                <p class="px-3 pt-2 pb-1 text-[11px] font-semibold tracking-wide text-ink-faint uppercase">{{ $group['label'] }}</p>
                @foreach ($group['items'] as $item)
                    <a href="{{ $item['url'] }}" wire:navigate wire:key="result-{{ $loop->parent->index }}-{{ $loop->index }}"
                        x-on:click="open = false"
                        x-on:mouseenter="active = items().indexOf($el)"
                        x-bind:class="items().indexOf($el) === active ? 'bg-ink/[0.06]' : ''"
                        class="block rounded-lg px-3 py-2">
                        <span class="block truncate text-sm font-medium">{{ $item['title'] }}</span>
                        <span class="block truncate text-[12px] text-ink-muted">{{ $item['meta'] }}</span>
                    </a>
                @endforeach
            @empty
                <p class="px-3 py-6 text-center text-sm text-ink-muted">Keine Treffer für „{{ $query }}“.</p>
            @endforelse
        </div>
    @endif
</div>
