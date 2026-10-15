{{--
    One message of the conversation as a card.
    mine:   tinted (team side for staff, own messages for customers)
    portal: customer view (portal attachment URLs, no internal details)
--}}
@props([
    'message',
    'mine' => false,
    'portal' => false,
    'canRetry' => false,
    'you' => null, // portal: the logged-in contact, labelled "Sie"
])

@php
    $internal = $message->isInternal();
    $isMail = in_array($message->type, [\App\Enums\MessageType::MailIn, \App\Enums\MessageType::MailOut], true);
    $template = $portal ? null : \App\Enums\SystemTemplate::tryFrom((string) $message->template_key);
    $name = $message->authorName();
    [$mainText, $quotedText] = $message->displayParts(portal: $portal);
@endphp

<article {{ $attributes->class([
    'rounded-xl px-4 py-3.5 sm:px-5',
    'bg-amber-400/12 ring-1 ring-amber-500/25 dark:bg-amber-400/10' => $internal,
    'bg-accent/[0.045] ring-1 ring-accent/15 dark:bg-accent/10' => ! $internal && $mine,
    'bg-surface shadow-card' => ! $internal && ! $mine,
]) }}>
    <header class="flex items-start gap-3">
        <x-ui.avatar :name="$name" size="md" />
        <div class="min-w-0 flex-1">
            <p class="flex flex-wrap items-center gap-x-2 gap-y-1 text-[13px] leading-5 text-ink-muted">
                <span class="font-semibold text-ink">{{ $name }}</span>
                @if ($you && $message->author?->is($you))
                    <span>Sie</span>
                @elseif (! $portal && $message->author instanceof \App\Models\Contact)
                    <span>Kunde</span>
                @elseif ($portal && ! $mine)
                    <span>{{ config('app.name') }}</span>
                @endif

                @if ($internal)
                    <x-ui.badge color="yellow"><x-bx-lock-alt class="size-3" aria-hidden="true" /> Interne Notiz</x-ui.badge>
                @else
                    @if (! $portal && $message->is_automatic)
                        <x-ui.badge color="gray"><x-bx-chip class="size-3" aria-hidden="true" /> Automatisch</x-ui.badge>
                    @endif
                    @if ($template)
                        <x-ui.badge color="gray"><x-bx-file class="size-3" aria-hidden="true" /> {{ $template->label() }}</x-ui.badge>
                    @endif
                @endif
            </p>
            <p class="text-[12px] leading-5 text-ink-muted">
                @if ($internal)
                    Nur für das Team sichtbar
                @elseif (! $portal && $message->type === \App\Enums\MessageType::MailOut)
                    An {{ collect($message->to)->pluck('email')->join(', ') }}@if ($message->cc) · CC {{ collect($message->cc)->pluck('email')->join(', ') }}@endif
                    @switch($message->mail_status)
                        @case('queued') · <span class="text-yellow-700 dark:text-yellow-300">wird gesendet …</span> @break
                        @case('sent') · gesendet {{ $message->sent_at?->isoFormat('D. MMM, HH:mm') }} @break
                    @endswitch
                @elseif ($isMail)
                    <span class="inline-flex items-center gap-1"><x-bx-envelope class="size-3.5" aria-hidden="true" /> {{ $portal ? 'E-Mail' : $message->type->label() }}@if (! $portal && $message->from_email) · {{ $message->from_email }}@endif</span>
                @else
                    {{ $portal ? 'Nachricht im Portal' : ($message->author instanceof \App\Models\Contact ? 'Über das Kundenportal' : 'Nachricht') }}
                @endif
            </p>
        </div>
        <time class="shrink-0 text-[12px] leading-5 text-ink-muted" datetime="{{ $message->created_at->toIso8601String() }}" title="{{ $message->created_at->isoFormat('D. MMMM YYYY, HH:mm') }}">{{ $message->created_at->format('H:i') }}</time>
    </header>

    <div class="mt-2.5 sm:pl-11">
        @if (! $portal && $message->type === \App\Enums\MessageType::MailOut && $message->mailFailed())
            <x-ui.alert type="error" class="mb-3">
                Versand fehlgeschlagen: {{ $message->mail_error }}
                @if ($canRetry)
                    <button type="button" wire:click="retryMail({{ $message->id }})" class="ml-1 font-medium underline">Erneut senden</button>
                @endif
            </x-ui.alert>
        @endif

        <div class="message-body">{!! $mainText !!}</div>

        @if ($quotedText)
            <details class="group mt-3">
                <summary class="inline-flex cursor-pointer list-none items-center gap-1 rounded-full bg-ink/[0.05] px-2.5 py-0.5 text-[12px] font-medium text-ink-muted hover:text-ink">
                    <x-bx-dots-horizontal-rounded class="size-4" aria-hidden="true" />
                    <span class="group-open:hidden">Zitierten Verlauf anzeigen</span>
                    <span class="hidden group-open:inline">Zitierten Verlauf ausblenden</span>
                </summary>
                <div class="message-body mt-3 border-l-2 border-hairline pl-3 text-ink-muted">{!! $quotedText !!}</div>
            </details>
        @endif

        @if ($message->listedAttachments()->isNotEmpty())
            <ul class="mt-3 flex flex-wrap gap-2" aria-label="Anhänge">
                @foreach ($message->listedAttachments() as $attachment)
                    <li>
                        <a href="{{ $attachment->url(inline: $attachment->canBeShownInline(), portal: $portal) }}" target="_blank" class="flex max-w-60 items-center gap-2 rounded-xl bg-surface px-3 py-2 text-[13px] shadow-card hover:ring-2 hover:ring-accent/40">
                            @if ($attachment->isImage())
                                <img src="{{ $attachment->url(inline: true, portal: $portal) }}" alt="" class="size-8 shrink-0 rounded-md object-cover" loading="lazy">
                            @else
                                <span class="grid size-8 shrink-0 place-items-center rounded-md bg-accent/10 text-accent"><x-bx-paperclip class="size-4" aria-hidden="true" /></span>
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
    </div>
</article>
