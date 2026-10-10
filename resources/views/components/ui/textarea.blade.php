@props([
    'label' => null,
    'name' => null,
    'id' => null,
    'hint' => null,
    'rows' => 4,
])

@php
    $name ??= $attributes->wire('model')->value() ?: null;
    $id ??= 'f-'.($name ? \Illuminate\Support\Str::slug($name) : \Illuminate\Support\Str::random(8));
    $error = $name ? ($errors ?? null)?->first($name) : null;
@endphp

<x-ui.field :$id :$label :$hint :$error :required="(bool) $attributes->get('required')" :class="$attributes->get('class')">
    <textarea
        id="{{ $id }}"
        rows="{{ $rows }}"
        @if ($name) name="{{ $name }}" @endif
        @if ($error) aria-invalid="true" aria-describedby="{{ $id }}-error" @elseif ($hint) aria-describedby="{{ $id }}-hint" @endif
        {{ $attributes->except('class')->class([
            'block w-full rounded-[10px] bg-surface px-3 py-2 text-sm text-ink shadow-xs placeholder:text-ink-faint',
            'ring-1 ring-inset focus:outline-none focus:ring-2 disabled:opacity-60',
            'ring-separator focus:ring-accent' => ! $error,
            'ring-red-500 focus:ring-red-500' => $error,
        ]) }}
    >{{ $slot }}</textarea>
</x-ui.field>
