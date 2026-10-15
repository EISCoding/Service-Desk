@php($steps = \App\Livewire\Install\Wizard::STEPS)
@php($index = array_search($step, array_keys($steps), true))

<div>
    <div class="mb-8 text-center">
        <x-brand-mark size="lg" class="mx-auto shadow-md" />
        <h1 class="mt-4 text-[24px] font-semibold tracking-tight">ServiceDesk einrichten</h1>
        <p class="mt-1 text-sm text-ink-muted">In wenigen Schritten startklar – alles im Browser.</p>
    </div>

    {{-- Steps --}}
    <ol class="mb-6 flex items-center justify-center gap-1.5" aria-label="Schritte">
        @foreach ($steps as $key => $label)
            @php($position = array_search($key, array_keys($steps), true))
            <li class="flex items-center gap-1.5" @if ($key === $step) aria-current="step" @endif>
                <span @class([
                    'grid size-6 place-items-center rounded-full text-[11px] font-semibold',
                    'bg-accent-fill text-on-accent' => $position <= $index,
                    'bg-ink/[0.07] text-ink-muted' => $position > $index,
                ])>
                    @if ($position < $index)
                        <x-bx-check class="size-3.5" aria-hidden="true" />
                    @else
                        {{ $position + 1 }}
                    @endif
                </span>
                <span @class(['hidden text-[12px] font-medium sm:inline', 'text-ink' => $key === $step, 'text-ink-muted' => $key !== $step])>{{ $label }}</span>
                @if (! $loop->last)
                    <span class="h-px w-3 bg-separator sm:w-5" aria-hidden="true"></span>
                @endif
            </li>
        @endforeach
    </ol>

    <x-ui.card class="shadow-pop [&>div]:px-6 [&>div]:py-6 sm:[&>div]:px-8">
        @if ($blocked)
            <x-ui.alert type="error">Die Einrichtung läuft bereits in einem anderen Browser. Bitte dort fortfahren oder zwei Stunden warten.</x-ui.alert>

        @elseif ($step === 'welcome')
            <h2 class="text-lg font-semibold">Willkommen</h2>
            <p class="mt-1 text-sm text-ink-muted">Zuerst prüfen wir, ob der Server alles mitbringt.</p>
            <ul class="mt-5 space-y-3">
                @foreach ($this->checks as $check)
                    <li class="flex items-start gap-3">
                        @if ($check['ok'])
                            <x-bxs-check-circle class="size-5 shrink-0 text-green-600 dark:text-green-400" aria-hidden="true" />
                        @elseif ($check['required'])
                            <x-bxs-x-circle class="size-5 shrink-0 text-red-600 dark:text-red-400" aria-hidden="true" />
                        @else
                            <x-bxs-error class="size-5 shrink-0 text-orange-500" aria-hidden="true" />
                        @endif
                        <span class="text-sm">
                            <span class="font-medium">{{ $check['label'] }}</span>
                            <span class="sr-only">{{ $check['ok'] ? 'in Ordnung' : 'nicht erfüllt' }}</span>
                            @unless ($check['ok'])
                                <span class="block text-[13px] text-ink-muted">{{ $check['hint'] }}</span>
                            @endunless
                        </span>
                    </li>
                @endforeach
            </ul>
            <div class="mt-6 flex justify-end">
                @if ($this->canContinue())
                    <x-ui.button wire:click="start">Los geht’s</x-ui.button>
                @else
                    <x-ui.button wire:click="$refresh" variant="secondary" icon="refresh">Erneut prüfen</x-ui.button>
                @endif
            </div>

        @elseif ($step === 'database')
            <h2 class="text-lg font-semibold">Datenbank</h2>
            <p class="mt-1 text-sm text-ink-muted">Lege in Plesk unter „Datenbanken“ eine MariaDB-Datenbank mit Benutzer an und trag die Daten hier ein.</p>
            <form wire:submit="saveDatabase" class="mt-5 space-y-4">
                <x-ui.segmented model="dbDriver" :current="$dbDriver" :options="['mysql' => 'MariaDB / MySQL', 'sqlite' => 'SQLite (Test)']" label="Datenbank-Typ" />
                @if ($dbDriver === 'mysql')
                    <div class="grid gap-4 sm:grid-cols-3">
                        <x-ui.input label="Server" wire:model="dbHost" required class="sm:col-span-2" />
                        <x-ui.input label="Port" wire:model="dbPort" required inputmode="numeric" />
                    </div>
                    <x-ui.input label="Datenbank" wire:model="dbDatabase" required />
                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-ui.input label="Benutzer" wire:model="dbUsername" required autocomplete="off" />
                        <x-ui.input label="Passwort" wire:model="dbPassword" type="password" autocomplete="new-password" />
                    </div>
                @else
                    <p class="text-[13px] text-ink-muted">Die Daten liegen in <code class="font-mono">database/database.sqlite</code>. Für den Betrieb MariaDB empfohlen.</p>
                    @error('dbHost') <p class="text-[13px] text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                @endif
                <div class="flex items-center justify-between gap-3 pt-2">
                    <x-ui.button variant="ghost" wire:click="back('welcome')">Zurück</x-ui.button>
                    <x-ui.button type="submit" wire:loading.attr="disabled" wire:target="saveDatabase">
                        <span wire:loading.remove wire:target="saveDatabase">Verbinden und einrichten</span>
                        <span wire:loading wire:target="saveDatabase">Tabellen werden angelegt …</span>
                    </x-ui.button>
                </div>
            </form>

        @elseif ($step === 'company')
            <h2 class="text-lg font-semibold">Deine Firma</h2>
            <p class="mt-1 text-sm text-ink-muted">Name und Logo erscheinen in der Oberfläche, im Kundenportal und in allen E-Mails.</p>
            <form wire:submit="saveCompany" class="mt-5 space-y-4">
                <x-ui.input label="Firmenname" wire:model="companyName" required autofocus />
                <x-ui.input label="Adresse der Anwendung" wire:model="appUrl" required hint="So ist der ServiceDesk erreichbar, z. B. https://support.firma.de" />
                <x-ui.select label="Akzentfarbe" wire:model="accent" :options="$accents" />
                <div>
                    <p class="mb-1.5 text-[13px] font-medium">Logo <span class="font-normal text-ink-muted">(optional, PNG/JPG/WebP bis 1 MB)</span></p>
                    <div class="flex items-center gap-3">
                        @if ($logo && ! $errors->has('logo'))
                            <img src="{{ $logo->temporaryUrl() }}" alt="" class="size-12 rounded-xl object-contain ring-1 ring-hairline">
                        @endif
                        <label class="inline-flex cursor-pointer items-center gap-1.5 rounded-[10px] bg-surface px-3 py-1.5 text-sm font-medium ring-1 ring-ink/12 ring-inset hover:bg-surface-muted">
                            <x-bx-upload class="size-4" aria-hidden="true" /> Datei wählen
                            <input type="file" wire:model="logo" accept="image/png,image/jpeg,image/webp" class="sr-only">
                        </label>
                    </div>
                    @error('logo') <p class="mt-1 text-[13px] text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                </div>
                <div class="flex items-center justify-between gap-3 pt-2">
                    <x-ui.button variant="ghost" wire:click="back('database')">Datenbank ändern</x-ui.button>
                    <x-ui.button type="submit" wire:loading.attr="disabled" wire:target="logo,saveCompany">Weiter</x-ui.button>
                </div>
            </form>

        @elseif ($step === 'admin')
            <h2 class="text-lg font-semibold">Dein Admin-Konto</h2>
            <p class="mt-1 text-sm text-ink-muted">Damit meldest du dich an und legst weitere Mitarbeiter an.</p>
            <form wire:submit="saveAdmin" class="mt-5 space-y-4">
                <x-ui.input label="Name" wire:model="adminName" required autocomplete="name" />
                <x-ui.input label="E-Mail-Adresse" wire:model="adminEmail" type="email" required autocomplete="email" />
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.input label="Passwort" wire:model="adminPassword" type="password" required autocomplete="new-password" hint="Mindestens 10 Zeichen, Buchstaben und Zahlen" />
                    <x-ui.input label="Passwort wiederholen" wire:model="adminPassword_confirmation" type="password" required autocomplete="new-password" />
                </div>
                <div class="flex items-center justify-between gap-3 pt-2">
                    <x-ui.button variant="ghost" wire:click="back('company')">Zurück</x-ui.button>
                    <x-ui.button type="submit">Konto anlegen</x-ui.button>
                </div>
            </form>

        @elseif ($step === 'mailbox')
            <h2 class="text-lg font-semibold">Support-Postfach</h2>
            <p class="mt-1 text-sm text-ink-muted">Aus eingehenden Mails werden Tickets, Antworten gehen über dieses Postfach raus. Das geht auch später unter Admin → Postfächer.</p>
            <form wire:submit="saveMailbox" class="mt-5 space-y-4">
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.input label="Name" wire:model="mailboxName" required />
                    <x-ui.input label="E-Mail-Adresse" wire:model.blur="mailboxEmail" type="email" required />
                </div>

                <fieldset class="space-y-3 rounded-xl bg-ink/[0.03] p-4">
                    <legend class="sr-only">Posteingang (IMAP)</legend>
                    <p class="text-[13px] font-semibold">Posteingang (IMAP)</p>
                    <div class="grid gap-3 sm:grid-cols-6">
                        <x-ui.input label="Server" wire:model="imapHost" placeholder="imap.firma.de" class="sm:col-span-3" />
                        <x-ui.select label="Verschlüsselung" wire:model.live="imapEncryption" :options="\App\Models\Mailbox::ENCRYPTIONS" class="sm:col-span-2" />
                        <x-ui.input label="Port" wire:model="imapPort" inputmode="numeric" />
                        <x-ui.input label="Benutzer" wire:model="imapUsername" autocomplete="off" class="sm:col-span-3" />
                        <x-ui.input label="Passwort" wire:model="imapPassword" type="password" autocomplete="new-password" class="sm:col-span-3" />
                    </div>
                    <div class="flex items-center gap-3">
                        <x-ui.button variant="secondary" size="sm" wire:click="testImap">Verbindung testen</x-ui.button>
                        @isset($testResults['imap'])
                            <p @class(['text-[13px]', 'text-green-700 dark:text-green-400' => $testResults['imap']['ok'], 'text-red-700 dark:text-red-400' => ! $testResults['imap']['ok']])>{{ $testResults['imap']['message'] }}</p>
                        @endisset
                    </div>
                </fieldset>

                <fieldset class="space-y-3 rounded-xl bg-ink/[0.03] p-4">
                    <legend class="sr-only">Postausgang (SMTP)</legend>
                    <p class="text-[13px] font-semibold">Postausgang (SMTP)</p>
                    <div class="grid gap-3 sm:grid-cols-6">
                        <x-ui.input label="Server" wire:model="smtpHost" placeholder="smtp.firma.de" class="sm:col-span-3" />
                        <x-ui.select label="Verschlüsselung" wire:model.live="smtpEncryption" :options="\App\Models\Mailbox::ENCRYPTIONS" class="sm:col-span-2" />
                        <x-ui.input label="Port" wire:model="smtpPort" inputmode="numeric" />
                        <x-ui.input label="Benutzer" wire:model="smtpUsername" autocomplete="off" class="sm:col-span-3" />
                        <x-ui.input label="Passwort" wire:model="smtpPassword" type="password" autocomplete="new-password" class="sm:col-span-3" />
                    </div>
                    <div class="flex items-center gap-3">
                        <x-ui.button variant="secondary" size="sm" wire:click="testSmtp">Verbindung testen</x-ui.button>
                        @isset($testResults['smtp'])
                            <p @class(['text-[13px]', 'text-green-700 dark:text-green-400' => $testResults['smtp']['ok'], 'text-red-700 dark:text-red-400' => ! $testResults['smtp']['ok']])>{{ $testResults['smtp']['message'] }}</p>
                        @endisset
                    </div>
                </fieldset>

                <div class="flex flex-wrap items-center justify-between gap-3 pt-2">
                    <x-ui.button variant="ghost" wire:click="skipMailbox">Später einrichten</x-ui.button>
                    <x-ui.button type="submit">Postfach speichern</x-ui.button>
                </div>
            </form>

        @else
            <div class="text-center">
                <span class="mx-auto grid size-12 place-items-center rounded-full bg-green-500/15 text-green-700 dark:text-green-400">
                    <x-bx-check class="size-6" aria-hidden="true" />
                </span>
                <h2 class="mt-3 text-lg font-semibold">Fertig eingerichtet</h2>
                <p class="mt-1 text-sm text-ink-muted">Ein letzter Schritt in Plesk, damit Mails und Erinnerungen im Hintergrund laufen:</p>
            </div>
            <ol class="mt-5 list-decimal space-y-2 pl-5 text-sm">
                <li>Plesk → <strong>Geplante Aufgaben</strong> → <strong>Aufgabe hinzufügen</strong></li>
                {{-- Plesk expects the script relative to the subscription (/var/www/vhosts/<domain>/…) --}}
                @php($script = preg_match('#/vhosts/[^/]+/(.+)$#', str_replace('\\', '/', base_path()), $match) ? $match[1].'/artisan' : str_replace('\\', '/', base_path('artisan')))
                <li>Typ <strong>PHP-Skript ausführen</strong>, Skript: <code class="rounded bg-ink/[0.06] px-1 font-mono text-[13px] [overflow-wrap:anywhere]">{{ $script }}</code></li>
                <li>Argumente: <code class="rounded bg-ink/[0.06] px-1 font-mono text-[13px]">schedule:run</code>, Ausführen: <strong>jede Minute</strong> (Cron <code class="font-mono text-[13px]">* * * * *</code>)</li>
            </ol>
            <p class="mt-4 text-[13px] text-ink-muted">Ob der Planer läuft, siehst du später unter Admin → Protokolle → Hintergrund-Jobs.</p>
            <div class="mt-6 flex justify-center">
                <x-ui.button :href="url('/intern/login')" icon="right-arrow-alt">Zur Anmeldung</x-ui.button>
            </div>
        @endif
    </x-ui.card>
</div>
