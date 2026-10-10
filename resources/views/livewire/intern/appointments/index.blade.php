<div class="max-w-4xl">
    <x-ui.page-header title="Termine" description="Kommende Vor-Ort-Termine (VOS).">
        <x-slot:actions>
            <x-ui.select wire:model.live="technician" placeholder="Alle Techniker" :options="$this->technicianOptions" aria-label="Techniker" class="w-56" />
        </x-slot:actions>
    </x-ui.page-header>

    @forelse ($this->days as $date => $appointments)
        @php($day = \Illuminate\Support\Carbon::parse($date))
        <section wire:key="day-{{ $date }}" class="mb-6">
            <h2 class="mb-2 text-[13px] font-semibold text-ink-muted">
                {{ $day->isToday() ? 'Heute' : ($day->isTomorrow() ? 'Morgen' : '') }}
                <span @class(['font-normal' => $day->isToday() || $day->isTomorrow()])>{{ $day->isoFormat('dddd, D. MMMM YYYY') }}</span>
            </h2>
            <x-ui.card :padding="false">
                <ul class="divide-y divide-separator">
                    @foreach ($appointments as $appointment)
                        <li wire:key="appointment-{{ $appointment->id }}" class="flex flex-col gap-2 px-5 py-4 sm:flex-row sm:items-start sm:gap-6">
                            <div class="w-28 shrink-0">
                                <p class="text-sm font-semibold tabular-nums">{{ $appointment->starts_at->format('H:i') }}–{{ $appointment->ends_at->format('H:i') }}</p>
                                <p class="text-[12px] text-ink-muted">{{ $appointment->technician?->name ?? 'Kein Techniker' }}</p>
                            </div>
                            <div class="min-w-0 flex-1">
                                <a href="{{ route('intern.tickets.show', $appointment->ticket) }}" wire:navigate class="text-sm font-medium hover:text-accent">
                                    <span class="font-mono text-[12px] text-ink-muted">{{ $appointment->ticket->number }}</span>
                                    {{ $appointment->ticket->subject }}
                                </a>
                                <p class="text-[13px] text-ink-muted">{{ $appointment->ticket->customer?->company ?? 'Ohne Kunde' }}</p>
                                <p class="mt-1 text-[13px] whitespace-pre-line">{{ $appointment->location }}</p>
                                @if ($appointment->note)
                                    <p class="mt-1 text-[13px] text-ink-muted">{{ $appointment->note }}</p>
                                @endif
                            </div>
                            <x-ui.badge :color="$appointment->ticket->status->color()" dot class="self-start">{{ $appointment->ticket->status->label() }}</x-ui.badge>
                        </li>
                    @endforeach
                </ul>
            </x-ui.card>
        </section>
    @empty
        <x-ui.card :padding="false">
            <x-ui.empty-state icon="calendar-days" title="Keine anstehenden Termine" description="VOS-Termine entstehen, wenn ein Ticket auf „VOS“ gesetzt wird." />
        </x-ui.card>
    @endforelse
</div>
