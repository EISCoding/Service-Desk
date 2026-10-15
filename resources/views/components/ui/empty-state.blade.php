@props([
    'icon' => 'collection',
    'title',
    'description' => null,
])

<div {{ $attributes->class('flex flex-col items-center justify-center px-6 py-16 text-center') }}>
    <div class="grid size-14 place-items-center rounded-2xl bg-accent/10 text-accent">
        <x-dynamic-component :component="\App\Support\Icon::component($icon)" class="size-7" aria-hidden="true" />
    </div>
    <h3 class="mt-4 text-[15px] font-semibold text-ink">{{ $title }}</h3>
    @if ($description)
        <p class="mt-1 max-w-sm text-[13px] text-ink-muted">{{ $description }}</p>
    @endif
    @if ($slot->isNotEmpty())
        <div class="mt-5 flex items-center gap-2">{{ $slot }}</div>
    @endif
</div>
