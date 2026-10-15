@php($levels = ['emergency' => 'red', 'alert' => 'red', 'critical' => 'red', 'error' => 'red', 'warning' => 'orange', 'notice' => 'blue', 'info' => 'blue', 'debug' => 'gray'])

<div class="max-w-6xl">
    <x-ui.page-header title="Protokolle" description="Fehler, Mails, Änderungen und Hintergrund-Jobs auf einen Blick." />

    <x-ui.segmented model="tab" :current="$tab" :options="\App\Livewire\Intern\Admin\Logs\Index::TABS" label="Protokoll" class="mb-5" />

    @if ($tab === 'system')
        {{-- Laravel log --}}
        <div class="mb-4 flex flex-wrap items-center gap-3">
            @if ($this->files)
                <x-ui.select wire:model.live="file" aria-label="Logdatei" class="w-56" :options="array_combine($this->files, $this->files)" />
            @endif
            <x-ui.segmented model="level" :current="$level" :options="['errors' => 'Fehler', 'warnings' => 'Warnungen', 'all' => 'Alle']" label="Stufe" />
            <x-ui.input wire:model.live.debounce.300ms="search" type="search" placeholder="Text suchen …" aria-label="Log durchsuchen" class="w-full sm:ml-auto sm:w-64" />
            @if ($this->files)
                <x-ui.button variant="secondary" icon="arrow-down-tray" :href="route('intern.admin.logs.download', $file ?: $this->files[0])">Herunterladen</x-ui.button>
            @endif
        </div>

        <x-ui.card :padding="false">
            @forelse ($this->entries as $index => $entry)
                <details wire:key="entry-{{ $index }}-{{ $entry['time']->timestamp }}" class="group border-b border-hairline last:border-0">
                    <summary class="flex cursor-pointer list-none items-start gap-3 px-5 py-3 hover:bg-ink/[0.02]">
                        <x-ui.badge :color="$levels[$entry['level']] ?? 'gray'" class="mt-0.5 w-20 shrink-0 justify-center">{{ ucfirst($entry['level']) }}</x-ui.badge>
                        <span class="min-w-0 flex-1">
                            <span class="line-clamp-2 text-sm [overflow-wrap:anywhere]">{{ $entry['message'] }}</span>
                            <time class="text-[12px] text-ink-muted" datetime="{{ $entry['time']->toIso8601String() }}">{{ $entry['time']->isoFormat('D. MMM YYYY, HH:mm:ss') }}</time>
                        </span>
                        @if ($entry['details'] !== '')
                            <x-heroicon-m-chevron-down class="mt-1 size-4 shrink-0 text-ink-faint transition group-open:rotate-180" aria-hidden="true" />
                        @endif
                    </summary>
                    @if ($entry['details'] !== '')
                        <pre class="mx-5 mb-4 max-h-96 overflow-auto rounded-xl bg-surface-muted p-3 font-mono text-[12px] leading-relaxed whitespace-pre-wrap text-ink-muted">{{ $entry['details'] }}</pre>
                    @endif
                </details>
            @empty
                <x-ui.empty-state icon="check-circle" title="Keine Einträge" :description="$level === 'all' ? 'Das Log ist leer.' : 'Keine Fehler oder Warnungen in dieser Datei.'" />
            @endforelse
        </x-ui.card>
        <p class="mt-2 text-[12px] text-ink-muted">Es werden die neuesten 200 passenden Einträge der Datei angezeigt.</p>

    @elseif ($tab === 'mail')
        {{-- Mailboxes: last fetch and errors --}}
        <div class="mb-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            @forelse ($this->mailboxes as $mailbox)
                <div wire:key="mailbox-{{ $mailbox->id }}" class="rounded-card bg-surface p-4 shadow-card">
                    <p class="flex items-center gap-2 text-sm font-semibold">
                        <span @class(['size-2 rounded-full', 'bg-red-500' => $mailbox->last_error, 'bg-green-500' => ! $mailbox->last_error]) aria-hidden="true"></span>
                        {{ $mailbox->name }}
                    </p>
                    <p class="text-[12px] text-ink-muted">{{ $mailbox->email }}</p>
                    <p class="mt-2 text-[12px] text-ink-muted">Zuletzt abgerufen: <span class="text-ink">{{ $mailbox->last_fetched_at?->diffForHumans() ?? 'noch nie' }}</span></p>
                    @if ($mailbox->last_error)
                        <p class="mt-1 text-[12px] text-red-700 dark:text-red-400 [overflow-wrap:anywhere]">{{ \Illuminate\Support\Str::limit($mailbox->last_error, 200) }}</p>
                    @endif
                </div>
            @empty
                <p class="text-sm text-ink-muted">Noch kein Postfach eingerichtet.</p>
            @endforelse
        </div>

        <x-ui.segmented model="mailFilter" :current="$mailFilter" :options="['all' => 'Alle', 'in' => 'Eingang', 'out' => 'Ausgang', 'failed' => 'Fehlgeschlagen']" label="Mails" class="mb-4" />

        <x-ui.card :padding="false">
            @if ($this->mails->isEmpty())
                <x-ui.empty-state icon="envelope" title="Keine Mails" />
            @else
                <ul class="divide-y divide-hairline">
                    @foreach ($this->mails as $mail)
                        @php($out = $mail->type === \App\Enums\MessageType::MailOut)
                        <li wire:key="mail-{{ $mail->id }}" class="flex items-start gap-3 px-5 py-3">
                            <span @class(['mt-0.5 grid size-7 shrink-0 place-items-center rounded-full', 'bg-accent/10 text-accent' => $out, 'bg-green-500/10 text-green-700 dark:text-green-400' => ! $out])>
                                <x-dynamic-component :component="$out ? 'heroicon-m-arrow-up-right' : 'heroicon-m-arrow-down-left'" class="size-4" aria-hidden="true" />
                                <span class="sr-only">{{ $out ? 'Ausgehend' : 'Eingehend' }}</span>
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-medium">{{ $mail->subject ?: $mail->ticket?->subject }}</p>
                                <p class="truncate text-[12px] text-ink-muted">
                                    {{ $out ? 'An '.collect($mail->to)->pluck('email')->join(', ') : 'Von '.($mail->from_name ? "{$mail->from_name} <{$mail->from_email}>" : $mail->from_email) }}
                                    @if ($mail->ticket)
                                        · <a href="{{ route('intern.tickets.show', $mail->ticket) }}" wire:navigate class="tabular-nums text-accent underline decoration-accent/40 underline-offset-2 hover:decoration-accent">{{ $mail->ticket->reference() }}</a>
                                    @endif
                                    · {{ $mail->created_at->isoFormat('D. MMM, HH:mm') }}
                                </p>
                                @if ($mail->mail_error)
                                    <p class="mt-1 text-[12px] text-red-700 dark:text-red-400 [overflow-wrap:anywhere]">{{ \Illuminate\Support\Str::limit($mail->mail_error, 300) }}</p>
                                @endif
                            </div>
                            @if ($out)
                                @switch($mail->mail_status)
                                    @case('sent') <x-ui.badge color="green" dot class="shrink-0">Gesendet</x-ui.badge> @break
                                    @case('failed') <x-ui.badge color="red" dot class="shrink-0">Fehlgeschlagen</x-ui.badge> @break
                                    @default <x-ui.badge color="yellow" dot class="shrink-0">Wartet</x-ui.badge>
                                @endswitch
                            @else
                                <x-ui.badge class="shrink-0">{{ $mail->is_automatic ? 'Automatisch' : 'Empfangen' }}</x-ui.badge>
                            @endif
                        </li>
                    @endforeach
                </ul>
                @if ($this->mails->hasPages())
                    <div class="border-t border-hairline px-5 py-3">{{ $this->mails->links() }}</div>
                @endif
            @endif
        </x-ui.card>
        <p class="mt-2 text-[12px] text-ink-muted">Fehlgeschlagene System-Mails (Einladungen, Passwort-Links) erscheinen unter „Hintergrund-Jobs“.</p>

    @elseif ($tab === 'activity')
        <x-ui.select wire:model.live="subject" aria-label="Bereich" placeholder="Alle Bereiche" class="mb-4 w-56" :options="\App\Livewire\Intern\Admin\Logs\Index::SUBJECTS" />

        <x-ui.card :padding="false">
            @if ($this->activities->isEmpty())
                <x-ui.empty-state icon="clock" title="Noch keine Aktivität" />
            @else
                <ul class="divide-y divide-hairline">
                    @foreach ($this->activities as $activity)
                        @php([$label, $url] = \App\Livewire\Intern\Admin\Logs\Index::subjectLink($activity))
                        <li wire:key="activity-{{ $activity->id }}" class="px-5 py-3">
                            <p class="text-sm">
                                <span class="font-medium">{{ \App\Support\ActivityPresenter::causer($activity) }}</span>
                                {{ \App\Support\ActivityPresenter::describe($activity) }}
                            </p>
                            <p class="mt-0.5 truncate text-[12px] text-ink-muted">
                                @if ($url)
                                    <a href="{{ $url }}" wire:navigate class="text-accent underline decoration-accent/40 underline-offset-2 hover:decoration-accent">{{ $label }}</a>
                                @else
                                    {{ $label }}
                                @endif
                                · <time datetime="{{ $activity->created_at->toIso8601String() }}">{{ $activity->created_at->isoFormat('D. MMM YYYY, HH:mm') }}</time>
                            </p>
                            @if ($rows = \App\Support\ActivityPresenter::changes($activity))
                                <dl class="mt-2 space-y-1 rounded-lg bg-surface-muted/70 px-3 py-2 text-[13px]">
                                    @foreach ($rows as [$field, $old, $new])
                                        <div class="flex flex-wrap gap-x-2">
                                            <dt class="font-medium">{{ $field }}:</dt>
                                            <dd class="text-ink-muted"><span class="line-through decoration-ink-faint">{{ $old }}</span> → <span class="text-ink">{{ $new }}</span></dd>
                                        </div>
                                    @endforeach
                                </dl>
                            @endif
                        </li>
                    @endforeach
                </ul>
                @if ($this->activities->hasPages())
                    <div class="border-t border-hairline px-5 py-3">{{ $this->activities->links() }}</div>
                @endif
            @endif
        </x-ui.card>

    @else
        {{-- Queue and scheduler health --}}
        @php($queue = $this->queue)
        <div class="mb-5 grid gap-3 sm:grid-cols-3">
            <div class="rounded-card bg-surface p-4 shadow-card">
                <p class="text-[13px] text-ink-muted">Planer (Cron)</p>
                <p @class(['mt-1 flex items-center gap-2 text-sm font-semibold', 'text-red-700 dark:text-red-400' => ! $queue['healthy']])>
                    <span @class(['size-2 rounded-full', 'bg-green-500' => $queue['healthy'], 'bg-red-500' => ! $queue['healthy']]) aria-hidden="true"></span>
                    {{ $queue['heartbeat'] ? 'Zuletzt '.$queue['heartbeat']->diffForHumans() : 'Noch nie gelaufen' }}
                </p>
                @unless ($queue['healthy'])
                    <p class="mt-1 text-[12px] text-ink-muted">Läuft die Plesk-Aufgabe <code class="font-mono">schedule:run</code> jede Minute?</p>
                @endunless
            </div>
            <div class="rounded-card bg-surface p-4 shadow-card">
                <p class="text-[13px] text-ink-muted">Wartende Jobs</p>
                <p class="mt-1 text-[22px] leading-none font-bold tabular-nums">{{ $queue['pending'] }}</p>
                @if ($queue['oldest'])
                    <p class="mt-1 text-[12px] text-ink-muted">ältester {{ $queue['oldest']->diffForHumans() }}</p>
                @endif
            </div>
            <div class="rounded-card bg-surface p-4 shadow-card">
                <p class="text-[13px] text-ink-muted">Fehlgeschlagen</p>
                <p @class(['mt-1 text-[22px] leading-none font-bold tabular-nums', 'text-red-700 dark:text-red-400' => $this->failedJobs->total() > 0])>{{ $this->failedJobs->total() }}</p>
            </div>
        </div>

        <x-ui.card :padding="false" title="Fehlgeschlagene Jobs">
            @if ($this->failedJobs->total() > 0)
                <x-slot:actions>
                    <x-ui.button variant="secondary" size="sm" icon="arrow-path" wire:click="retryAllJobs">Alle erneut</x-ui.button>
                    <x-ui.button variant="ghost" size="sm" wire:click="flushJobs" wire:confirm="Alle fehlgeschlagenen Jobs löschen?">Alle löschen</x-ui.button>
                </x-slot:actions>
            @endif
            @if ($this->failedJobs->isEmpty())
                <x-ui.empty-state icon="check-circle" title="Keine fehlgeschlagenen Jobs" />
            @else
                <ul class="mt-2 divide-y divide-hairline border-t border-hairline">
                    @foreach ($this->failedJobs as $job)
                        <li wire:key="job-{{ $job->uuid }}" class="px-5 py-3">
                            <div class="flex flex-wrap items-start gap-3">
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-medium">{{ \App\Livewire\Intern\Admin\Logs\Index::jobName($job) }}</p>
                                    <p class="text-[12px] text-ink-muted">Warteschlange „{{ $job->queue }}“ · {{ \Illuminate\Support\Carbon::parse($job->failed_at)->isoFormat('D. MMM YYYY, HH:mm') }}</p>
                                    <p class="mt-1 text-[12px] text-red-700 dark:text-red-400 [overflow-wrap:anywhere]">{{ \Illuminate\Support\Str::limit(strtok($job->exception, "\n"), 300) }}</p>
                                </div>
                                <div class="flex shrink-0 gap-1">
                                    <x-ui.button variant="secondary" size="sm" wire:click="retryJob('{{ $job->uuid }}')">Erneut</x-ui.button>
                                    <x-ui.button variant="ghost" size="sm" wire:click="forgetJob('{{ $job->uuid }}')">Löschen</x-ui.button>
                                </div>
                            </div>
                            <details class="mt-2">
                                <summary class="cursor-pointer text-[12px] font-medium text-ink-muted hover:text-ink">Details</summary>
                                <pre class="mt-2 max-h-80 overflow-auto rounded-xl bg-surface-muted p-3 font-mono text-[12px] leading-relaxed whitespace-pre-wrap text-ink-muted">{{ \Illuminate\Support\Str::limit($job->exception, 20000) }}</pre>
                            </details>
                        </li>
                    @endforeach
                </ul>
                @if ($this->failedJobs->hasPages())
                    <div class="border-t border-hairline px-5 py-3">{{ $this->failedJobs->links() }}</div>
                @endif
            @endif
        </x-ui.card>
    @endif
</div>
