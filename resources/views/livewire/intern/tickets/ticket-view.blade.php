@php
    $canUpdate = auth()->user()->can('update', $ticket);
@endphp

<div class="px-4 py-4 sm:px-6 lg:px-8 lg:py-6">
    <a href="{{ route('intern.tickets.index') }}" wire:navigate class="mb-3 inline-flex items-center gap-1 text-[13px] font-medium text-ink-muted hover:text-ink lg:hidden">
        <x-heroicon-m-chevron-left class="size-4" aria-hidden="true" /> Tickets
    </a>

    <header class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div class="min-w-0">
            <p class="font-mono text-[13px] text-ink-muted">{{ $ticket->number }}</p>
            <h2 class="mt-0.5 text-[22px] leading-tight font-semibold tracking-tight [overflow-wrap:anywhere]">{{ $ticket->subject }}</h2>
            <div class="mt-2 flex flex-wrap gap-1.5">
                <x-ui.badge :color="$ticket->status->color()" dot :title="$ticket->status->description()">{{ $ticket->status->label() }}</x-ui.badge>
                <x-ui.badge :color="$ticket->priority->color()">{{ $ticket->priority->label() }}</x-ui.badge>
                @if ($ticket->mergedInto)
                    <x-ui.badge>Zusammengeführt in {{ $ticket->mergedInto->number }}</x-ui.badge>
                @endif
            </div>
        </div>

        <div class="flex shrink-0 items-center gap-2">
            {{ $headerActions ?? '' }}
            @can('delete', $ticket)
                <div x-data="{ open: false }" class="relative" x-on:keydown.escape="open = false">
                    <x-ui.button variant="secondary" x-on:click="open = ! open" x-bind:aria-expanded="open">
                        <x-heroicon-m-ellipsis-horizontal class="size-5" aria-hidden="true" />
                        <span class="sr-only">Weitere Aktionen</span>
                    </x-ui.button>
                    <div x-show="open" x-cloak x-on:click.outside="open = false" x-transition.opacity class="absolute right-0 z-20 mt-1 w-56 rounded-xl bg-surface p-1 shadow-lg ring-1 ring-separator">
                        <button type="button" x-on:click="open = false" wire:click="delete" wire:confirm="Ticket {{ $ticket->number }} mit allen Nachrichten und Anhängen endgültig löschen?" class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-sm text-red-600 hover:bg-surface-muted dark:text-red-400">
                            <x-heroicon-o-trash class="size-4" aria-hidden="true" /> Ticket löschen
                        </button>
                    </div>
                </div>
            @endcan
        </div>
    </header>

    <div class="grid gap-6 2xl:grid-cols-[minmax(0,1fr)_280px]">
        {{-- Ticket fields --}}
        <aside class="2xl:order-last" aria-label="Ticketdaten">
            <x-ui.card :padding="false" class="2xl:sticky 2xl:top-6">
                <div class="grid grid-cols-2 gap-x-4 gap-y-3 p-4 sm:grid-cols-3 2xl:grid-cols-1">
                    <x-ui.select label="Status" :options="$statusOptions" :selected="$ticket->status->value" wire:change="setStatus($event.target.value)" :disabled="! $canUpdate" />
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
                            <x-ui.badge><x-heroicon-m-envelope class="size-3" aria-hidden="true" /> {{ $message->type->label() }}</x-ui.badge>
                        @endif
                    </header>

                    <div class="message-body">{!! $message->body !!}</div>

                    @if ($message->attachments->isNotEmpty())
                        <ul class="mt-4 flex flex-wrap gap-2" aria-label="Anhänge">
                            @foreach ($message->attachments as $attachment)
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

            {{ $composer ?? '' }}
        </div>
    </div>
</div>
