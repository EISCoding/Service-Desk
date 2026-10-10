<div>
    <x-ui.page-header title="Postfächer" description="E-Mail-Postfächer, aus denen Tickets entstehen und über die geantwortet wird.">
        <x-slot:actions>
            @can('create', \App\Models\Mailbox::class)
                <x-ui.button icon="plus" wire:click="create">Neues Postfach</x-ui.button>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.card :padding="false">
        @if ($this->mailboxes->isEmpty())
            <x-ui.empty-state icon="inbox" title="Noch keine Postfächer" description="Lege ein Postfach an, damit eingehende Mails zu Tickets werden.">
                <x-ui.button variant="secondary" size="sm" icon="plus" wire:click="create">Neues Postfach</x-ui.button>
            </x-ui.empty-state>
        @else
            <x-ui.table>
                <x-slot:head>
                    <x-ui.table.heading>Postfach</x-ui.table.heading>
                    <x-ui.table.heading class="hidden md:table-cell">Standardgruppe</x-ui.table.heading>
                    <x-ui.table.heading>Status</x-ui.table.heading>
                    <x-ui.table.heading><span class="sr-only">Aktionen</span></x-ui.table.heading>
                </x-slot:head>
                @foreach ($this->mailboxes as $mailbox)
                    <x-ui.table.row wire:key="mailbox-{{ $mailbox->id }}" @class(['opacity-60' => ! $mailbox->is_active])>
                        <x-ui.table.cell>
                            <p class="font-medium">{{ $mailbox->name }} @if ($mailbox->is_default)<x-ui.badge color="blue" class="ml-1">Standard</x-ui.badge>@endif</p>
                            <p class="text-[13px] text-ink-muted">{{ $mailbox->email }}</p>
                        </x-ui.table.cell>
                        <x-ui.table.cell class="hidden text-ink-muted md:table-cell">{{ $mailbox->defaultGroup?->name ?? 'Ohne Gruppe' }}</x-ui.table.cell>
                        <x-ui.table.cell>
                            @if (! $mailbox->is_active)
                                <x-ui.badge dot>Inaktiv</x-ui.badge>
                            @elseif ($mailbox->last_error)
                                <x-ui.badge color="red" dot :title="$mailbox->last_error">Fehler beim Abruf</x-ui.badge>
                            @elseif ($mailbox->last_fetched_at)
                                <x-ui.badge color="green" dot>Abgerufen {{ $mailbox->last_fetched_at->diffForHumans() }}</x-ui.badge>
                            @else
                                <x-ui.badge color="yellow" dot>Noch nicht abgerufen</x-ui.badge>
                            @endif
                            @if ($mailbox->last_error)
                                <p class="mt-1 max-w-xs truncate text-[12px] text-red-600 dark:text-red-400" title="{{ $mailbox->last_error }}">{{ $mailbox->last_error }}</p>
                            @endif
                        </x-ui.table.cell>
                        <x-ui.table.cell class="text-right whitespace-nowrap">
                            @can('update', $mailbox)
                                <x-ui.button variant="ghost" size="sm" wire:click="edit({{ $mailbox->id }})">Bearbeiten</x-ui.button>
                            @endcan
                            @can('delete', $mailbox)
                                <x-ui.button variant="ghost" size="sm" class="text-red-600 dark:text-red-400" wire:click="delete({{ $mailbox->id }})" wire:confirm="Postfach {{ $mailbox->email }} löschen?">Löschen</x-ui.button>
                            @endcan
                        </x-ui.table.cell>
                    </x-ui.table.row>
                @endforeach
            </x-ui.table>
        @endif
    </x-ui.card>

    <x-ui.modal name="mailbox-form" wire:model="showForm" max-width="2xl" :title="$editingId ? 'Postfach bearbeiten' : 'Neues Postfach'">
        <form wire:submit="save" id="mailbox-form" class="space-y-6">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.input label="Name (Absendername)" wire:model="name" placeholder="Support Musterfirma" required />
                <x-ui.input label="E-Mail-Adresse" wire:model.blur="email" type="email" required />
                <x-ui.select label="Standardgruppe für neue Tickets" wire:model="default_group_id" placeholder="Ohne Gruppe" :options="$this->groupOptions" />
                <div class="space-y-2 pt-6">
                    <x-ui.checkbox label="Aktiv" description="Mails werden jede Minute abgerufen." wire:model="is_active" />
                    <x-ui.checkbox label="Standardpostfach" description="Für intern und im Portal angelegte Tickets." wire:model="is_default" />
                </div>
            </div>

            @foreach (['imap' => 'Posteingang (IMAP)', 'smtp' => 'Postausgang (SMTP)'] as $protocol => $heading)
                <fieldset class="space-y-4 rounded-xl bg-surface-muted/60 p-4">
                    <div class="flex items-center justify-between gap-3">
                        <legend class="text-sm font-semibold">{{ $heading }}</legend>
                        <x-ui.button variant="secondary" size="sm" icon="signal" wire:click="test{{ ucfirst($protocol) }}">
                            <span wire:loading.remove wire:target="test{{ ucfirst($protocol) }}">Verbindung testen</span>
                            <span wire:loading wire:target="test{{ ucfirst($protocol) }}">Teste …</span>
                        </x-ui.button>
                    </div>
                    <div class="grid gap-4 sm:grid-cols-6">
                        <x-ui.input label="Server" wire:model="{{ $protocol }}_host" class="sm:col-span-3" required />
                        <x-ui.select label="Verschlüsselung" wire:model.live="{{ $protocol }}_encryption" :options="$encryptions" class="sm:col-span-2" />
                        <x-ui.input label="Port" wire:model="{{ $protocol }}_port" type="number" class="sm:col-span-1" required />
                        <x-ui.input label="Benutzer" wire:model="{{ $protocol }}_username" class="sm:col-span-3" autocomplete="off" required />
                        <x-ui.input label="Passwort" wire:model="{{ $protocol }}_password" type="password" class="sm:col-span-3" autocomplete="new-password" :placeholder="$editingId ? 'unverändert' : ''" />
                        @if ($protocol === 'imap')
                            <x-ui.input label="Ordner" wire:model="imap_folder" class="sm:col-span-2" required />
                            <x-ui.select label="Verarbeitete Mails" wire:model.live="processed_action" :options="['mark_read' => 'Als gelesen markieren', 'move' => 'In Ordner verschieben']" class="sm:col-span-2" />
                            @if ($processed_action === 'move')
                                <x-ui.input label="Zielordner" wire:model="processed_folder" placeholder="INBOX.Erledigt" class="sm:col-span-2" />
                            @endif
                        @endif
                    </div>
                    @if (isset($testResults[$protocol]))
                        <x-ui.alert :type="$testResults[$protocol]['ok'] ? 'success' : 'error'">{{ $testResults[$protocol]['message'] }}</x-ui.alert>
                    @endif
                </fieldset>
            @endforeach
        </form>

        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="show = false">Abbrechen</x-ui.button>
            <x-ui.button type="submit" form="mailbox-form">Speichern</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>
</div>
