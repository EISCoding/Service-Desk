@props([
    'title',
    'description' => null,
])

<div {{ $attributes->class('mb-6 flex flex-wrap items-end justify-between gap-4') }}>
    <div class="min-w-0">
        <h1 class="text-[28px] leading-tight font-bold tracking-tight text-ink">{{ $title }}</h1>
        @if ($description)
            <p class="mt-1 text-[15px] text-ink-muted">{{ $description }}</p>
        @endif
    </div>
    @isset($actions)
        <div class="flex shrink-0 items-center gap-2">{{ $actions }}</div>
    @endisset
</div>
