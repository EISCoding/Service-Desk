@php($items = \App\Support\Navigation::admin(auth()->user()))

<x-layouts::intern title="Admin">
    <x-ui.page-header title="Admin" description="Benutzer, Gruppen und Systemeinstellungen verwalten." />

    @if ($items)
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($items as $item)
                <a href="{{ route($item['route']) }}" wire:navigate class="group rounded-card focus-visible:outline-2 focus-visible:outline-accent">
                    <x-ui.card class="h-full transition-shadow group-hover:shadow-md">
                        <div class="flex items-center gap-3">
                            <span class="grid size-10 place-items-center rounded-xl bg-accent/10 text-accent">
                                <x-dynamic-component :component="'heroicon-o-'.$item['icon']" class="size-5" aria-hidden="true" />
                            </span>
                            <span class="text-[15px] font-semibold">{{ $item['label'] }}</span>
                            <x-heroicon-m-chevron-right class="ml-auto size-5 text-ink-faint" aria-hidden="true" />
                        </div>
                    </x-ui.card>
                </a>
            @endforeach
        </div>
    @else
        <x-ui.card :padding="false">
            <x-ui.empty-state icon="cog-6-tooth" title="Noch keine Verwaltungsbereiche" description="Benutzer- und Gruppenverwaltung folgen in Kürze." />
        </x-ui.card>
    @endif
</x-layouts::intern>
