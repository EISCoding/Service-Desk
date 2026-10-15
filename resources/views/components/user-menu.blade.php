{{--
    Menu of the signed-in staff member: appearance, settings, sign out.
    placement: top (mobile top bar, opens downwards) | sidebar (bottom of the sidebar, opens upwards)
--}}
@props(['placement' => 'top'])

@php
    $user = auth()->user();
    $sidebar = $placement === 'sidebar';
@endphp

<div x-data="{ open: false }" x-on:keydown.escape.window="open = false" {{ $attributes->class('relative') }}>
    <button
        type="button"
        x-on:click="open = ! open"
        x-bind:aria-expanded="open"
        aria-haspopup="true"
        @if ($sidebar) data-tip="{{ $user->name }}" @endif
        @class([
            'flex items-center gap-2.5 focus-visible:outline-2 focus-visible:outline-accent',
            'w-full rounded-lg p-1.5 text-left hover:bg-ink/[0.04] group-data-[sidebar=collapsed]/shell:justify-center' => $sidebar,
            'rounded-full p-0.5 hover:bg-surface-muted' => ! $sidebar,
        ])
    >
        <x-ui.avatar :name="$user->name" aria-hidden="true" />
        @if ($sidebar)
            <span class="min-w-0 flex-1 leading-tight group-data-[sidebar=collapsed]/shell:hidden">
                <span class="block truncate text-[13px] font-semibold">{{ $user->name }}</span>
                <span class="block truncate text-[12px] text-ink-muted">{{ $user->email }}</span>
            </span>
            <x-bx-expand-vertical class="size-4 shrink-0 text-ink-faint group-data-[sidebar=collapsed]/shell:hidden" aria-hidden="true" />
        @endif
        <span class="sr-only">Benutzermenü</span>
    </button>

    <div
        x-show="open"
        x-cloak
        x-on:click.outside="open = false"
        x-transition:enter="ease-out duration-150"
        x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100"
        x-transition:leave="ease-in duration-100"
        x-transition:leave-end="opacity-0 scale-95"
        @class([
            'absolute z-40 w-72 rounded-xl bg-surface p-1.5 shadow-pop',
            'bottom-full left-0 mb-2 origin-bottom-left' => $sidebar,
            'right-0 mt-2 origin-top-right' => ! $sidebar,
        ])
    >
        <div class="px-3 pt-2 pb-3">
            <p class="truncate text-sm font-semibold">{{ $user->name }}</p>
            <p class="truncate text-[13px] text-ink-muted">{{ $user->email }}</p>
        </div>

        <div class="px-2 pb-2">
            <p class="mb-1.5 px-1 text-[12px] font-medium text-ink-muted">Darstellung</p>
            <x-theme-switcher />
        </div>

        <div class="my-1 border-t border-separator"></div>

        <a href="{{ route('intern.settings.general') }}" wire:navigate class="flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm hover:bg-surface-muted">
            <x-bx-cog class="size-4 text-ink-muted" aria-hidden="true" />
            Einstellungen
        </a>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="flex w-full items-center gap-2.5 rounded-lg px-3 py-2 text-left text-sm hover:bg-surface-muted">
                <x-bx-log-out class="size-4 text-ink-muted" aria-hidden="true" />
                Abmelden
            </button>
        </form>
    </div>
</div>
