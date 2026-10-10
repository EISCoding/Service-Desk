<div class="px-4 py-4 sm:px-6 lg:px-8 lg:py-6">
    <a href="{{ route('intern.tickets.index') }}" wire:navigate class="mb-3 inline-flex items-center gap-1 text-[13px] font-medium text-ink-muted hover:text-ink lg:hidden">
        <x-heroicon-m-chevron-left class="size-4" aria-hidden="true" /> Tickets
    </a>

    <header>
        <p class="font-mono text-[13px] text-ink-muted">{{ $ticket->number }}</p>
        <h2 class="mt-0.5 text-[22px] leading-tight font-semibold tracking-tight">{{ $ticket->subject }}</h2>
        <div class="mt-2 flex flex-wrap gap-1.5">
            <x-ui.badge :color="$ticket->status->color()" dot :title="$ticket->status->description()">{{ $ticket->status->label() }}</x-ui.badge>
            <x-ui.badge :color="$ticket->priority->color()">{{ $ticket->priority->label() }}</x-ui.badge>
        </div>
    </header>
</div>
