@props([
    'title' => null,
    'description' => null,
    'padding' => true,
])

<section {{ $attributes->class('overflow-hidden rounded-card bg-surface shadow-card') }}>
    @if ($title || isset($actions))
        <header class="flex items-start justify-between gap-4 px-5 pt-5 pb-1">
            <div class="min-w-0">
                @if ($title)
                    <h2 class="text-[15px] font-semibold text-ink">{{ $title }}</h2>
                @endif
                @if ($description)
                    <p class="mt-0.5 text-[13px] text-ink-muted">{{ $description }}</p>
                @endif
            </div>
            @isset($actions)
                <div class="flex shrink-0 items-center gap-2">{{ $actions }}</div>
            @endisset
        </header>
    @endif

    <div @class(['px-5 py-4' => $padding])>
        {{ $slot }}
    </div>

    @isset($footer)
        <footer class="flex items-center justify-end gap-2 border-t border-hairline bg-surface-muted/40 px-5 py-3">
            {{ $footer }}
        </footer>
    @endisset
</section>
