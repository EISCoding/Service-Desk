@php
    $canUpdate = auth()->user()->can('update', $ticket);
    $pill = 'h-8 appearance-none rounded-full bg-transparent pr-7 text-[13px] font-medium text-ink focus:outline-none disabled:opacity-70';
    $field = 'h-9 w-full appearance-none rounded-lg bg-surface pr-8 pl-3 text-[13px] text-ink ring-1 ring-ink/15 ring-inset hover:ring-ink/25 focus:ring-2 focus:ring-accent focus:outline-none disabled:opacity-70';
    $section = 'text-[11px] font-semibold tracking-wide text-ink-faint uppercase';
    $statusDot = match ($ticket->status->color()) {
        'blue' => 'bg-blue-500', 'indigo' => 'bg-indigo-500', 'yellow' => 'bg-yellow-500', 'orange' => 'bg-orange-500',
        'purple' => 'bg-purple-500', 'teal' => 'bg-teal-500', default => 'bg-zinc-400',
    };
@endphp

{{--
    Ticket (design E): header with tabs, conversation as cards and the composer
    on the left; ticket fields and customer details in a full-height sidebar on
    the right (a collapsible card in narrow panes).
--}}
<div wire:poll.10s="checkForChanges" class="@container/ticket min-h-full">
    <div class="grid min-h-full grid-rows-[auto_auto_1fr_auto] @3xl/ticket:grid-cols-[minmax(0,1fr)_19rem] @3xl/ticket:grid-rows-[auto_1fr_auto]">
        {{-- Header --}}
        <header class="border-b border-separator bg-surface px-4 pt-4 sm:px-6 @3xl/ticket:col-start-1">
            <a href="{{ route('intern.tickets.index') }}" wire:navigate class="mb-3 inline-flex items-center gap-1 text-[13px] font-medium text-accent lg:hidden">
                <x-bx-chevron-left class="size-4" aria-hidden="true" /> Tickets
            </a>

            <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-2">
                <p class="flex min-w-0 flex-wrap items-center gap-x-2 text-[12px] text-ink-muted">
                    <span class="font-medium tabular-nums">{{ $ticket->reference() }}</span>
                    <span aria-hidden="true">·</span>
                    <span>{{ $ticket->source->label() }}</span>
                    <span aria-hidden="true">·</span>
                    <time datetime="{{ $ticket->created_at->toIso8601String() }}">{{ $ticket->created_at->isoFormat('D. MMM YYYY, HH:mm') }}</time>
                </p>
                <div class="ml-auto flex shrink-0 items-center gap-1.5">
                    <button type="button" x-on:click="$dispatch('toggle-ticket-focus')" class="hidden size-8 place-items-center rounded-lg text-ink-muted hover:bg-ink/[0.06] hover:text-ink lg:grid"
                        x-bind:title="typeof focus !== 'undefined' && focus ? 'Ticketliste einblenden (F)' : 'Ticketliste ausblenden (F)'">
                        <x-bx-fullscreen class="size-5" aria-hidden="true" x-show="typeof focus === 'undefined' || ! focus" />
                        <x-bx-exit-fullscreen class="size-5" aria-hidden="true" x-show="typeof focus !== 'undefined' && focus" x-cloak />
                        <span class="sr-only">Ticketliste aus- oder einblenden</span>
                    </button>
                    @if ($canUpdate && $ticket->status !== \App\Enums\TicketStatus::Closed)
                        <x-ui.button variant="secondary" size="sm" icon="check-circle" wire:click="setStatus('closed')">Erledigt</x-ui.button>
                    @elseif ($canUpdate)
                        <x-ui.button variant="secondary" size="sm" icon="undo" wire:click="setStatus('in_progress')">Wieder öffnen</x-ui.button>
                    @endif
                    @if ($canUpdate && $ticket->assignee_id !== auth()->id())
                        <x-ui.button size="sm" icon="user-plus" wire:click="take">Übernehmen</x-ui.button>
                    @endif
                    @canany(['delete', 'merge'], $ticket)
                        <div x-data="{ open: false }" class="relative" x-on:keydown.escape="open = false">
                            <x-ui.button variant="secondary" size="sm" class="w-8 px-0!" x-on:click="open = ! open" x-bind:aria-expanded="open">
                                <x-bx-dots-horizontal-rounded class="size-5" aria-hidden="true" />
                                <span class="sr-only">Weitere Aktionen</span>
                            </x-ui.button>
                            <div x-show="open" x-cloak x-on:click.outside="open = false" x-transition.opacity class="absolute right-0 z-20 mt-1 w-56 rounded-xl bg-surface p-1 shadow-pop">
                                @if (! $ticket->merged_into_id)
                                    @can('merge', $ticket)
                                        <button type="button" x-on:click="open = false; $dispatch('open-modal', { name: 'merge' })" class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-sm hover:bg-surface-muted">
                                            <x-bx-exit-fullscreen class="size-4 text-ink-muted" aria-hidden="true" /> Zusammenführen …
                                        </button>
                                    @endcan
                                @endif
                                @can('delete', $ticket)
                                    <button type="button" x-on:click="open = false" wire:click="delete" wire:confirm="{{ $ticket->reference() }} mit allen Nachrichten und Anhängen endgültig löschen?" class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-sm text-red-600 hover:bg-surface-muted dark:text-red-400">
                                        <x-bx-trash class="size-4" aria-hidden="true" /> Ticket löschen
                                    </button>
                                @endcan
                            </div>
                        </div>
                    @endcanany
                </div>
            </div>

            <h2 class="mt-1.5 text-[22px] leading-tight font-semibold tracking-tight [overflow-wrap:anywhere]">{{ $ticket->subject }}</h2>
            <p class="mt-1 text-[13px] text-ink-muted">
                @if ($ticket->customer)
                    <a href="{{ route('intern.customers.show', $ticket->customer) }}" wire:navigate class="font-medium text-ink hover:text-accent">{{ $ticket->customer->company }}</a>
                @else
                    <span class="italic">Ohne Kunde</span>
                @endif
                @if ($ticket->contact)
                    · {{ $ticket->contact->fullName() }}
                @elseif ($ticket->requester_email)
                    · {{ $ticket->requester_name ?: $ticket->requester_email }}
                @endif
            </p>
            @if ($ticket->mergedInto)
                <a href="{{ route('intern.tickets.show', $ticket->mergedInto) }}" wire:navigate class="mt-2 inline-block">
                    <x-ui.badge>Zusammengeführt in {{ $ticket->mergedInto->reference() }} →</x-ui.badge>
                </a>
            @endif

            {{-- Narrow panes: properties as compact pills (Status opens with "E") --}}
            <div class="mt-3 flex flex-wrap items-center gap-2 @3xl/ticket:hidden" aria-label="Ticketdaten">
                <label class="relative inline-flex items-center gap-1.5 rounded-full bg-ink/[0.05] pl-3 hover:bg-ink/[0.08]">
                    <span @class(['size-2 rounded-full', match ($ticket->status->color()) {
                        'blue' => 'bg-blue-500', 'indigo' => 'bg-indigo-500', 'yellow' => 'bg-yellow-500', 'orange' => 'bg-orange-500',
                        'purple' => 'bg-purple-500', 'teal' => 'bg-teal-500', default => 'bg-zinc-400',
                    }]) aria-hidden="true"></span>
                    <span class="sr-only">Status</span>
                    <select class="{{ $pill }}" wire:change="setStatus($event.target.value)" data-shortcut="status" wire:key="status-{{ $ticket->status->value }}-{{ $statusVersion }}" @disabled(! $canUpdate)>
                        @foreach ($statusOptions as $value => $label)
                            <option value="{{ $value }}" @selected($ticket->status->value === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <x-bx-chevron-down class="pointer-events-none absolute right-2.5 size-3.5 text-ink-faint" aria-hidden="true" />
                </label>

                @foreach ([
                    ['Priorität', 'setPriority', $priorityOptions, $ticket->priority->value, null, 'flag'],
                    ['Gruppe', 'setGroup', $this->groupOptions, $ticket->group_id, 'Ohne Gruppe', 'network-chart'],
                    ['Bearbeiter', 'setAssignee', $this->assigneeOptions, $ticket->assignee_id, 'Nicht zugeordnet', 'user'],
                ] as [$label, $action, $options, $current, $placeholder, $icon])
                    <label class="relative inline-flex items-center gap-1.5 rounded-full bg-ink/[0.05] pl-3 hover:bg-ink/[0.08]">
                        <x-dynamic-component :component="\App\Support\Icon::component($icon)" class="size-3.5 text-ink-muted" aria-hidden="true" />
                        <span class="sr-only">{{ $label }}</span>
                        <select class="{{ $pill }}" wire:change="{{ $action }}($event.target.value)" @disabled(! $canUpdate)>
                            @if ($placeholder)
                                <option value="">{{ $placeholder }}</option>
                            @endif
                            @foreach ($options as $value => $optionLabel)
                                <option value="{{ $value }}" @selected((string) $current === (string) $value)>{{ $optionLabel }}</option>
                            @endforeach
                        </select>
                        <x-bx-chevron-down class="pointer-events-none absolute right-2.5 size-3.5 text-ink-faint" aria-hidden="true" />
                    </label>
                @endforeach

                @if ($ticket->follow_up_at)
                    <x-ui.badge :color="$ticket->follow_up_due ? 'orange' : 'gray'" class="h-8 px-3">
                        <x-bxs-bell-ring class="size-3.5" aria-hidden="true" /> Wiedervorlage {{ $ticket->follow_up_at->isoFormat('dd, D. MMM') }}
                    </x-ui.badge>
                @endif
            </div>

            <div role="tablist" aria-label="Ansicht" class="mt-3 flex gap-5">
                @foreach (['messages' => 'Verlauf', 'activity' => 'Aktivität'] as $key => $label)
                    <button type="button" role="tab" aria-selected="{{ $tab === $key ? 'true' : 'false' }}" wire:click="$set('tab', '{{ $key }}')"
                        @class([
                            '-mb-px border-b-2 pt-1 pb-2.5 text-[13px] transition-colors',
                            'border-accent font-semibold text-ink' => $tab === $key,
                            'border-transparent font-medium text-ink-muted hover:text-ink' => $tab !== $key,
                        ])>
                        {{ $label }}
                        @if ($key === 'messages')
                            <span class="ml-0.5 text-[12px] font-medium text-ink-faint tabular-nums">{{ $this->messages->count() }}</span>
                        @endif
                    </button>
                @endforeach
            </div>
        </header>

        {{-- Ticket fields and customer details --}}
        <aside aria-label="Details" class="min-w-0 px-4 pt-4 sm:px-6 @3xl/ticket:col-start-2 @3xl/ticket:row-span-3 @3xl/ticket:row-start-1 @3xl/ticket:self-start @3xl/ticket:sticky @3xl/ticket:top-14 @3xl/ticket:max-h-[calc(100dvh-3.5rem)] @3xl/ticket:overflow-y-auto @3xl/ticket:border-l @3xl/ticket:border-separator @3xl/ticket:bg-surface @3xl/ticket:p-0 lg:@3xl/ticket:top-0 lg:@3xl/ticket:h-[calc(100dvh-3.5rem)]">
            {{-- Wide panes: the ticket fields as a form (narrow panes show them as pills in the header) --}}
            <section class="hidden border-b border-separator px-5 py-4 @3xl/ticket:block" aria-label="Ticketdaten">
                <p class="mb-3 {{ $section }}">Ticket</p>
                <div class="space-y-2.5">
                    <label class="grid grid-cols-[6.5rem_minmax(0,1fr)] items-center gap-2">
                        <span class="text-[13px] text-ink-muted">Status</span>
                        <span class="relative">
                            <span class="pointer-events-none absolute top-1/2 left-3 size-2 -translate-y-1/2 rounded-full {{ $statusDot }}" aria-hidden="true"></span>
                            <select class="{{ $field }} pl-7" wire:change="setStatus($event.target.value)" data-shortcut="status" wire:key="status-field-{{ $ticket->status->value }}-{{ $statusVersion }}" @disabled(! $canUpdate)>
                                @foreach ($statusOptions as $value => $label)
                                    <option value="{{ $value }}" @selected($ticket->status->value === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            <x-bx-chevron-down class="pointer-events-none absolute top-1/2 right-2.5 size-4 -translate-y-1/2 text-ink-faint" aria-hidden="true" />
                        </span>
                    </label>
                    @foreach ([
                        ['Priorität', 'setPriority', $priorityOptions, $ticket->priority->value, null],
                        ['Gruppe', 'setGroup', $this->groupOptions, $ticket->group_id, 'Ohne Gruppe'],
                        ['Bearbeiter', 'setAssignee', $this->assigneeOptions, $ticket->assignee_id, 'Nicht zugeordnet'],
                    ] as [$label, $action, $options, $current, $placeholder])
                        <label class="grid grid-cols-[6.5rem_minmax(0,1fr)] items-center gap-2">
                            <span class="text-[13px] text-ink-muted">{{ $label }}</span>
                            <span class="relative">
                                <select class="{{ $field }}" wire:change="{{ $action }}($event.target.value)" @disabled(! $canUpdate)>
                                    @if ($placeholder)
                                        <option value="">{{ $placeholder }}</option>
                                    @endif
                                    @foreach ($options as $value => $optionLabel)
                                        <option value="{{ $value }}" @selected((string) $current === (string) $value)>{{ $optionLabel }}</option>
                                    @endforeach
                                </select>
                                <x-bx-chevron-down class="pointer-events-none absolute top-1/2 right-2.5 size-4 -translate-y-1/2 text-ink-faint" aria-hidden="true" />
                            </span>
                        </label>
                    @endforeach
                    @if ($ticket->follow_up_at)
                        <p class="grid grid-cols-[6.5rem_minmax(0,1fr)] items-center gap-2 text-[13px]">
                            <span class="text-ink-muted">Wiedervorlage</span>
                            <span @class(['inline-flex items-center gap-1.5 font-medium', 'text-orange-700 dark:text-orange-400' => $ticket->follow_up_due])>
                                <x-bxs-bell-ring class="size-4" aria-hidden="true" /> {{ $ticket->follow_up_at->isoFormat('dd, D. MMM YYYY') }}
                            </span>
                        </p>
                    @endif
                </div>
            </section>

            <div x-data="{ open: {{ $ticket->customer_id ? 'false' : 'true' }} }" class="@container/details overflow-hidden rounded-card bg-surface shadow-card @3xl/ticket:overflow-visible @3xl/ticket:rounded-none @3xl/ticket:bg-transparent @3xl/ticket:shadow-none">
                <button type="button" x-on:click="open = ! open" x-bind:aria-expanded="open" class="flex w-full items-center gap-3 px-4 py-3 text-left text-[13px] hover:bg-surface-muted/50 @3xl/ticket:hidden">
                    <span class="flex min-w-0 flex-1 flex-wrap items-center gap-x-4 gap-y-1 text-ink-muted">
                        <span class="inline-flex items-center gap-1.5">
                            <x-bx-buildings class="size-4" aria-hidden="true" />
                            <span @class(['font-medium text-ink' => $ticket->customer, 'italic' => ! $ticket->customer])>{{ $ticket->customer?->company ?? 'Ohne Kunde' }}</span>
                        </span>
                        @if ($ticket->contact)
                            <span class="inline-flex items-center gap-1.5"><x-bx-user class="size-4" aria-hidden="true" /> {{ $ticket->contact->fullName() }}</span>
                        @endif
                        <span class="inline-flex items-center gap-1.5">
                            <x-bx-calendar class="size-4" aria-hidden="true" />
                            {{ $this->appointments->isEmpty() ? 'Keine Termine' : trans_choice(':count Termin|:count Termine', $this->appointments->count()) }}
                        </span>
                    </span>
                    <span class="text-[12px] font-medium text-accent" x-text="open ? 'Weniger' : 'Details'">Details</span>
                </button>

                <div x-show="open" x-cloak class="border-t border-hairline @3xl/ticket:!block @3xl/ticket:border-t-0">
                <div class="grid grid-cols-1 gap-x-6 gap-y-5 p-4 @xl/details:grid-cols-2 @3xl/ticket:px-5">
                    {{-- Customer with search --}}
                    <div x-data="{ editing: false }">
                        <p class="mb-1.5 {{ $section }}">Kunde</p>
                        @if ($ticket->customer)
                            <div class="flex items-start justify-between gap-2" x-show="! editing">
                                <a href="{{ route('intern.customers.show', $ticket->customer) }}" wire:navigate class="min-w-0 text-sm text-accent hover:underline">
                                    <span class="block truncate font-medium">{{ $ticket->customer->company }}</span>
                                    <span class="tabular-nums text-[12px] text-ink-muted">{{ $ticket->customer->number }}</span>
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
                                    class="block h-9 w-full rounded-[10px] bg-surface px-3 text-sm ring-1 ring-ink/15 ring-inset placeholder:text-ink-faint focus:ring-2 focus:ring-accent focus:outline-none">
                                @if ($this->customerResults->isNotEmpty())
                                    <ul class="absolute z-20 mt-1 w-full overflow-hidden rounded-xl bg-surface shadow-pop">
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

                    <div>
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

                    {{-- On-site appointments (VOS) --}}
                    <div>
                        <div class="flex items-center justify-between">
                            <p class="{{ $section }}">Termine</p>
                            @if ($canUpdate)
                                <button type="button" wire:click="planAppointment" class="text-[12px] font-medium text-accent hover:underline">Termin planen</button>
                            @endif
                        </div>
                        @forelse ($this->appointments as $appointment)
                            <div wire:key="appointment-{{ $appointment->id }}" @class(['mt-2 rounded-xl bg-teal-500/10 px-3 py-2 text-[12px]', 'opacity-60' => $appointment->ends_at->isPast()])>
                                <div class="flex items-start justify-between gap-2">
                                    <p class="font-medium text-ink">{{ $appointment->timeRange() }}</p>
                                    @if ($canUpdate)
                                        <button type="button" wire:click="deleteAppointment({{ $appointment->id }})" wire:confirm="Termin löschen?" class="text-ink-faint hover:text-red-600">
                                            <x-bx-x class="size-4" aria-hidden="true" /><span class="sr-only">Termin löschen</span>
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

                    <dl class="space-y-1.5 text-[12px] text-ink-muted">
                        <div class="flex justify-between gap-2"><dt>Quelle</dt><dd class="text-ink">{{ $ticket->source->label() }}</dd></div>
                        <div class="flex justify-between gap-2"><dt>Erstellt</dt><dd class="text-ink">{{ $ticket->created_at->isoFormat('D. MMM YYYY, HH:mm') }}</dd></div>
                        @if ($ticket->follow_up_at)
                            <div class="flex justify-between gap-2"><dt>Wiedervorlage</dt><dd @class(['font-medium', 'text-orange-600 dark:text-orange-400' => $ticket->follow_up_due, 'text-ink' => ! $ticket->follow_up_due])>{{ $ticket->follow_up_at->isoFormat('dd, D. MMM YYYY') }}</dd></div>
                        @endif
                        @if ($ticket->closed_at)
                            <div class="flex justify-between gap-2"><dt>Geschlossen</dt><dd class="text-ink">{{ $ticket->closed_at->isoFormat('D. MMM YYYY, HH:mm') }}</dd></div>
                        @endif
                    </dl>

                    @if ($ticket->customer)
                        {{-- Customer context: notes and other tickets --}}
                        <div class="space-y-5 border-t border-hairline pt-4 @xl/details:col-span-2">
                            @if ($this->customerNotes->isNotEmpty())
                                <div>
                                    <div class="flex items-center justify-between">
                                        <p class="{{ $section }}">Kundennotizen</p>
                                        @can('view', $ticket->customer)
                                        <a href="{{ route('intern.customers.show', ['customer' => $ticket->customer, 'tab' => 'notizen']) }}" wire:navigate class="text-[12px] font-medium text-accent hover:underline">Alle</a>
                                        @endcan
                                    </div>
                                    <ul class="mt-2 space-y-2">
                                        @foreach ($this->customerNotes as $note)
                                            <li wire:key="note-{{ $note->id }}" @class(['rounded-xl px-3 py-2 text-[12px]', 'bg-yellow-400/15' => $note->is_pinned, 'bg-ink/[0.04]' => ! $note->is_pinned])>
                                                <div class="line-clamp-4 text-ink [&_p]:inline">{!! $note->body !!}</div>
                                                <p class="mt-1 text-ink-muted">
                                                    @if ($note->is_pinned)<x-bx-bookmark class="inline size-3" aria-hidden="true" /> Angeheftet · @endif
                                                    {{ $note->created_at->isoFormat('D. MMM YYYY') }}
                                                </p>
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif

                            <div>
                                <div class="flex items-center justify-between">
                                    <p class="{{ $section }}">Weitere Tickets</p>
                                    @can('view', $ticket->customer)
                                        <a href="{{ route('intern.customers.show', ['customer' => $ticket->customer, 'tab' => 'tickets']) }}" wire:navigate class="text-[12px] font-medium text-accent hover:underline">Alle</a>
                                        @endcan
                                </div>
                                @forelse ($this->otherTickets as $other)
                                    <a wire:key="other-{{ $other->id }}" href="{{ route('intern.tickets.show', $other) }}" wire:navigate class="-mx-2 mt-1 flex items-center gap-2 rounded-lg px-2 py-1.5 hover:bg-ink/[0.04]">
                                        <span class="min-w-0 flex-1">
                                            <span class="block truncate text-[13px] font-medium">{{ $other->subject }}</span>
                                            <span class="block text-[11px] text-ink-muted tabular-nums">{{ $other->reference() }} · {{ $other->last_activity_at?->diffForHumans(short: true) }}</span>
                                        </span>
                                        <x-ui.badge :color="$other->status->color()" dot class="shrink-0">{{ $other->status->label() }}</x-ui.badge>
                                    </a>
                                @empty
                                    <p class="mt-1 text-[12px] text-ink-muted">Keine weiteren Tickets.</p>
                                @endforelse
                            </div>
                        </div>
                    @endif
                </div>
                </div>
            </div>
        </aside>

        {{-- Conversation / activity log --}}
        <div class="min-w-0 px-4 py-5 sm:px-6 @3xl/ticket:col-start-1">
            @if ($tab === 'activity')
                <livewire:intern.tickets.history :ticket="$ticket" :key="'history-'.$ticket->id" />
            @else
                <div class="space-y-3">
                    @php($day = null)
                    @forelse ($this->messages as $message)
                        @if ($day !== $message->created_at->toDateString())
                            @php($day = $message->created_at->toDateString())
                            <x-ticket.day :date="$message->created_at" />
                        @endif
                        <x-ticket.message wire:key="message-{{ $message->id }}" :message="$message" :mine="! $message->isFromCustomer()" :can-retry="auth()->user()->can('reply', $ticket)" />
                    @empty
                        <x-ui.empty-state icon="conversation" title="Noch keine Nachrichten" />
                    @endforelse
                </div>
            @endif
        </div>

        @if ($tab !== 'activity' && (auth()->user()->can('reply', $ticket) || auth()->user()->can('addNote', $ticket)))
            <livewire:intern.tickets.composer :ticket="$ticket" :key="'composer-'.$ticket->id" />
        @endif
    </div>

    {{-- Merge into another ticket --}}
    @can('merge', $ticket)
        <x-ui.modal name="merge" title="Ticket zusammenführen" description="Nachrichten, Anhänge und Termine wandern in das gewählte Ticket. Dieses Ticket wird geschlossen." max-width="lg">
            <x-ui.input wire:model.live.debounce.250ms="mergeSearch" type="search" placeholder="Zielticket: Nummer oder Betreff …" aria-label="Zielticket suchen" />
            <ul class="mt-3 divide-y divide-separator">
                @foreach ($this->mergeResults as $target)
                    <li wire:key="merge-{{ $target->id }}" class="flex items-center justify-between gap-3 py-2.5">
                        <div class="min-w-0 text-sm">
                            <p class="truncate"><span class="tabular-nums text-[12px] text-ink-muted">{{ $target->reference() }}</span> <span class="font-medium">{{ $target->subject }}</span></p>
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
            @if ($planningAppointment && $this->canConfirmAppointment())
                <x-ui.checkbox label="Terminbestätigung an den Kunden senden" wire:model="appointmentNotify"
                    :description="'Vorlage „Termin bestätigt“ an '.$ticket->recipient()['email']" />
            @endif
        </form>
        <x-slot:footer>
            <x-ui.button variant="secondary" wire:click="cancelStatusChange">Abbrechen</x-ui.button>
            <x-ui.button type="submit" form="appointment-form">Termin speichern</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>

    {{-- Status change: follow-up date ("Warte auf Intern/Extern") and/or mail to the customer --}}
    @php($pending = $pendingStatus ? \App\Enums\TicketStatus::tryFrom($pendingStatus) : null)
    @php($notification = $pending ? $this->notification($pending) : null)
    <x-ui.modal name="status" :title="$taking ? 'Ticket übernehmen' : ($pending ? 'Status: '.$pending->label() : 'Status ändern')" max-width="sm"
        x-init="$watch('show', value => { if (! value && $wire.pendingStatus) $wire.cancelStatusChange() })"
        x-effect="show = $wire.pendingStatus !== null">
        <form wire:submit="confirmStatus" id="status-form" class="space-y-4">
            @if ($pending?->requiresFollowUp())
                <div class="space-y-3">
                    <p class="text-sm text-ink-muted">Wann soll das Ticket wieder vorgelegt werden?</p>
                    <x-ui.input label="Wiedervorlage am" type="date" wire:model="followUpDate" :min="today()->toDateString()" required />
                    <div class="flex flex-wrap gap-1.5">
                        @foreach (['Morgen' => today()->addWeekday(), 'In 2 Werktagen' => today()->addWeekdays(2), 'Nächste Woche' => today()->next('Monday')] as $label => $date)
                            <button type="button" wire:click="$set('followUpDate', '{{ $date->toDateString() }}')" class="rounded-full bg-ink/[0.05] px-2.5 py-1 text-[12px] font-medium text-ink-muted hover:text-ink">{{ $label }}</button>
                        @endforeach
                    </div>
                </div>
            @endif
            @if ($notification)
                <x-ui.checkbox label="Kunden per E-Mail informieren" wire:model="notifyCustomer"
                    :description="'Vorlage „'.$notification->label().'“ an '.$ticket->recipient()['email']" />
            @endif
        </form>
        <x-slot:footer>
            <x-ui.button variant="secondary" wire:click="cancelStatusChange">Abbrechen</x-ui.button>
            <x-ui.button type="submit" form="status-form">{{ $taking ? 'Übernehmen' : 'Status setzen' }}</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>
</div>
