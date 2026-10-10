@props([
    'sortable' => false,
    'direction' => null, // asc | desc | null (column is sortable but not active)
])

<th
    scope="col"
    @if ($sortable) aria-sort="{{ match ($direction) { 'asc' => 'ascending', 'desc' => 'descending', default => 'none' } }}" @endif
    {{ $attributes->whereDoesntStartWith('wire:click')->class('px-4 py-2.5 font-medium whitespace-nowrap first:pl-5 last:pr-5') }}
>
    @if ($sortable)
        <button type="button" {{ $attributes->whereStartsWith('wire:click') }} class="group inline-flex items-center gap-1 hover:text-ink focus-visible:outline-2 focus-visible:outline-accent">
            {{ $slot }}
            @if ($direction === 'asc')
                <x-heroicon-m-chevron-up class="size-3.5" aria-hidden="true" />
            @elseif ($direction === 'desc')
                <x-heroicon-m-chevron-down class="size-3.5" aria-hidden="true" />
            @else
                <x-heroicon-m-chevron-up-down class="size-3.5 opacity-0 group-hover:opacity-100" aria-hidden="true" />
            @endif
        </button>
    @else
        {{ $slot }}
    @endif
</th>
