@props([
    'label' => null,
    'name' => null,
    'id' => null,
    'type' => 'text',
    'hint' => null,
])

@php
    $name ??= $attributes->wire('model')->value() ?: null;
    $id ??= 'f-'.($name ? \Illuminate\Support\Str::slug($name) : \Illuminate\Support\Str::random(8));
    $error = $name ? ($errors ?? null)?->first($name) : null;
@endphp

<x-ui.field :$id :$label :$hint :$error :required="(bool) $attributes->get('required')" :class="$attributes->get('class')">
    <input
        id="{{ $id }}"
        type="{{ $type }}"
        @if ($name) name="{{ $name }}" @endif
        @if ($error) aria-invalid="true" aria-describedby="{{ $id }}-error" @elseif ($hint) aria-describedby="{{ $id }}-hint" @endif
        {{ $attributes->except('class')->class([
            'block h-9 w-full rounded-[10px] bg-surface px-3 text-sm text-ink shadow-xs placeholder:text-ink-faint',
            'ring-1 ring-inset focus:outline-none focus:ring-2 disabled:opacity-60',
            'ring-separator focus:ring-accent' => ! $error,
            'ring-red-500 focus:ring-red-500' => $error,
        ]) }}
    >
</x-ui.field>
