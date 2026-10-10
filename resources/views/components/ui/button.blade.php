@props([
    'variant' => 'primary', // primary | secondary | ghost | danger
    'size' => 'md',         // sm | md | lg
    'href' => null,
    'type' => 'button',
    'icon' => null,         // heroicon name, e.g. "plus"
])

@php
    $classes = \Illuminate\Support\Arr::toCssClasses([
        'inline-flex items-center justify-center gap-1.5 font-medium whitespace-nowrap select-none transition-colors duration-150',
        'focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent',
        'disabled:opacity-50 disabled:pointer-events-none data-loading:opacity-60 data-loading:pointer-events-none',
        match ($size) {
            'sm' => 'h-8 rounded-lg px-3 text-[13px]',
            'lg' => 'h-11 rounded-xl px-5 text-[15px]',
            default => 'h-9 rounded-[10px] px-4 text-sm',
        },
        match ($variant) {
            'secondary' => 'bg-surface text-ink ring-1 ring-inset ring-separator shadow-xs hover:bg-surface-muted',
            'ghost' => 'text-ink-muted hover:bg-surface-muted hover:text-ink',
            'danger' => 'bg-red-600 text-white shadow-xs hover:bg-red-700 dark:bg-red-500 dark:hover:bg-red-600',
            default => 'bg-accent text-white shadow-xs hover:bg-accent-hover',
        },
    ]);
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->class($classes) }}>
        @if ($icon)
            <x-dynamic-component :component="'heroicon-o-'.$icon" class="size-4 shrink-0" aria-hidden="true" />
        @endif
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->class($classes) }}>
        @if ($icon)
            <x-dynamic-component :component="'heroicon-o-'.$icon" class="size-4 shrink-0" aria-hidden="true" />
        @endif
        {{ $slot }}
    </button>
@endif
