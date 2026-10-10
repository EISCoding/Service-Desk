{{-- Round avatar with initials. --}}
@props(['name', 'size' => 'md'])

@php
    $initials = collect(preg_split('/\s+/', trim($name)))->filter()->map(fn ($part) => mb_substr($part, 0, 1))->take(2)->implode('');
@endphp

<span title="{{ $name }}" {{ $attributes->class([
    'inline-grid shrink-0 place-items-center rounded-full bg-gradient-to-b from-zinc-400 to-zinc-500 font-semibold text-white dark:from-zinc-500 dark:to-zinc-600',
    'size-6 text-[10px]' => $size === 'sm',
    'size-8 text-[12px]' => $size === 'md',
]) }}>
    <span aria-hidden="true">{{ mb_strtoupper($initials) }}</span>
    <span class="sr-only">{{ $name }}</span>
</span>
