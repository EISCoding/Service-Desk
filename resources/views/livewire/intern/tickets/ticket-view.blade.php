@php
    $canUpdate = auth()->user()->can('update', $ticket);
@endphp

<div class="px-4 py-4 sm:px-6 lg:px-8 lg:py-6">
    <a href="{{ route('intern.tickets.index') }}" wire:navigate class="mb-3 inline-flex items-center gap-1 text-[13px] font-medium text-ink-muted hover:text-ink lg:hidden">
        <x-heroicon-m-chevron-left class="size-4" aria-hidden="true" /> Tickets
    </a>

    <header class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div class="min-w-0">
            <p class="font-mono text-[13px] text-ink-muted">{{ $ticket->reference() }}</p>
            <h2 class="mt-0.5 text-[22px] leading-tight font-semibold tracking-tight [overflow-wrap:anywhere]">{{ $ticket->subject }}</h2>
            <div class="mt-2 flex flex-wrap gap-1.5">
                <x-ui.badge :color="$ticket->status->color()" dot :title="$ticket->status->description()">{{ $ticket->status->label() }}</x-ui.badge>
                <x-ui.badge :color="$ticket->priority->color()">{{ $ticket->priority->label() }}</x-ui.badge>
                @if ($ticket->mergedInto)
                    <a href="{{ route('intern.tickets.show', $ticket->mergedInto) }}" wire:navigate>
                        <x-ui.badge>Zusammengeführt in {{ $ticket->mergedInto->reference() }} →</x-ui.badge>
                    </a>
                @endif
            </div>
        </div>

        <div class="flex shrink-0 items-center gap-2">
            @if ($canUpdate && $ticket->assignee_id !== auth()->id())
                <x-ui.button icon="hand-raised" wire:click="take">Übernehmen</x-ui.button>
            @endif
            @canany(['delete', 'merge'], $ticket)
                <div x-data="{ open: false }" class="relative" x-on:keydown.escape="open = false">
                    <x-ui.button variant="secondary" x-on:click="open = ! open" x-bind:aria-expanded="open">
                        <x-heroicon-m-ellipsis-horizontal class="size-5" aria-hidden="true" />
                        <span class="sr-only">Weitere Aktionen</span>
                    </x-ui.button>
                    <div x-show="open" x-cloak x-on:click.outside="open = false" x-transition.opacity class="absolute right-0 z-20 mt-1 w-56 rounded-xl bg-surface p-1 shadow-lg ring-1 ring-separator">
                        @if (! $ticket->merged_into_id)
                            @can('merge', $ticket)
                                <button type="button" x-on:click="open = false; $dispatch('open-modal', { name: 'merge' })" class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-sm hover:bg-surface-muted">
                                    <x-heroicon-o-arrows-pointing-in class="size-4 text-ink-muted" aria-hidden="true" /> Zusammenführen …
                                </button>
                            @endcan
                        @endif
                        @can('delete', $ticket)
                            <button type="button" x-on:click="open = false" wire:click="delete" wire:confirm="{{ $ticket->reference() }} mit allen Nachrichten und Anhängen endgültig löschen?" class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-sm text-red-600 hover:bg-surface-muted dark:text-red-400">
                                <x-heroicon-o-trash class="size-4" aria-hidden="true" /> Ticket löschen
                            </button>
                        @endcan
                    </div>
                </div>
            @endcanany
        </div>
    </header>

    <div class="grid gap-6 2xl:grid-cols-[minmax(0,1fr)_280px]">
        {{-- Ticket fields --}}
        <aside class="2xl:order-last" aria-label="Ticketdaten">
            <x-ui.card :padding="false" class="2xl:sticky 2xl:top-6">
                <div class="grid grid-cols-2 gap-x-4 gap-y-3 p-4 sm:grid-cols-3 2xl:grid-cols-1">
                    <x-ui.select label="Status" :options="$statusOptions" :selected="$ticket->status->value" wire:change="setStatus($event.target.value)" wire:key="status-{{ $ticket->status->value }}-{{ $statusVersion }}" :disabled="! $canUpdate" />
                    <x-ui.select label="Priorität" :options="$priorityOptions" :selected="$ticket->priority->value" wire:change="setPriority($event.target.value)" :disabled="! $canUpdate" />
                    <x-ui.select label="Gruppe" placeholder="Ohne Gruppe" :options="$this->groupOptions" :selected="$ticket->group_id" wire:change="setGroup($event.target.value)" :disabled="! $canUpdate" />
                    <x-ui.select label="Bearbeiter" placeholder="Nicht zugeordnet" :options="$this->assigneeOptions" :selected="$ticket->assignee_id" wire:change="setAssignee($event.target.value)" :disabled="! $canUpdate" />

                    {{-- Customer with search --}}
                    <div class="col-span-2 sm:col-span-1 2xl:col-span-1" x-data="{ editing: false }">
                        <p class="mb-1.5 text-[13px] font-medium">Kunde</p>
                        @if ($ticket->customer)
                            <div class="flex items-start justify-between gap-2" x-show="! editing">
                                <a href="{{ route('intern.customers.show', $ticket->customer) }}" wire:navigate class="min-w-0 text-sm text-accent hover:underline">
                                    <span class="block truncate font-medium">{{ $ticket->customer->company }}</span>
                                    <span class="font-mono text-[12px] text-ink-muted">{{ $ticket->customer->number }}</span>
                                </a>
                                @if ($canUpdate)
                                    <button type="button" x-on:click="editing = true; $nextTick(() => $refs.search.focus())" class="text-[12px] text-ink-muted hover:text-ink">Ändern</button>
                                @endif
                            </div>
                        @else
                            <p class="text-sm text-ink-muted italic" x-show="! editing">Ohne Kunde
                                @if ($canUpdate)
                                    <button type="button" x-on:click="editing = true; $nextTick(() => $refs.search.focus())" class="ml-1 not-italic text-accent hover:underline">Zuordnen</button>
                                @endif
                            </p>
                        @endif

                        @if ($canUpdate)
                            <div x-show="editing" x-cloak x-on:keydown.escape="editing = false" class="relative">
                                <input x-ref="search" type="search" wire:model.live.debounce.250ms="customerSearch" placeholder="Firma oder Nummer …" aria-label="Kunde suchen"
                                    class="block h-9 w-full rounded-[10px] bg-surface px-3 text-sm shadow-xs ring-1 ring-separator ring-inset placeholder:text-ink-faint focus:ring-2 focus:ring-accent focus:outline-none">
                                @if ($this->customerResults->isNotEmpty())
                                    <ul class="absolute z-20 mt-1 w-full overflow-hidden rounded-xl bg-surface shadow-lg ring-1 ring-separator">
                                        @foreach ($this->customerResults as $result)
                                            <li>
                                                <button type="button" wire:click="setCustomer({{ $result->id }})" x-on:click="editing = false" class="block w-full px-3 py-2 text-left text-sm hover:bg-surface-muted">
                                                    <span class="font-medium">{{ $result->company }}</span>
                                                    <span class="text-[12px] text-ink-muted">· {{ $result->number }} {{ $result->city }}</span>
                                                </button>
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif
                                <div class="mt-1 flex justify-between text-[12px]">
                                    @if ($ticket->customer)
                                        <button type="button" wire:click="setCustomer(null)" x-on:click="editing = false" class="text-red-600 hover:underline dark:text-red-400">Kunde entfernen</button>
                                    @endif
                                    <button type="button" x-on:click="editing = false" class="ml-auto text-ink-muted hover:text-ink">Abbrechen</button>
                                </div>
                            </div>
                        @endif
                    </div>

                    <div class="col-span-2 sm:col-span-1 2xl:col-span-1">
                        <x-ui.select label="Ansprechpartner" :placeholder="$ticket->customer ? 'Kein Ansprechpartner' : 'Erst Kunde wählen'" :options="$this->contactOptions" :selected="$ticket->contact_id" wire:change="setContact($event.target.value)" :disabled="! $canUpdate || ! $ticket->customer" />
                        @if ($ticket->contact)
                            <p class="mt-1 truncate text-[12px] text-ink-muted">
                                @if ($ticket->contact->email)
                                    <a href="mailto:{{ $ticket->contact->email }}" class="hover:text-accent">{{ $ticket->contact->email }}</a>
                                @endif
                                {{ $ticket->contact->phone ? ' · '.$ticket->contact->phone : '' }}
                            </p>
                        @endif
                    </div>
                </div>

                {{-- On-site appointments (VOS) --}}
                <div class="border-t border-separator px-4 py-3">
                    <div class="flex items-center justify-between">
                        <p class="text-[13px] font-medium">Termine</p>
                        @if ($canUpdate)
                            <button type="button" wire:click="planAppointment" class="text-[12px] font-medium text-accent hover:underline">Termin planen</button>
                        @endif
                    </div>
                    @forelse ($this->appointments as $appointment)
                        <div wire:key="appointment-{{ $appointment->id }}" @class(['mt-2 rounded-lg bg-teal-500/10 px-3 py-2 text-[12px]', 'opacity-60' => $appointment->ends_at->isPast()])>
                            <div class="flex items-start justify-between gap-2">
                                <p class="font-medium text-ink">{{ $appointment->timeRange() }}</p>
                                @if ($canUpdate)
                                    <button type="button" wire:click="deleteAppointment({{ $appointment->id }})" wire:confirm="Termin löschen?" class="text-ink-faint hover:text-red-600">
                                        <x-heroicon-m-x-mark class="size-4" aria-hidden="true" /><span class="sr-only">Termin löschen</span>
                                    </button>
                                @endif
                            </div>
                            <p class="text-ink-muted">{{ $appointment->technician?->name ?? 'Kein Techniker' }}</p>
                            <p class="mt-1 whitespace-pre-line text-ink-muted">{{ $appointment->location }}</p>
                            @if ($appointment->note)
                                <p class="mt-1 text-ink">{{ $appointment->note }}</p>
                            @endif
                        </div>
                    @empty
                        <p class="mt-1 text-[12px] text-ink-muted">Keine Termine.</p>
                    @endforelse
                </div>

                <dl class="grid grid-cols-2 gap-3 border-t border-separator px-4 py-3 text-[12px] text-ink-muted 2xl:grid-cols-1">
                    <div class="flex justify-between gap-2"><dt>Quelle</dt><dd class="text-ink">{{ $ticket->source->label() }}</dd></div>
                    <div class="flex justify-between gap-2"><dt>Erstellt</dt><dd class="text-ink">{{ $ticket->created_at->isoFormat('D. MMM YYYY, HH:mm') }}</dd></div>
                    @if ($ticket->follow_up_at)
                        <div class="flex justify-between gap-2"><dt>Wiedervorlage</dt><dd @class(['font-medium', 'text-orange-600 dark:text-orange-400' => $ticket->follow_up_due, 'text-ink' => ! $ticket->follow_up_due])>{{ $ticket->follow_up_at->isoFormat('dd, D. MMM YYYY') }}</dd></div>
                    @endif
                    @if ($ticket->closed_at)
                        <div class="flex justify-between gap-2"><dt>Geschlossen</dt><dd class="text-ink">{{ $ticket->closed_at->isoFormat('D. MMM YYYY, HH:mm') }}</dd></div>
                    @endif
                </dl>
            </x-ui.card>
        </aside>

        {{-- Conversation --}}
        <div class="min-w-0 space-y-4">
            @forelse ($this->messages as $message)
                <article wire:key="message-{{ $message->id }}" @class([
                    'rounded-card p-4 ring-1 sm:p-5',
                    'bg-yellow-500/10 ring-yellow-500/30' => $message->isInternal(),
                    'bg-surface ring-separator/80' => ! $message->isInternal(),
                ])>
                    <header class="mb-3 flex items-start gap-3">
                        <x-ui.avatar :name="$message->authorName()" />
                        <div class="min-w-0 flex-1">
                            <p class="text-sm">
                                <span class="font-medium">{{ $message->authorName() }}</span>
                                @if ($message->author instanceof \App\Models\Contact)
                                    <span class="text-[12px] text-ink-muted">· Kunde</span>
                                @endif
                            </p>
                            <p class="text-[12px] text-ink-muted">
                                <time datetime="{{ $message->created_at->toIso8601String() }}" title="{{ $message->created_at->isoFormat('D. MMMM YYYY, HH:mm') }}">{{ $message->created_at->isoFormat('D. MMM YYYY, HH:mm') }}</time>
                            </p>
                        </div>
                        @if ($message->isInternal())
                            <x-ui.badge color="yellow"><x-heroicon-m-lock-closed class="size-3" aria-hidden="true" /> Interne Notiz</x-ui.badge>
                        @elseif ($message->type !== \App\Enums\MessageType::Public)
                            <div class="flex flex-wrap justify-end gap-1">
                                @if ($message->is_automatic)
                                    <x-ui.badge color="gray"><x-heroicon-m-cpu-chip class="size-3" aria-hidden="true" /> Automatisch</x-ui.badge>
                                @endif
                                <x-ui.badge><x-heroicon-m-envelope class="size-3" aria-hidden="true" /> {{ $message->type->label() }}</x-ui.badge>
                            </div>
                        @endif
                    </header>

                    @if ($message->type === \App\Enums\MessageType::MailOut)
                        <p class="-mt-1 mb-3 text-[12px] text-ink-muted">
                            An {{ collect($message->to)->pluck('email')->join(', ') }}@if ($message->cc) · CC {{ collect($message->cc)->pluck('email')->join(', ') }}@endif
                            @switch($message->mail_status)
                                @case('queued') · <span class="text-yellow-700 dark:text-yellow-300">wird gesendet …</span> @break
                                @case('sent') · gesendet {{ $message->sent_at?->isoFormat('D. MMM, HH:mm') }} @break
                            @endswitch
                        </p>
                        @if ($message->mailFailed())
                            <x-ui.alert type="error" class="mb-3">
                                Versand fehlgeschlagen: {{ $message->mail_error }}
                                @can('reply', $ticket)
                                    <button type="button" wire:click="retryMail({{ $message->id }})" class="ml-1 font-medium underline">Erneut senden</button>
                                @endcan
                            </x-ui.alert>
                        @endif
                    @endif

                    @php([$mainText, $quotedText] = $message->displayParts())
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
                                    <a href="{{ $attachment->url(inline: $attachment->canBeShownInline()) }}" target="_blank" class="flex max-w-60 items-center gap-2 rounded-xl bg-surface-muted px-3 py-2 text-[13px] ring-1 ring-separator hover:ring-accent">
                                        @if ($attachment->isImage())
                                            <img src="{{ $attachment->url(inline: true) }}" alt="" class="size-8 shrink-0 rounded-md object-cover" loading="lazy">
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
            @empty
                <x-ui.card :padding="false">
                    <x-ui.empty-state icon="chat-bubble-left-right" title="Noch keine Nachrichten" />
                </x-ui.card>
            @endforelse

            @if (auth()->user()->can('reply', $ticket) || auth()->user()->can('addNote', $ticket))
                <livewire:intern.tickets.composer :ticket="$ticket" :key="'composer-'.$ticket->id" />
            @endif
        </div>
    </div>

    {{-- Merge into another ticket --}}
    @can('merge', $ticket)
        <x-ui.modal name="merge" title="Ticket zusammenführen" description="Nachrichten, Anhänge und Termine wandern in das gewählte Ticket. Dieses Ticket wird geschlossen." max-width="lg">
            <x-ui.input wire:model.live.debounce.250ms="mergeSearch" type="search" placeholder="Zielticket: Nummer oder Betreff …" aria-label="Zielticket suchen" />
            <ul class="mt-3 divide-y divide-separator">
                @foreach ($this->mergeResults as $target)
                    <li wire:key="merge-{{ $target->id }}" class="flex items-center justify-between gap-3 py-2.5">
                        <div class="min-w-0 text-sm">
                            <p class="truncate"><span class="font-mono text-[12px] text-ink-muted">{{ $target->reference() }}</span> <span class="font-medium">{{ $target->subject }}</span></p>
                            <p class="text-[12px] text-ink-muted">{{ $target->customer?->company ?? 'Ohne Kunde' }} · {{ $target->status->label() }}</p>
                        </div>
                        <x-ui.button size="sm" variant="secondary" wire:click="merge({{ $target->id }})" wire:confirm="{{ $ticket->reference() }} in {{ $target->reference() }} zusammenführen? Das lässt sich nicht rückgängig machen.">Hierhin</x-ui.button>
                    </li>
                @endforeach
            </ul>
            @if (mb_strlen(trim($mergeSearch)) >= 2 && $this->mergeResults->isEmpty())
                <p class="mt-3 text-sm text-ink-muted">Kein passendes Ticket gefunden.</p>
            @endif
        </x-ui.modal>
    @endcan

    {{-- Appointment dialog (VOS) --}}
    <x-ui.modal name="appointment" wire:model="planningAppointment" title="VOS-Termin planen" description="Vor-Ort-Service / Kundentermin. Das Ticket erhält den Status „VOS“." max-width="lg"
        x-init="$watch('show', value => { if (! value) $wire.statusVersion++ })">
        <form wire:submit="saveAppointment" id="appointment-form" class="space-y-4">
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                <x-ui.input label="Datum" type="date" wire:model="appointmentDate" :min="today()->toDateString()" required class="col-span-2" />
                <x-ui.input label="Von" type="time" wire:model="appointmentFrom" required />
                <x-ui.input label="Bis" type="time" wire:model="appointmentTo" required />
            </div>
            <x-ui.select label="Techniker" wire:model="appointmentTechnician" :options="$this->assigneeOptions" required />
            <x-ui.textarea label="Einsatzort" wire:model="appointmentLocation" rows="3" required />
            <x-ui.textarea label="Notiz" wire:model="appointmentNote" rows="2" placeholder="z. B. Zugang über Hintereingang" />
        </form>
        <x-slot:footer>
            <x-ui.button variant="secondary" wire:click="cancelStatusChange">Abbrechen</x-ui.button>
            <x-ui.button type="submit" form="appointment-form">Termin speichern</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>

    {{-- Follow-up date for "Warte auf Intern/Extern" --}}
    <x-ui.modal name="follow-up" :title="$pendingStatus ? \App\Enums\TicketStatus::from($pendingStatus)->label() : 'Wiedervorlage'" description="Wann soll das Ticket wieder vorgelegt werden?" max-width="sm"
        x-init="$watch('show', value => { if (! value && $wire.pendingStatus) $wire.cancelStatusChange() })"
        x-effect="show = $wire.pendingStatus !== null">
        <form wire:submit="confirmFollowUp" id="follow-up-form" class="space-y-3">
            <x-ui.input label="Wiedervorlage am" type="date" wire:model="followUpDate" :min="today()->toDateString()" required />
            <div class="flex flex-wrap gap-1.5">
                @foreach (['Morgen' => today()->addWeekday(), 'In 2 Werktagen' => today()->addWeekdays(2), 'Nächste Woche' => today()->next('Monday')] as $label => $date)
                    <button type="button" wire:click="$set('followUpDate', '{{ $date->toDateString() }}')" class="rounded-full bg-ink/[0.05] px-2.5 py-1 text-[12px] font-medium text-ink-muted hover:text-ink">{{ $label }}</button>
                @endforeach
            </div>
        </form>
        <x-slot:footer>
            <x-ui.button variant="secondary" wire:click="cancelStatusChange">Abbrechen</x-ui.button>
            <x-ui.button type="submit" form="follow-up-form">Status setzen</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>
</div>
