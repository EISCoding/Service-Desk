@php
    $user = auth()->user();
    $initials = collect(explode(' ', $user->name))->filter()->map(fn ($part) => mb_substr($part, 0, 1))->take(2)->implode('');
@endphp

<div x-data="{ open: false }" x-on:keydown.escape.window="open = false" class="relative">
    <button
        type="button"
        x-on:click="open = ! open"
        x-bind:aria-expanded="open"
        aria-haspopup="true"
        class="flex items-center gap-2 rounded-full p-0.5 pr-2 hover:bg-surface-muted focus-visible:outline-2 focus-visible:outline-accent"
    >
        <span class="grid size-8 place-items-center rounded-full bg-gradient-to-b from-zinc-400 to-zinc-500 text-[12px] font-semibold text-white dark:from-zinc-500 dark:to-zinc-600" aria-hidden="true">{{ mb_strtoupper($initials) }}</span>
        <span class="hidden text-[13px] font-medium sm:block">{{ $user->name }}</span>
        <x-heroicon-m-chevron-down class="hidden size-4 text-ink-faint sm:block" aria-hidden="true" />
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
        class="absolute right-0 z-40 mt-2 w-72 origin-top-right rounded-2xl bg-surface/95 p-1.5 shadow-xl ring-1 ring-separator backdrop-blur-xl"
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

        <a href="{{ route('intern.settings.security') }}" wire:navigate class="flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm hover:bg-surface-muted">
            <x-heroicon-o-shield-check class="size-4 text-ink-muted" aria-hidden="true" />
            Sicherheit
        </a>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="flex w-full items-center gap-2.5 rounded-lg px-3 py-2 text-left text-sm hover:bg-surface-muted">
                <x-heroicon-o-arrow-right-start-on-rectangle class="size-4 text-ink-muted" aria-hidden="true" />
                Abmelden
            </button>
        </form>
    </div>
</div>
