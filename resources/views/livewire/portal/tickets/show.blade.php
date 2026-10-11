@php($contact = $this->contact)

<div>
    <a href="{{ route('portal.home') }}" wire:navigate class="mb-3 inline-flex items-center gap-1 text-[13px] font-medium text-ink-muted hover:text-ink">
        <x-heroicon-m-chevron-left class="size-4" aria-hidden="true" /> Alle Tickets
    </a>

    <header class="mb-6">
        <p class="ref text-ink-muted">{{ $ticket->reference() }}</p>
        <h1 class="mt-0.5 text-[24px] leading-tight font-bold tracking-tight [overflow-wrap:anywhere]">{{ $ticket->subject }}</h1>
        <div class="mt-2.5 flex flex-wrap items-center gap-1.5">
            <x-ui.badge :color="$ticket->status->portalColor()" dot>{{ $ticket->status->portalLabel() }}</x-ui.badge>
        </div>
    </header>

    <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_260px]">
        <aside class="space-y-4 lg:order-last" aria-label="Ticketdaten">
            @foreach ($this->appointments as $appointment)
                <div wire:key="appointment-{{ $appointment->id }}" class="rounded-card bg-teal-500/[0.07] p-4 ring-1 ring-teal-500/25">
                    <p class="flex items-center gap-1.5 text-[13px] font-semibold text-teal-700 dark:text-teal-300">
                        <x-heroicon-o-calendar-days class="size-4" aria-hidden="true" /> Vor-Ort-Termin
                    </p>
                    <p class="mt-2 text-sm font-semibold">{{ $appointment->starts_at->isoFormat('dddd, D. MMMM YYYY') }}</p>
                    <p class="text-sm tabular-nums">{{ $appointment->starts_at->format('H:i') }}–{{ $appointment->ends_at->format('H:i') }} Uhr</p>
                    @if ($appointment->technician)
                        <p class="mt-2 text-[13px] text-ink-muted">Techniker: <span class="text-ink">{{ $appointment->technician->name }}</span></p>
                    @endif
                    <p class="mt-1 text-[13px] whitespace-pre-line text-ink-muted">{{ $appointment->location }}</p>
                </div>
            @endforeach

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

        <div class="min-w-0">
            <div class="space-y-4">
                @php($day = null)
                @foreach ($this->messages as $message)
                    @if ($day !== $message->created_at->toDateString())
                        @php($day = $message->created_at->toDateString())
                        <x-ticket.day :date="$message->created_at" />
                    @endif
                    <x-ticket.message wire:key="message-{{ $message->id }}" :message="$message" :mine="$message->isFromCustomer()" :portal="true" :you="$contact" />
                @endforeach
            </div>

            @if ($contact->can('replyInPortal', $ticket))
                {{-- Always at hand: a slim bar that expands into the editor --}}
                <section aria-label="Antworten"
                    x-data="{ expanded: false, open() { this.expanded = true; this.$nextTick(() => this.$root.querySelector('trix-editor')?.focus()) } }"
                    x-on:composer-sent.window="expanded = false"
                    x-on:keydown.escape="expanded = false"
                    class="sticky bottom-[calc(57px+env(safe-area-inset-bottom))] z-10 -mx-4 mt-6 border-t border-hairline bg-canvas/92 px-4 py-3 backdrop-blur-2xl backdrop-saturate-150 sm:mx-0 sm:rounded-2xl sm:border-0 sm:bg-surface/85 sm:p-3 sm:shadow-pop md:bottom-4"
                >
                    @if ($ticket->isClosed())
                        <p class="mb-2 flex items-center gap-1.5 text-[12px] text-ink-muted">
                            <x-heroicon-m-information-circle class="size-4 shrink-0" aria-hidden="true" />
                            Dieses Ticket ist geschlossen. Mit Ihrer Antwort wird es wieder geöffnet.
                        </p>
                    @endif

                    <div x-show="! expanded" class="flex items-center gap-2">
                        <x-ui.avatar :name="$contact->fullName()" class="max-sm:hidden" />
                        <button type="button" x-on:click="open()" class="flex h-10 min-w-0 flex-1 items-center rounded-full bg-surface px-4 text-left text-sm text-ink-faint shadow-card hover:text-ink-muted sm:bg-ink/[0.04] sm:shadow-none sm:ring-1 sm:ring-hairline">
                            <span class="truncate">Antworten …</span>
                        </button>
                    </div>

                    <div x-show="expanded" x-cloak>
                        <div class="mb-2 flex items-center justify-between gap-3">
                            <p class="text-[13px] font-semibold">Antworten</p>
                            <button type="button" x-on:click="expanded = false" class="rounded-full p-1.5 text-ink-faint hover:bg-ink/[0.06] hover:text-ink">
                                <x-heroicon-m-chevron-down class="size-5" aria-hidden="true" /><span class="sr-only">Einklappen</span>
                            </button>
                        </div>
                        <form wire:submit="reply" class="space-y-3">
                            <div class="[&_trix-editor]:max-h-[40vh] [&_trix-editor]:overflow-y-auto">
                                <x-ui.rich-editor wire:model="body" placeholder="Ihre Antwort …" />
                            </div>
                            <div class="flex flex-wrap items-end justify-between gap-3">
                                @include('livewire.portal.partials.files')
                                <x-ui.button type="submit" icon="paper-airplane" wire:loading.attr="disabled" wire:target="files,reply">Antwort senden</x-ui.button>
                            </div>
                        </form>
                    </div>
                </section>
            @else
                <x-ui.card class="mt-6">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <p class="text-sm text-ink-muted">Dieses Ticket ist seit {{ $ticket->closed_at?->isoFormat('D. MMMM YYYY') }} geschlossen. Für ein neues Anliegen legen Sie bitte ein neues Ticket an.</p>
                        <x-ui.button variant="secondary" icon="plus" :href="route('portal.tickets.create', ['bezug' => $ticket->id])" wire:navigate>Neues Ticket dazu</x-ui.button>
                    </div>
                </x-ui.card>
            @endif
        </div>
    </div>
</div>
