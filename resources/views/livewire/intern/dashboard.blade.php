@php($counts = $this->counts)

<div class="mx-auto max-w-6xl">
    <header class="mb-6">
        <p class="text-[13px] font-medium text-ink-muted">{{ now()->isoFormat('dddd, D. MMMM') }}</p>
        <h1 class="mt-0.5 text-[28px] leading-tight font-bold tracking-tight">{{ $this->greeting() }}</h1>
    </header>

    {{-- Key figures, each leads to the matching ticket list --}}
    <ul class="grid grid-cols-2 gap-3 lg:grid-cols-4" aria-label="Kennzahlen">
        @foreach ([
            ['mine', 'Meine offenen', 'inbox', 'bg-accent-fill'],
            ['unread', 'Neue Kundenantworten', 'chat-bubble-left-ellipsis', 'bg-indigo-500'],
            ['due', 'Wiedervorlagen fällig', 'bell-alert', 'bg-orange-500'],
            ['unassigned', 'Nicht zugeordnet', 'user-plus', 'bg-zinc-500'],
        ] as [$key, $label, $icon, $color])
            <li>
                <a href="{{ route('intern.tickets.index', ['ansicht' => $key]) }}" wire:navigate class="group flex h-full flex-col gap-3 rounded-card bg-surface p-4 shadow-card transition hover:-translate-y-px hover:shadow-pop">
                    <span class="flex items-center justify-between">
                        <span class="grid size-8 place-items-center rounded-[10px] text-white {{ $color }}">
                            <x-dynamic-component :component="'heroicon-s-'.$icon" class="size-[18px]" aria-hidden="true" />
                        </span>
                        <x-heroicon-m-chevron-right class="size-4 text-ink-faint transition group-hover:translate-x-0.5" aria-hidden="true" />
                    </span>
                    <span>
                        <span class="block text-[28px] leading-none font-bold tracking-tight tabular-nums">{{ $counts[$key] }}</span>
                        <span class="mt-1 block text-[13px] font-medium text-ink-muted">{{ $label }}</span>
                    </span>
                </a>
            </li>
        @endforeach
    </ul>

    <div class="mt-6 grid items-start gap-6 lg:grid-cols-2">
        <x-ui.card title="Neue Kundenantworten" :padding="false">
            @if ($this->unread->isEmpty())
                <p class="flex items-center gap-2 px-5 pt-2 pb-5 text-sm text-ink-muted"><x-heroicon-o-check-circle class="size-5 text-green-600 dark:text-green-400" aria-hidden="true" /> Alles gelesen.</p>
            @else
                <ul class="mt-2 divide-y divide-hairline border-t border-hairline">
                    @foreach ($this->unread as $ticket)
                        <x-ticket.row wire:key="unread-{{ $ticket->id }}" :ticket="$ticket" :unread="true" :meta="$ticket->customer_replied_at?->diffForHumans(short: true)" />
                    @endforeach
                </ul>
            @endif
        </x-ui.card>

        <x-ui.card title="Fällige Wiedervorlagen" :padding="false">
            @if ($this->due->isEmpty())
                <p class="flex items-center gap-2 px-5 pt-2 pb-5 text-sm text-ink-muted"><x-heroicon-o-check-circle class="size-5 text-green-600 dark:text-green-400" aria-hidden="true" /> Nichts fällig.</p>
            @else
                <ul class="mt-2 divide-y divide-hairline border-t border-hairline">
                    @foreach ($this->due as $ticket)
                        <x-ticket.row wire:key="due-{{ $ticket->id }}" :ticket="$ticket" :unread="$ticket->isUnreadFor(auth()->user())" :meta="'fällig seit '.$ticket->follow_up_at?->isoFormat('D. MMM')" />
                    @endforeach
                </ul>
            @endif
        </x-ui.card>

        <x-ui.card title="Meine offenen Tickets" :padding="false">
            <x-slot:actions>
                <a href="{{ route('intern.tickets.index', ['ansicht' => 'mine']) }}" wire:navigate class="text-[13px] font-medium text-accent hover:underline">Alle</a>
            </x-slot:actions>
            @if ($this->mine->isEmpty())
                <p class="px-5 pt-2 pb-5 text-sm text-ink-muted">Dir ist gerade kein offenes Ticket zugeordnet.</p>
            @else
                <ul class="mt-2 divide-y divide-hairline border-t border-hairline">
                    @foreach ($this->mine as $ticket)
                        <x-ticket.row wire:key="mine-{{ $ticket->id }}" :ticket="$ticket" :unread="$ticket->isUnreadFor(auth()->user())" />
                    @endforeach
                </ul>
            @endif
        </x-ui.card>

        @if ($this->appointments->isNotEmpty())
            <x-ui.card title="Deine nächsten Termine" :padding="false">
                <ul class="mt-2 divide-y divide-hairline border-t border-hairline">
                    @foreach ($this->appointments as $appointment)
                        <li wire:key="appointment-{{ $appointment->id }}">
                            <a href="{{ route('intern.tickets.show', $appointment->ticket_id) }}" wire:navigate class="flex items-center gap-3 px-5 py-3 transition-colors hover:bg-ink/[0.03] focus-visible:-outline-offset-2">
                                <span class="grid w-11 shrink-0 place-items-center rounded-[10px] bg-teal-500/10 py-1 text-teal-700 dark:text-teal-300">
                                    <span class="text-[10px] font-semibold uppercase">{{ $appointment->starts_at->isoFormat('MMM') }}</span>
                                    <span class="text-[17px] leading-tight font-bold tabular-nums">{{ $appointment->starts_at->format('j') }}</span>
                                </span>
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate text-sm font-medium">{{ $appointment->ticket?->subject }}</span>
                                    <span class="block truncate text-[12px] text-ink-muted">{{ $appointment->starts_at->isoFormat('dd') }}, {{ $appointment->starts_at->format('H:i') }}–{{ $appointment->ends_at->format('H:i') }} Uhr · {{ str($appointment->location)->before("\n") }}</span>
                                </span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </x-ui.card>
        @endif
    </div>
</div>
