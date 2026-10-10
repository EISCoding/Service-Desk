<details class="rounded-xl bg-surface-muted/60 px-4 py-3 text-[13px]">
    <summary class="cursor-pointer font-medium">Platzhalter</summary>
    <p class="mt-2 text-ink-muted">Werden beim Versand bzw. beim Einfügen ersetzt. Fehlt ein Wert (z. B. kein Termin), bleibt die Stelle leer.</p>
    <dl class="mt-2 grid gap-x-4 gap-y-1 sm:grid-cols-[auto_1fr]">
        @foreach (\App\Support\Placeholders::DESCRIPTIONS as $placeholder => $description)
            <dt><code class="rounded bg-surface px-1.5 py-0.5 font-mono text-[12px] select-all">{{ '{'.$placeholder.'}' }}</code></dt>
            <dd class="text-ink-muted">{{ $description }}</dd>
        @endforeach
    </dl>
</details>
