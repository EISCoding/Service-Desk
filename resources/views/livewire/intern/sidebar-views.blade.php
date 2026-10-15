{{-- Ticket views with live counts; hidden while the sidebar is collapsed to icons. --}}
<div wire:poll.30s.visible="checkForChanges">
    @if ($canView)
        <p class="px-2.5 pt-5 pb-1 text-[11px] font-semibold tracking-wide text-ink-faint uppercase">Ansichten</p>
        <ul class="space-y-px">
            @foreach (\App\Livewire\Intern\SidebarViews::VIEWS as $key => $label)
                @php($count = $this->counts[$key])
                <li>
                    <a
                        href="{{ route('intern.tickets.index', ['ansicht' => $key]) }}"
                        wire:navigate
                        @if ($current === $key) aria-current="page" @endif
                        @class([
                            'flex h-8 items-center gap-2 rounded-lg px-2.5 text-[13px] transition-colors',
                            'bg-surface font-semibold text-ink shadow-card' => $current === $key,
                            'text-ink-muted hover:bg-ink/[0.04] hover:text-ink' => $current !== $key,
                        ])
                    >
                        <span class="min-w-0 flex-1 truncate">{{ $label }}</span>
                        <span @class([
                            'text-[12px] font-semibold tabular-nums',
                            'text-accent' => $count > 0 && in_array($key, ['unread', 'due'], true),
                            'text-ink-faint' => ! ($count > 0 && in_array($key, ['unread', 'due'], true)),
                        ])>{{ $count }}<span class="sr-only"> Tickets</span></span>
                    </a>
                </li>
            @endforeach
        </ul>
    @endif
</div>
