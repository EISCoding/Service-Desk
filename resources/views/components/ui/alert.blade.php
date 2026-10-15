@props([
    'type' => 'info', // info | success | warning | error
])

@php
    [$classes, $icon] = match ($type) {
        'success' => ['bg-green-500/10 text-green-800 dark:text-green-300', 'check-circle'],
        'warning' => ['bg-yellow-500/15 text-yellow-900 dark:text-yellow-200', 'error'],
        'error' => ['bg-red-500/10 text-red-800 dark:text-red-300', 'x-circle'],
        default => ['bg-accent/10 text-ink', 'info-circle'],
    };
@endphp

<div role="{{ $type === 'error' ? 'alert' : 'status' }}" {{ $attributes->class(['flex gap-2.5 rounded-xl px-3.5 py-3 text-[13px] leading-5', $classes]) }}>
    <x-dynamic-component :component="\App\Support\Icon::component($icon, solid: true)" class="mt-px size-4 shrink-0 opacity-80" aria-hidden="true" />
    <div class="min-w-0">{{ $slot }}</div>
</div>
