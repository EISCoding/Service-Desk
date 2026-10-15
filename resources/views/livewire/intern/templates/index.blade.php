@php($canManage = auth()->user()->can('create', \App\Models\MailTemplate::class))

<div class="max-w-4xl space-y-8">
    <x-ui.page-header title="Vorlagen" description="Automatische E-Mails und Textbausteine für Antworten." class="mb-0!">
        @can(\App\Enums\Permission::AdminSettings->value)
            <x-slot:actions>
                <x-ui.button variant="secondary" size="sm" icon="envelope-open" :href="route('intern.admin.mail-design')" wire:navigate>Mail-Design</x-ui.button>
            </x-slot:actions>
        @endcan
    </x-ui.page-header>

    <section>
        <h2 class="mb-2 text-[11px] font-semibold tracking-wide text-ink-faint uppercase">Systemvorlagen</h2>
        <x-ui.card :padding="false">
            @if ($this->systemTemplates->isEmpty())
                <x-ui.empty-state icon="envelope" title="Keine Systemvorlagen" description="Sie werden beim Deployment angelegt (php artisan db:seed --class=MailTemplateSeeder)." />
            @else
                <ul class="divide-y divide-separator">
                    @foreach ($this->systemTemplates as $template)
                        <li wire:key="system-{{ $template->id }}" class="flex flex-wrap items-center justify-between gap-3 px-5 py-3.5">
                            <div class="min-w-0">
                                <p class="text-sm font-medium">{{ $template->name }}</p>
                                <p class="text-[13px] text-ink-muted">{{ \App\Enums\SystemTemplate::tryFrom((string) $template->key)?->description() ?? $template->subject }}</p>
                            </div>
                            <div class="flex items-center gap-2">
                                @if ($canManage)
                                    <button type="button" role="switch" aria-checked="{{ $template->is_active ? 'true' : 'false' }}" wire:click="toggleActive({{ $template->id }})"
                                        @class(['relative inline-flex h-6 w-10 shrink-0 rounded-full transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent', 'bg-green-500' => $template->is_active, 'bg-ink/20' => ! $template->is_active])>
                                        <span class="sr-only">{{ $template->name }} {{ $template->is_active ? 'deaktivieren' : 'aktivieren' }}</span>
                                        <span @class(['mt-0.5 size-5 rounded-full bg-white shadow transition-transform', 'translate-x-[18px]' => $template->is_active, 'translate-x-0.5' => ! $template->is_active])></span>
                                    </button>
                                    <x-ui.button variant="ghost" size="sm" wire:click="edit({{ $template->id }})">Bearbeiten</x-ui.button>
                                @else
                                    <x-ui.badge :color="$template->is_active ? 'green' : 'gray'" dot>{{ $template->is_active ? 'Aktiv' : 'Aus' }}</x-ui.badge>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-ui.card>
    </section>

    <section>
        <div class="mb-2 flex items-center justify-between">
            <h2 class="text-[11px] font-semibold tracking-wide text-ink-faint uppercase">Textbausteine</h2>
            @if ($canManage)
                <x-ui.button size="sm" icon="plus" wire:click="create">Neuer Textbaustein</x-ui.button>
            @endif
        </div>
        <x-ui.card :padding="false">
            @forelse ($this->snippets as $snippet)
                <div wire:key="snippet-{{ $snippet->id }}" @class(['flex items-start justify-between gap-3 px-5 py-3.5', 'border-t border-separator' => ! $loop->first])>
                    <div class="min-w-0">
                        <p class="text-sm font-medium">{{ $snippet->name }}</p>
                        <p class="line-clamp-2 text-[13px] text-ink-muted">{{ \App\Support\Html::toText($snippet->body) }}</p>
                    </div>
                    @if ($canManage)
                        <div class="flex shrink-0 gap-1">
                            <x-ui.button variant="ghost" size="sm" wire:click="edit({{ $snippet->id }})">Bearbeiten</x-ui.button>
                            <x-ui.button variant="ghost" size="sm" class="text-red-600 dark:text-red-400" wire:click="delete({{ $snippet->id }})" wire:confirm="Textbaustein „{{ $snippet->name }}“ löschen?">Löschen</x-ui.button>
                        </div>
                    @endif
                </div>
            @empty
                <x-ui.empty-state icon="copy-alt" title="Noch keine Textbausteine" description="Textbausteine lassen sich beim Antworten mit einem Klick einfügen." />
            @endforelse
        </x-ui.card>
    </section>

    @if ($canManage)
    <x-ui.modal name="template-form" wire:model="showForm" max-width="3xl" :title="$this->editing?->isSystem() ? 'Systemvorlage bearbeiten' : ($editingId ? 'Textbaustein bearbeiten' : 'Neuer Textbaustein')">
        <form wire:submit="save" id="template-form" class="space-y-4">
            <x-ui.input label="Name" wire:model="name" required />
            @if ($this->editing?->isSystem())
                <x-ui.input label="Betreff" wire:model="subject" required :hint="\App\Enums\SystemTemplate::tryFrom((string) $this->editing->key)?->isTicketMail() === false ? null : 'Die Ticketnummer [#JJJJ-NNNNN] wird automatisch angehängt.'" />
            @endif
            <x-ui.rich-editor label="Text" wire:model="body" toolbar="full" placeholders />
            @include('livewire.intern.templates.placeholders')
            @if ($this->editing?->isSystem())
                <x-ui.checkbox label="Aktiv" description="Nur aktive Systemvorlagen werden verschickt." wire:model="is_active" />
            @endif
        </form>
        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="show = false">Abbrechen</x-ui.button>
            <x-ui.button type="submit" form="template-form">Speichern</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>
    @endif
</div>
