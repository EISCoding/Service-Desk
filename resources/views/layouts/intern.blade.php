{{--
    Staff area layout.
    Desktop: blurred sidebar on the left, topbar with search and user menu.
    Mobile: compact topbar and a tab bar at the bottom.
--}}
@props([
    'title' => null,
    'flush' => false, // no padding, full height (split views)
])

@php
    $user = auth()->user();
    $mainNav = \App\Support\Navigation::main($user);
    $adminNav = \App\Support\Navigation::admin($user);
@endphp

<x-layouts::base :title="$title">
    <div x-data class="min-h-full">
        {{-- Sidebar (desktop) --}}
        <aside class="fixed inset-y-0 left-0 z-30 hidden w-60 flex-col border-r border-separator bg-surface/70 backdrop-blur-xl backdrop-saturate-150 lg:flex">
            <a href="{{ route('intern.dashboard') }}" wire:navigate class="flex h-14 items-center gap-2.5 px-5">
                <x-brand-mark size="sm" />
                <span class="text-[15px] font-semibold tracking-tight">{{ config('app.name') }}</span>
            </a>

            <nav class="flex-1 space-y-6 overflow-y-auto px-3 py-4" aria-label="Hauptnavigation">
                <ul class="space-y-0.5">
                    @foreach ($mainNav as $item)
                        @php($active = request()->routeIs($item['active']))
                        <li>
                            <a
                                href="{{ route($item['route']) }}"
                                wire:navigate
                                @if ($active) aria-current="page" @endif
                                @class([
                                    'flex items-center gap-3 rounded-lg px-3 py-1.5 text-sm transition-colors',
                                    'bg-ink/[0.07] font-medium text-ink' => $active,
                                    'text-ink-muted hover:bg-ink/[0.04] hover:text-ink' => ! $active,
                                ])
                            >
                                <x-dynamic-component :component="'heroicon-o-'.$item['icon']" @class(['size-5', 'text-accent' => $active]) aria-hidden="true" />
                                {{ $item['label'] }}
                            </a>
                        </li>
                    @endforeach
                </ul>

                @if ($adminNav)
                    <div>
                        <p class="px-3 pb-1.5 text-[11px] font-semibold tracking-wide text-ink-faint uppercase">Verwaltung</p>
                        <ul class="space-y-0.5">
                            @foreach ($adminNav as $item)
                                @php($active = request()->routeIs($item['active']))
                                <li>
                                    <a
                                        href="{{ route($item['route']) }}"
                                        wire:navigate
                                        @if ($active) aria-current="page" @endif
                                        @class([
                                            'flex items-center gap-3 rounded-lg px-3 py-1.5 text-sm transition-colors',
                                            'bg-ink/[0.07] font-medium text-ink' => $active,
                                            'text-ink-muted hover:bg-ink/[0.04] hover:text-ink' => ! $active,
                                        ])
                                    >
                                        <x-dynamic-component :component="'heroicon-o-'.$item['icon']" @class(['size-5', 'text-accent' => $active]) aria-hidden="true" />
                                        {{ $item['label'] }}
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </nav>
        </aside>

        <div class="lg:pl-60">
            {{-- Topbar --}}
            <header class="sticky top-0 z-20 flex h-14 items-center gap-3 border-b border-separator bg-canvas/80 px-4 backdrop-blur-xl backdrop-saturate-150 sm:px-6">
                <a href="{{ route('intern.dashboard') }}" wire:navigate class="shrink-0 lg:hidden">
                    <x-brand-mark />
                    <span class="sr-only">{{ config('app.name') }}</span>
                </a>

                <livewire:intern.global-search />

                <div class="ml-auto flex items-center gap-1">
                    <livewire:intern.notification-bell />
                    <x-user-menu />
                </div>
            </header>

            <main @class(['pb-24 lg:pb-0' => $flush, 'px-4 pt-6 pb-28 sm:px-6 lg:px-8 lg:pb-10' => ! $flush])>
                {{ $slot }}
            </main>
        </div>

        {{-- Tab bar (mobile) --}}
        <nav class="fixed inset-x-0 bottom-0 z-30 border-t border-separator bg-surface/80 pb-[env(safe-area-inset-bottom)] backdrop-blur-xl backdrop-saturate-150 lg:hidden" aria-label="Hauptnavigation">
            <ul class="mx-auto flex max-w-lg">
                @foreach ($mainNav as $item)
                    @php($active = request()->routeIs($item['active']))
                    <li class="flex-1">
                        <a
                            href="{{ route($item['route']) }}"
                            wire:navigate
                            @if ($active) aria-current="page" @endif
                            @class([
                                'flex flex-col items-center gap-0.5 pt-2 pb-1.5 text-[10px] font-medium',
                                'text-accent' => $active,
                                'text-ink-faint hover:text-ink' => ! $active,
                            ])
                        >
                            <x-dynamic-component :component="'heroicon-'.($active ? 's' : 'o').'-'.$item['icon']" class="size-6" aria-hidden="true" />
                            {{ $item['label'] }}
                        </a>
                    </li>
                @endforeach
            </ul>
        </nav>
    </div>
</x-layouts::base>
