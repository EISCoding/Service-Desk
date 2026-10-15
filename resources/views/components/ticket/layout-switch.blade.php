{{-- Switch between ticket list and board; the choice is remembered for "Tickets" in the navigation. --}}
@props(['current', 'compact' => false])

@php
    $items = [
        'list' => ['Liste', 'list-ul', route('intern.tickets.index', ['darstellung' => 'liste'])],
        'board' => ['Board', 'columns', route('intern.tickets.board')],
    ];
@endphp

<nav aria-label="Darstellung" {{ $attributes->class('inline-flex gap-0.5 rounded-[10px] bg-ink/[0.06] p-0.5') }}>
    @foreach ($items as $key => [$label, $icon, $href])
        <a href="{{ $href }}" wire:navigate @if ($current === $key) aria-current="page" @endif title="{{ $label }}"
            @class([
                'inline-flex items-center gap-1.5 rounded-lg py-1 text-[13px] font-medium',
                'px-2' => $compact,
                'px-3' => ! $compact,
                'bg-surface text-ink shadow-[0_1px_2px_rgb(0_0_0/0.08),0_0_0_0.5px_rgb(0_0_0/0.04)] dark:bg-white/15' => $current === $key,
                'text-ink-muted hover:text-ink' => $current !== $key,
            ])>
            <x-dynamic-component :component="\App\Support\Icon::component($icon)" class="size-4" aria-hidden="true" />
            <span @class(['sr-only' => $compact])>{{ $label }}</span>
        </a>
    @endforeach
</nav>
