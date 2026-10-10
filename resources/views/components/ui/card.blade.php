@props([
    'title' => null,
    'description' => null,
    'padding' => true,
])

<section {{ $attributes->class('overflow-hidden rounded-card bg-surface shadow-[0_1px_3px_rgb(0_0_0/0.04)] ring-1 ring-separator/80') }}>
    @if ($title || isset($actions))
        <header class="flex items-start justify-between gap-4 px-5 pt-4 pb-1">
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
        <footer class="flex items-center justify-end gap-2 border-t border-separator bg-surface-muted/50 px-5 py-3">
            {{ $footer }}
        </footer>
    @endisset
</section>
