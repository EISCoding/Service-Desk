@php($contact = $this->contact)

<div>
    <a href="{{ route('portal.home') }}" wire:navigate class="mb-3 inline-flex items-center gap-1 text-[13px] font-medium text-ink-muted hover:text-ink">
        <x-heroicon-m-chevron-left class="size-4" aria-hidden="true" /> Alle Tickets
    </a>

    <header class="mb-6">
        <p class="font-mono text-[13px] text-ink-muted">{{ $ticket->reference() }}</p>
        <h1 class="mt-0.5 text-[22px] leading-tight font-semibold tracking-tight [overflow-wrap:anywhere]">{{ $ticket->subject }}</h1>
        <div class="mt-2 flex flex-wrap items-center gap-1.5">
            <x-ui.badge :color="$ticket->status->portalColor()" dot>{{ $ticket->status->portalLabel() }}</x-ui.badge>
        </div>
    </header>

    <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_260px]">
        <aside class="lg:order-last" aria-label="Ticketdaten">
            <x-ui.card :padding="false" class="lg:sticky lg:top-20">
                <dl class="grid grid-cols-2 gap-x-4 gap-y-3 p-4 text-[13px] lg:grid-cols-1">
                    <div><dt class="text-ink-muted">Priorität</dt><dd class="mt-0.5 font-medium">{{ $ticket->priority->label() }}</dd></div>
                    <div><dt class="text-ink-muted">Erstellt</dt><dd class="mt-0.5 font-medium">{{ $ticket->created_at->isoFormat('D. MMM YYYY, HH:mm') }}</dd></div>
                    @if ($contact->is_primary && $ticket->contact_id !== $contact->id)
                        <div><dt class="text-ink-muted">Ansprechpartner</dt><dd class="mt-0.5 font-medium">{{ $ticket->contact?->fullName() ?? '–' }}</dd></div>
                    @endif
                    @if ($ticket->closed_at)
                        <div><dt class="text-ink-muted">Geschlossen</dt><dd class="mt-0.5 font-medium">{{ $ticket->closed_at->isoFormat('D. MMM YYYY, HH:mm') }}</dd></div>
                    @endif
                </dl>
            </x-ui.card>
        </aside>

        <div class="min-w-0 space-y-4">
            @foreach ($this->messages as $message)
                @php($fromCustomer = $message->author instanceof \App\Models\Contact || ($message->type === \App\Enums\MessageType::MailIn && ! $message->author))
                <article wire:key="message-{{ $message->id }}" @class([
                    'rounded-card p-4 ring-1 sm:p-5',
                    'bg-accent/[0.04] ring-accent/15' => $fromCustomer,
                    'bg-surface ring-separator/80' => ! $fromCustomer,
                ])>
                    <header class="mb-3 flex items-start gap-3">
                        <x-ui.avatar :name="$message->authorName()" />
                        <div class="min-w-0 flex-1">
                            <p class="text-sm">
                                <span class="font-medium">{{ $message->authorName() }}</span>
                                @if ($message->author?->is($contact))
                                    <span class="text-[12px] text-ink-muted">· Sie</span>
                                @elseif (! $fromCustomer)
                                    <span class="text-[12px] text-ink-muted">· {{ config('app.name') }}</span>
                                @endif
                            </p>
                            <p class="text-[12px] text-ink-muted">
                                <time datetime="{{ $message->created_at->toIso8601String() }}">{{ $message->created_at->isoFormat('D. MMM YYYY, HH:mm') }}</time>
                            </p>
                        </div>
                        @if ($message->type !== \App\Enums\MessageType::Public)
                            <x-ui.badge><x-heroicon-m-envelope class="size-3" aria-hidden="true" /> E-Mail</x-ui.badge>
                        @endif
                    </header>

                    @php([$mainText, $quotedText] = $message->displayParts(portal: true))
                    <div class="message-body">{!! $mainText !!}</div>
                    @if ($quotedText)
                        <details class="group mt-3">
                            <summary class="inline-flex cursor-pointer list-none items-center gap-1 rounded-full bg-ink/[0.05] px-2.5 py-0.5 text-[12px] font-medium text-ink-muted hover:text-ink">
                                <x-heroicon-m-ellipsis-horizontal class="size-4" aria-hidden="true" />
                                <span class="group-open:hidden">Zitierten Verlauf anzeigen</span>
                                <span class="hidden group-open:inline">Zitierten Verlauf ausblenden</span>
                            </summary>
                            <div class="message-body mt-3 border-l-2 border-separator pl-3 text-ink-muted">{!! $quotedText !!}</div>
                        </details>
                    @endif

                    @if ($message->listedAttachments()->isNotEmpty())
                        <ul class="mt-4 flex flex-wrap gap-2" aria-label="Anhänge">
                            @foreach ($message->listedAttachments() as $attachment)
                                <li>
                                    <a href="{{ $attachment->url(inline: $attachment->canBeShownInline(), portal: true) }}" target="_blank" class="flex max-w-60 items-center gap-2 rounded-xl bg-surface-muted px-3 py-2 text-[13px] ring-1 ring-separator hover:ring-accent">
                                        @if ($attachment->isImage())
                                            <img src="{{ $attachment->url(inline: true, portal: true) }}" alt="" class="size-8 shrink-0 rounded-md object-cover" loading="lazy">
                                        @else
                                            <x-heroicon-o-paper-clip class="size-4 shrink-0 text-ink-muted" aria-hidden="true" />
                                        @endif
                                        <span class="min-w-0">
                                            <span class="block truncate font-medium">{{ $attachment->original_name }}</span>
                                            <span class="text-[11px] text-ink-muted">{{ $attachment->humanSize() }}</span>
                                        </span>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </article>
            @endforeach
        </div>
    </div>
</div>
