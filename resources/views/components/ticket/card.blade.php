{{-- Ticket card on the board --}}
@props(['ticket'])

@php($unread = $ticket->isUnreadFor(auth()->user()))

<a href="{{ route('intern.tickets.show', $ticket) }}" wire:navigate draggable="false"
    class="block rounded-xl bg-surface p-3 shadow-card transition-shadow select-none hover:shadow-pop focus-visible:outline-2 focus-visible:outline-accent">
    <span class="flex items-center justify-between gap-2 text-[11px] text-ink-muted">
        <span class="tabular-nums">{{ $ticket->reference() }}</span>
        <time datetime="{{ $ticket->last_activity_at?->toIso8601String() }}">{{ $ticket->last_activity_at?->diffForHumans(short: true) }}</time>
    </span>
    <span class="mt-1 flex items-start gap-1.5">
        @if ($unread)
            <span class="mt-1.5 size-2 shrink-0 rounded-full bg-accent" aria-hidden="true"></span>
            <span class="sr-only">Neue Kundenantwort:</span>
        @endif
        <span @class(['line-clamp-2 text-[13px] leading-snug text-ink', 'font-semibold' => $unread, 'font-medium' => ! $unread])>{{ $ticket->subject }}</span>
    </span>
    <span class="mt-0.5 block truncate text-[12px] text-ink-muted">{{ $ticket->customer?->company ?? 'Ohne Kunde' }}</span>
    <span class="mt-2 flex flex-wrap items-center gap-1">
        @if ($ticket->priority->isHighlighted())
            <x-ui.badge :color="$ticket->priority->color()">{{ $ticket->priority->label() }}</x-ui.badge>
        @endif
        @if ($ticket->follow_up_due)
            <x-ui.badge color="orange"><x-bxs-bell-ring class="size-3" aria-hidden="true" /> Fällig</x-ui.badge>
        @elseif ($ticket->follow_up_at)
            <span class="text-[11px] text-ink-muted">Wiedervorlage {{ $ticket->follow_up_at->isoFormat('D. MMM') }}</span>
        @endif
        <span class="ml-auto">
            @if ($ticket->assignee)
                <x-ui.avatar :name="$ticket->assignee->name" size="sm" />
            @endif
        </span>
    </span>
</a>
