@props([
    'variant' => 'primary', // primary | secondary | ghost | danger
    'size' => 'md',         // sm | md | lg
    'href' => null,
    'type' => 'button',
    'icon' => null,         // Boxicons name, e.g. "plus" (see App\Support\Icon)
])

@php
    // Submit buttons outside their form (modal and card footers) show the loading state via wire:loading.
    if ($type === 'submit' && $attributes->has('form') && ! $attributes->has('wire:loading.attr')) {
        $attributes = $attributes->merge(['wire:loading.attr' => 'data-loading']);
    }

    $classes = \Illuminate\Support\Arr::toCssClasses([
        'group inline-flex items-center justify-center gap-1.5 font-medium whitespace-nowrap select-none transition-colors duration-150',
        'focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent',
        'disabled:opacity-50 disabled:pointer-events-none data-loading:opacity-60 data-loading:pointer-events-none in-data-loading:opacity-60 in-data-loading:pointer-events-none',
        match ($size) {
            'sm' => 'h-8 rounded-lg px-2.5 text-[13px]',
            'lg' => 'h-11 rounded-[10px] px-5 text-[15px]',
            default => 'h-9 rounded-lg px-3.5 text-sm',
        },
        match ($variant) {
            'secondary' => 'bg-surface text-ink ring-1 ring-inset ring-separator hover:bg-surface-muted',
            'ghost' => 'text-ink-muted hover:bg-surface-muted hover:text-ink',
            'danger' => 'bg-red-600 text-white shadow-xs hover:bg-red-700 dark:bg-red-500 dark:hover:bg-red-600',
            default => 'bg-accent-fill text-on-accent shadow-[0_1px_2px_rgb(16_24_40/0.12)] hover:bg-accent-fill-hover',
        },
    ]);
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->class($classes) }}>
        @if ($icon)
            <x-dynamic-component :component="\App\Support\Icon::component($icon)" class="size-4 shrink-0" aria-hidden="true" />
        @endif
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->class($classes) }}>
        {{-- Spinner while the request triggered by this button (or its form) runs --}}
        <svg class="hidden size-4 shrink-0 animate-spin group-data-loading:block in-data-loading:block" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-opacity=".25" stroke-width="3" />
            <path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round" />
        </svg>
        @if ($icon)
            <x-dynamic-component :component="\App\Support\Icon::component($icon)" class="size-4 shrink-0 group-data-loading:hidden in-data-loading:hidden" aria-hidden="true" />
        @endif
        {{ $slot }}
    </button>
@endif
