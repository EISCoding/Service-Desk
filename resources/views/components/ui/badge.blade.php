{{-- Pill for status, priority, roles. --}}
@props([
    'color' => 'gray', // gray | blue | indigo | purple | teal | green | yellow | orange | red
    'dot' => false,
])

@php
    $colors = [
        'gray' => 'bg-zinc-500/10 text-zinc-700 dark:bg-zinc-400/15 dark:text-zinc-300',
        'blue' => 'bg-blue-500/10 text-blue-700 dark:bg-blue-400/15 dark:text-blue-300',
        'indigo' => 'bg-indigo-500/10 text-indigo-700 dark:bg-indigo-400/15 dark:text-indigo-300',
        'purple' => 'bg-purple-500/10 text-purple-700 dark:bg-purple-400/15 dark:text-purple-300',
        'teal' => 'bg-teal-500/10 text-teal-700 dark:bg-teal-400/15 dark:text-teal-300',
        'green' => 'bg-green-500/10 text-green-700 dark:bg-green-400/15 dark:text-green-300',
        'yellow' => 'bg-yellow-500/15 text-yellow-800 dark:bg-yellow-400/15 dark:text-yellow-300',
        'orange' => 'bg-orange-500/10 text-orange-700 dark:bg-orange-400/15 dark:text-orange-300',
        'red' => 'bg-red-500/10 text-red-700 dark:bg-red-400/15 dark:text-red-300',
    ];
    $dots = [
        'gray' => 'bg-zinc-400', 'blue' => 'bg-blue-500', 'indigo' => 'bg-indigo-500', 'purple' => 'bg-purple-500',
        'teal' => 'bg-teal-500', 'green' => 'bg-green-500', 'yellow' => 'bg-yellow-500', 'orange' => 'bg-orange-500',
        'red' => 'bg-red-500',
    ];
    $color = array_key_exists($color, $colors) ? $color : 'gray';
@endphp

<span {{ $attributes->class(['inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium whitespace-nowrap', $colors[$color]]) }}>
    @if ($dot)
        <span class="size-1.5 rounded-full {{ $dots[$color] }}" aria-hidden="true"></span>
    @endif
    {{ $slot }}
</span>
