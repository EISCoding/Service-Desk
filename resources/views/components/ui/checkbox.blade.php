@props([
    'label',
    'description' => null,
    'id' => null,
])

@php
    $id ??= 'cb-'.\Illuminate\Support\Str::random(8);
@endphp

<label for="{{ $id }}" {{ $attributes->only('class')->class('flex cursor-pointer items-start gap-3') }}>
    <input
        id="{{ $id }}"
        type="checkbox"
        {{ $attributes->except('class')->class('mt-0.5 size-4 shrink-0 cursor-pointer rounded border-separator accent-(--accent)') }}
    >
    <span class="text-sm leading-5">
        <span class="font-medium text-ink">{{ $label }}</span>
        @if ($description)
            <span class="block text-[13px] text-ink-muted">{{ $description }}</span>
        @endif
    </span>
</label>
