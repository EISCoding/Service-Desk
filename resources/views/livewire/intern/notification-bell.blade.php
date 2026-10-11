<div wire:poll.60s.visible class="relative" x-data="{ open: false }" x-on:keydown.escape="open = false">
    <button type="button" x-on:click="open = ! open" x-bind:aria-expanded="open" class="relative grid size-9 place-items-center rounded-full text-ink-muted hover:bg-ink/[0.05] hover:text-ink">
        <x-heroicon-o-bell class="size-5" aria-hidden="true" />
        <span class="sr-only">Benachrichtigungen{{ $this->unreadCount ? " ({$this->unreadCount} ungelesen)" : '' }}</span>
        @if ($this->unreadCount)
            <span class="absolute top-1 right-1 grid min-w-4 place-items-center rounded-full bg-red-500 px-1 text-[10px] leading-4 font-semibold text-white" aria-hidden="true">{{ $this->unreadCount > 9 ? '9+' : $this->unreadCount }}</span>
        @endif
    </button>

    <div x-show="open" x-cloak x-on:click.outside="open = false" x-transition.opacity class="absolute right-0 z-30 mt-1 w-[min(22rem,calc(100vw-2rem))] rounded-xl bg-surface shadow-lg ring-1 ring-separator">
        <div class="flex items-center justify-between border-b border-separator px-4 py-2.5">
            <p class="text-sm font-semibold">Benachrichtigungen</p>
            @if ($this->unreadCount)
                <button type="button" wire:click="markAllRead" class="text-[12px] font-medium text-accent hover:underline">Alle gelesen</button>
            @endif
        </div>
        <ul class="max-h-96 divide-y divide-separator overflow-y-auto">
            @forelse ($this->latest as $notification)
                <li wire:key="notification-{{ $notification->id }}">
                    <button type="button" wire:click="open('{{ $notification->id }}')" x-on:click="open = false" class="flex w-full gap-3 px-4 py-3 text-left hover:bg-surface-muted">
                        <span @class(['mt-1.5 size-2 shrink-0 rounded-full', 'bg-accent' => ! $notification->read_at, 'bg-transparent' => $notification->read_at]) aria-hidden="true"></span>
                        <span class="min-w-0">
                            <span class="block text-sm font-medium">{{ $notification->data['title'] ?? '' }}</span>
                            <span class="block truncate text-[13px] text-ink-muted"><span class="font-mono">{{ $notification->data['reference'] ?? '' }}</span> {{ $notification->data['subject'] ?? '' }}</span>
                            @if (! empty($notification->data['text']))
                                <span class="mt-0.5 line-clamp-2 block text-[12px] text-ink-muted">{{ $notification->data['text'] }}</span>
                            @endif
                            <span class="mt-0.5 block text-[11px] text-ink-faint">{{ $notification->created_at->diffForHumans() }}</span>
                        </span>
                    </button>
                </li>
            @empty
                <li class="px-4 py-8 text-center text-sm text-ink-muted">Keine Benachrichtigungen.</li>
            @endforelse
        </ul>
        <div class="border-t border-separator px-4 py-2 text-right">
            <a href="{{ route('intern.settings.notifications') }}" wire:navigate class="text-[12px] font-medium text-ink-muted hover:text-ink">Einstellungen</a>
        </div>
    </div>
</div>
