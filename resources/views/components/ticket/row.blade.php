{{-- Compact ticket row for overviews; $meta replaces the activity time on the right of the second line. --}}
@props(['ticket', 'unread' => false, 'meta' => null])

<li>
    <a href="{{ route('intern.tickets.show', $ticket) }}" wire:navigate class="flex items-center gap-3 px-5 py-3 transition-colors hover:bg-ink/[0.03] focus-visible:-outline-offset-2">
        <div class="min-w-0 flex-1">
            <p class="flex items-center gap-1.5 text-sm">
                @if ($unread)
                    <span class="size-2 shrink-0 rounded-full bg-accent" aria-hidden="true"></span>
                    <span class="sr-only">Neue Kundenantwort:</span>
                @endif
                <span @class(['truncate', 'font-semibold' => $unread, 'font-medium' => ! $unread])>{{ $ticket->subject }}</span>
            </p>
            <p class="mt-0.5 truncate text-[12px] text-ink-muted">
                <span class="tabular-nums">{{ $ticket->reference() }}</span>
                · {{ $ticket->customer?->company ?? 'Ohne Kunde' }}
                · {{ $meta ?? $ticket->last_activity_at?->diffForHumans(short: true) }}
            </p>
        </div>
        @if ($ticket->priority->isHighlighted())
            <x-ui.badge :color="$ticket->priority->color()" class="shrink-0 max-sm:hidden">{{ $ticket->priority->label() }}</x-ui.badge>
        @endif
        <x-ui.badge :color="$ticket->status->color()" dot class="shrink-0">{{ $ticket->status->label() }}</x-ui.badge>
    </a>
</li>
