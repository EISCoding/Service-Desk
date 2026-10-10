@props([
    'label' => null,
    'name' => null,
    'id' => null,
    'hint' => null,
    'options' => [],        // [value => label]; alternatively pass <option>s in the slot
    'placeholder' => null,
])

@php
    $name ??= $attributes->wire('model')->value() ?: null;
    $id ??= 'f-'.($name ? \Illuminate\Support\Str::slug($name) : \Illuminate\Support\Str::random(8));
    $error = $name ? ($errors ?? null)?->first($name) : null;
@endphp

<x-ui.field :$id :$label :$hint :$error :required="$attributes->has('required')" :class="$attributes->get('class')">
    <div class="relative">
        <select
            id="{{ $id }}"
            @if ($name) name="{{ $name }}" @endif
            @if ($error) aria-invalid="true" aria-describedby="{{ $id }}-error" @elseif ($hint) aria-describedby="{{ $id }}-hint" @endif
            {{ $attributes->except('class')->class([
                'block h-9 w-full appearance-none rounded-[10px] bg-surface pr-9 pl-3 text-sm text-ink shadow-xs',
                'ring-1 ring-inset focus:outline-none focus:ring-2 disabled:opacity-60',
                'ring-separator focus:ring-accent' => ! $error,
                'ring-red-500 focus:ring-red-500' => $error,
            ]) }}
        >
            @if ($placeholder)
                <option value="">{{ $placeholder }}</option>
            @endif
            @foreach ($options as $value => $optionLabel)
                <option value="{{ $value }}">{{ $optionLabel }}</option>
            @endforeach
            {{ $slot }}
        </select>
        <x-heroicon-m-chevron-up-down class="pointer-events-none absolute top-1/2 right-2.5 size-4 -translate-y-1/2 text-ink-faint" aria-hidden="true" />
    </div>
</x-ui.field>
