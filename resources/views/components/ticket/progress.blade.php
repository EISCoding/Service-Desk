{{-- Portal progress of a ticket: Eingegangen → In Bearbeitung → Erledigt --}}
@props(['status'])

@php
    $step = $status->portalStep();
    $waiting = $status === \App\Enums\TicketStatus::WaitingCustomer;
@endphp

<div {{ $attributes }}>
    <ol class="flex items-start" aria-label="Fortschritt">
        @foreach ([1 => 'Eingegangen', 2 => 'In Bearbeitung', 3 => 'Erledigt'] as $number => $label)
            @php($done = $number < $step || $step === 3)
            <li class="relative flex flex-1 flex-col items-center text-center" @if ($number === $step) aria-current="step" @endif>
                @if ($number > 1)
                    <span @class(['absolute top-[11px] right-1/2 left-[-50%] h-0.5 rounded-full', 'bg-accent' => $number <= $step, 'bg-ink/10' => $number > $step]) aria-hidden="true"></span>
                @endif
                <span @class([
                    'relative grid size-6 place-items-center rounded-full',
                    'bg-accent-fill text-on-accent' => $done,
                    'bg-accent-fill ring-4 ring-accent/20' => ! $done && $number === $step,
                    'bg-surface ring-2 ring-ink/15 ring-inset' => $number > $step,
                ])>
                    @if ($done)
                        <x-heroicon-m-check class="size-3.5" aria-hidden="true" />
                    @elseif ($number === $step)
                        <span class="size-2 rounded-full bg-on-accent"></span>
                    @endif
                </span>
                <span @class(['mt-1.5 text-[12px] font-medium', 'text-ink' => $number <= $step, 'text-ink-muted' => $number > $step])>
                    {{ $label }}
                    @if ($done)<span class="sr-only">(abgeschlossen)</span>@endif
                </span>
            </li>
        @endforeach
    </ol>
    <p @class(['mt-3 text-center text-[13px]', 'font-medium text-yellow-800 dark:text-yellow-300' => $waiting, 'text-ink-muted' => ! $waiting])>{{ $status->portalHint() }}</p>
</div>
