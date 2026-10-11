{{-- Date separator in the conversation ("Heute", "Gestern", "Mo, 12. Okt.") --}}
@props(['date'])

@php
    $label = match (true) {
        $date->isToday() => 'Heute',
        $date->isYesterday() => 'Gestern',
        $date->isCurrentYear() => $date->isoFormat('dd, D. MMMM'),
        default => $date->isoFormat('D. MMMM YYYY'),
    };
@endphp

<div class="flex items-center gap-3 py-1 text-[12px] font-medium text-ink-muted" role="separator">
    <span class="h-px flex-1 bg-hairline"></span>
    <time datetime="{{ $date->toDateString() }}">{{ $label }}</time>
    <span class="h-px flex-1 bg-hairline"></span>
</div>
