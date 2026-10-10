{{-- Rich text editor (Trix) bound via wire:model; the HTML is sanitized on save. --}}
@props([
    'label' => null,
    'placeholder' => '',
    'id' => null,
])

@php
    $model = $attributes->wire('model')->value();
    $id ??= 'editor-'.\Illuminate\Support\Str::slug($model ?: \Illuminate\Support\Str::random(6));
    $error = $model ? ($errors ?? null)?->first($model) : null;
@endphp

<x-ui.field :$id :$label :$error :class="$attributes->get('class')">
    <div wire:ignore x-data="richEditor($wire.entangle(@js($model)))">
        <input type="hidden" id="{{ $id }}-input">
        <trix-editor
            x-ref="editor"
            id="{{ $id }}"
            input="{{ $id }}-input"
            placeholder="{{ $placeholder }}"
            aria-label="{{ $label ?: ($placeholder ?: 'Text') }}"
            @if ($error) aria-invalid="true" aria-describedby="{{ $id }}-error" @endif
            class="message-body"
        ></trix-editor>
    </div>
</x-ui.field>
