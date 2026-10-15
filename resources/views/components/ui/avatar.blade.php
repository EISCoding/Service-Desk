{{-- Round avatar with initials; the color follows the name, so people are easy to tell apart. --}}
@props(['name', 'size' => 'md'])

@php
    $initials = collect(preg_split('/\s+/', trim($name)))->filter()->map(fn ($part) => mb_substr($part, 0, 1))->take(2)->implode('');
    $palette = [
        'from-sky-400 to-blue-500', 'from-indigo-400 to-violet-500', 'from-fuchsia-400 to-pink-500', 'from-orange-400 to-red-500',
        'from-amber-400 to-orange-500', 'from-emerald-400 to-teal-500', 'from-cyan-400 to-sky-600', 'from-zinc-400 to-zinc-500',
    ];
    $gradient = $palette[crc32(mb_strtolower(trim($name))) % count($palette)];
@endphp

<span title="{{ $name }}" {{ $attributes->class([
    'inline-grid shrink-0 place-items-center rounded-full bg-gradient-to-b font-semibold text-white shadow-[inset_0_0_0_0.5px_rgb(0_0_0/0.08)]',
    $gradient,
    'size-6 text-[10px]' => $size === 'sm',
    'size-8 text-[12px]' => $size === 'md',
    'size-10 text-[14px]' => $size === 'lg',
]) }}>
    <span aria-hidden="true">{{ mb_strtoupper($initials) }}</span>
    <span class="sr-only">{{ $name }}</span>
</span>
