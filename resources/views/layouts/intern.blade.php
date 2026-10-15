{{--
    Staff area layout (design "E · Service Desk").
    Desktop: sidebar that collapses to icons (remembered per user, shortcut [),
    ticket views with live counts, top bar with search and "Neues Ticket".
    Mobile: compact top bar and a tab bar at the bottom.
--}}
@props([
    'title' => null,
    'flush' => false, // no padding, full height (split views)
])

@php
    $user = auth()->user();
    $mainNav = \App\Support\Navigation::main($user);
    $adminNav = \App\Support\Navigation::admin($user);
    // With the admin areas listed in the sidebar, the overview entry is not needed there.
    $sidebarNav = $adminNav ? array_values(array_filter($mainNav, fn ($item) => $item['route'] !== 'intern.admin.index')) : $mainNav;
    $collapsed = (bool) $user->sidebar_collapsed;
    $canCreate = $user->can('create', \App\Models\Ticket::class);
@endphp

<x-layouts::base :title="$title">
    <div
        x-data="shell(@js($collapsed), @js(route('intern.sidebar.update')))"
        data-sidebar="{{ $collapsed ? 'collapsed' : 'expanded' }}"
        x-bind:data-sidebar="collapsed ? 'collapsed' : 'expanded'"
        x-on:toggle-sidebar.window="toggle()"
        class="group/shell min-h-full"
    >
        {{-- Sidebar (desktop) --}}
        <aside
            class="fixed inset-y-0 left-0 z-30 hidden w-60 flex-col border-r border-separator bg-sidebar transition-[width] duration-200 ease-out lg:flex group-data-[sidebar=collapsed]/shell:w-16"
            x-on:mouseover="showTip($event)"
            x-on:mouseleave="hideTip()"
            x-on:focusin="showTip($event)"
            x-on:focusout="hideTip()"
        >
            <a href="{{ route('intern.dashboard') }}" wire:navigate data-tip="{{ config('app.name') }}"
                class="mx-3 mt-3 mb-2 flex h-10 shrink-0 items-center gap-2.5 rounded-lg px-1 group-data-[sidebar=collapsed]/shell:mx-auto group-data-[sidebar=collapsed]/shell:justify-center">
                <x-brand-mark size="sm" />
                <span class="truncate text-[15px] font-semibold tracking-tight group-data-[sidebar=collapsed]/shell:sr-only">{{ config('app.name') }}</span>
            </a>

            <nav class="flex-1 overflow-x-hidden overflow-y-auto px-3 pb-3 group-data-[sidebar=collapsed]/shell:px-2" aria-label="Hauptnavigation">
                <ul class="space-y-px">
                    @foreach ($sidebarNav as $item)
                        <x-sidebar.item :href="route($item['route'])" :icon="$item['icon']" :label="$item['label']" :active="request()->routeIs($item['active'])" />
                    @endforeach
                </ul>

                <div class="group-data-[sidebar=collapsed]/shell:hidden">
                    <livewire:intern.sidebar-views />
                </div>

                @if ($adminNav)
                    <p class="px-2.5 pt-5 pb-1 text-[11px] font-semibold tracking-wide text-ink-faint uppercase group-data-[sidebar=collapsed]/shell:hidden">Verwaltung</p>
                    <div class="mx-2 my-3 hidden border-t border-separator group-data-[sidebar=collapsed]/shell:block" aria-hidden="true"></div>
                    <ul class="space-y-px" aria-label="Verwaltung">
                        @foreach ($adminNav as $item)
                            <x-sidebar.item :href="route($item['route'])" :icon="$item['icon']" :label="$item['label']" :active="request()->routeIs($item['active'])" />
                        @endforeach
                    </ul>
                @endif
            </nav>

            <div class="shrink-0 space-y-1 border-t border-separator p-3 group-data-[sidebar=collapsed]/shell:px-2">
                <button type="button" x-on:click="toggle()" data-tip="Seitenleiste ausklappen ( [ )" x-bind:aria-expanded="collapsed ? 'false' : 'true'"
                    class="flex h-9 w-full items-center gap-2.5 rounded-lg px-2.5 text-sm text-ink-muted transition-colors hover:bg-ink/[0.04] hover:text-ink group-data-[sidebar=collapsed]/shell:justify-center group-data-[sidebar=collapsed]/shell:px-0">
                    <x-bx-sidebar class="size-5 shrink-0" aria-hidden="true" />
                    <span class="min-w-0 flex-1 truncate text-left group-data-[sidebar=collapsed]/shell:sr-only">
                        <span class="group-data-[sidebar=collapsed]/shell:hidden">Einklappen<span class="sr-only"> (Seitenleiste)</span></span>
                        <span class="hidden group-data-[sidebar=collapsed]/shell:inline">Seitenleiste ausklappen</span>
                    </span>
                    <kbd class="rounded border border-separator px-1.5 font-sans text-[11px] text-ink-faint group-data-[sidebar=collapsed]/shell:hidden">[</kbd>
                </button>
                <x-user-menu placement="sidebar" />
            </div>

            {{-- One tooltip for all entries while collapsed --}}
            <div x-show="tip.show" x-cloak role="tooltip" x-text="tip.text" x-bind:style="`top: ${tip.top}px; left: ${tip.left}px`"
                class="pointer-events-none fixed z-50 -translate-y-1/2 rounded-md bg-ink px-2 py-1 text-[12px] font-medium whitespace-nowrap text-canvas shadow-pop"></div>
        </aside>

        <div class="transition-[padding] duration-200 ease-out lg:pl-60 lg:group-data-[sidebar=collapsed]/shell:pl-16">
            {{-- Top bar --}}
            <header class="sticky top-0 z-20 flex h-14 items-center gap-2 border-b border-separator bg-surface/85 px-4 backdrop-blur-xl backdrop-saturate-150 sm:px-6">
                <a href="{{ route('intern.dashboard') }}" wire:navigate class="mr-1 shrink-0 lg:hidden">
                    <x-brand-mark />
                    <span class="sr-only">{{ config('app.name') }}</span>
                </a>

                <livewire:intern.global-search />

                <div class="ml-auto flex items-center gap-1.5">
                    <livewire:intern.notification-bell />
                    @if ($canCreate)
                        <x-ui.button icon="plus" x-on:click="$dispatch('create-ticket')" class="max-sm:size-9 max-sm:px-0" aria-label="Neues Ticket">
                            <span class="max-sm:sr-only">Neues Ticket</span>
                        </x-ui.button>
                    @endif
                    <x-user-menu class="lg:hidden" />
                </div>
            </header>

            <main @class(['pb-24 lg:pb-0' => $flush, 'px-4 pt-6 pb-28 sm:px-6 lg:px-8 lg:pb-10' => ! $flush])>
                {{ $slot }}
            </main>
        </div>

        @if ($canCreate)
            <livewire:intern.tickets.create-ticket />
        @endif

        {{-- Tab bar (mobile) --}}
        <nav class="fixed inset-x-0 bottom-0 z-30 border-t border-separator bg-surface/90 pb-[env(safe-area-inset-bottom)] backdrop-blur-xl backdrop-saturate-150 lg:hidden" aria-label="Hauptnavigation">
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
                            <x-dynamic-component :component="\App\Support\Icon::component($item['icon'], solid: $active)" class="size-6" aria-hidden="true" />
                            {{ $item['label'] }}
                        </a>
                    </li>
                @endforeach
            </ul>
        </nav>
    </div>
</x-layouts::base>
