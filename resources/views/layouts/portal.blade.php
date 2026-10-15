{{--
    Customer portal layout (guard "contact").
    Desktop: topbar with the navigation, mobile: tab bar at the bottom.
--}}
@props([
    'title' => null,
])

@php
    $contact = auth('contact')->user();
    $nav = array_filter([
        ['route' => 'portal.home', 'active' => 'portal.home|portal.tickets.show', 'label' => 'Tickets', 'icon' => 'collection'],
        ['route' => 'portal.tickets.create', 'active' => 'portal.tickets.create', 'label' => 'Neues Ticket', 'icon' => 'plus-circle'],
        ['route' => 'portal.profile', 'active' => 'portal.profile', 'label' => 'Profil', 'icon' => 'user-circle'],
    ], fn (array $item) => Route::has($item['route']));
@endphp

<x-layouts::base :title="$title">
    <div x-data class="min-h-full">
        <header class="sticky top-0 z-20 border-b border-hairline bg-canvas/75 backdrop-blur-2xl backdrop-saturate-150">
            <div class="mx-auto flex h-14 max-w-5xl items-center gap-6 px-4 sm:px-6">
                <a href="{{ route('portal.home') }}" wire:navigate class="flex items-center gap-2.5">
                    <x-brand-mark size="sm" />
                    <span class="text-[15px] font-semibold tracking-tight">{{ config('app.name') }}</span>
                    <span class="hidden text-[13px] text-ink-muted sm:inline">Kundenportal</span>
                </a>

                <nav class="hidden md:block" aria-label="Hauptnavigation">
                    <ul class="flex items-center gap-1">
                        @foreach ($nav as $item)
                            @php($active = request()->routeIs(...explode('|', $item['active'])))
                            <li>
                                <a href="{{ route($item['route']) }}" wire:navigate @if ($active) aria-current="page" @endif
                                    @class([
                                        'rounded-full px-3.5 py-1.5 text-sm transition-colors',
                                        'bg-ink/[0.07] font-semibold text-ink' => $active,
                                        'text-ink-muted hover:bg-ink/[0.04] hover:text-ink' => ! $active,
                                    ])>{{ $item['label'] }}</a>
                            </li>
                        @endforeach
                    </ul>
                </nav>

                <div class="relative ml-auto" x-data="{ open: false }" x-on:keydown.escape="open = false">
                    <button type="button" x-on:click="open = ! open" x-bind:aria-expanded="open" class="flex items-center gap-2 rounded-full py-1 pr-2 pl-1 text-sm hover:bg-ink/[0.05]">
                        <x-ui.avatar :name="$contact->fullName()" size="sm" />
                        <span class="hidden max-w-40 truncate font-medium sm:block">{{ $contact->fullName() }}</span>
                        <x-bx-chevron-down class="size-4 text-ink-faint" aria-hidden="true" />
                    </button>
                    <div x-show="open" x-cloak x-on:click.outside="open = false" x-transition.opacity class="absolute right-0 z-30 mt-1 w-64 rounded-xl bg-surface p-1 shadow-pop">
                        <div class="px-3 py-2">
                            <p class="truncate text-sm font-medium">{{ $contact->fullName() }}</p>
                            @unless ($contact->loadMissing('customer')->customer->isPrivate())
                                <p class="truncate text-[12px] text-ink-muted">{{ $contact->customer->company }}</p>
                            @endunless
                        </div>
                        <div class="px-3 pt-1 pb-2">
                            <p class="mb-1.5 text-[12px] font-medium text-ink-muted">Darstellung</p>
                            <x-theme-switcher :url="route('portal.theme.update')" />
                        </div>
                        <div class="my-1 border-t border-separator"></div>
                        @if (Route::has('portal.profile'))
                            <a href="{{ route('portal.profile') }}" wire:navigate class="flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm hover:bg-surface-muted">
                                <x-bx-user-circle class="size-4 text-ink-muted" aria-hidden="true" />
                                Profil
                            </a>
                        @endif
                        <form method="POST" action="{{ route('portal.logout') }}">
                            @csrf
                            <button type="submit" class="flex w-full items-center gap-2.5 rounded-lg px-3 py-2 text-left text-sm hover:bg-surface-muted">
                                <x-bx-log-out class="size-4 text-ink-muted" aria-hidden="true" />
                                Abmelden
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </header>

        <main class="mx-auto max-w-5xl px-4 pt-6 pb-28 sm:px-6 md:pb-12">
            {{ $slot }}
        </main>

        {{-- Tab bar (mobile) --}}
        <nav class="fixed inset-x-0 bottom-0 z-30 border-t border-hairline bg-surface/90 pb-[env(safe-area-inset-bottom)] backdrop-blur-2xl backdrop-saturate-150 md:hidden" aria-label="Hauptnavigation">
            <ul class="mx-auto flex max-w-lg">
                @foreach ($nav as $item)
                    @php($active = request()->routeIs(...explode('|', $item['active'])))
                    <li class="flex-1">
                        <a href="{{ route($item['route']) }}" wire:navigate @if ($active) aria-current="page" @endif
                            @class([
                                'flex flex-col items-center gap-0.5 pt-2 pb-1.5 text-[10px] font-medium',
                                'text-accent' => $active,
                                'text-ink-faint hover:text-ink' => ! $active,
                            ])>
                            <x-dynamic-component :component="\App\Support\Icon::component($item['icon'], solid: $active)" class="size-6" aria-hidden="true" />
                            {{ $item['label'] }}
                        </a>
                    </li>
                @endforeach
            </ul>
        </nav>
    </div>
</x-layouts::base>
