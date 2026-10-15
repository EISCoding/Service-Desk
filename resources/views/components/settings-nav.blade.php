{{-- Sub-navigation of the personal settings (/intern/einstellungen/...) --}}
@php
    $items = [
        ['route' => 'intern.settings.general', 'label' => 'Allgemein'],
        ['route' => 'intern.settings.notifications', 'label' => 'Benachrichtigungen'],
        ['route' => 'intern.settings.signature', 'label' => 'Signatur'],
        ['route' => 'intern.settings.security', 'label' => 'Sicherheit'],
    ];
@endphp

<div class="mb-6">
    <x-ui.page-header title="Einstellungen" class="mb-4!" />
    <nav aria-label="Einstellungen" class="-mx-4 overflow-x-auto overflow-y-hidden px-4 sm:mx-0 sm:px-0">
        <ul class="inline-flex gap-0.5 rounded-[10px] bg-ink/[0.05] p-0.5">
            @foreach ($items as $item)
                @php($active = request()->routeIs($item['route']))
                <li>
                    <a href="{{ route($item['route']) }}" wire:navigate @if ($active) aria-current="page" @endif
                        @class(['block rounded-lg px-3 py-1 text-[13px] font-medium whitespace-nowrap', 'bg-surface shadow-xs' => $active, 'text-ink-muted hover:text-ink' => ! $active])>{{ $item['label'] }}</a>
                </li>
            @endforeach
        </ul>
    </nav>
</div>
