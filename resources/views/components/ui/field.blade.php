{{-- Wrapper with label, hint and validation error. Used by input, select, textarea. --}}
@props([
    'id',
    'label' => null,
    'hint' => null,
    'error' => null,
    'required' => false,
])

<div {{ $attributes->class('space-y-1.5') }}>
    @if ($label)
        <label for="{{ $id }}" class="block text-[13px] font-medium text-ink">
            {{ $label }}
            @if ($required)
                <span class="text-ink-faint" aria-hidden="true">*</span>
            @endif
        </label>
    @endif

    {{ $slot }}

    @if ($error)
        <p id="{{ $id }}-error" class="text-[13px] text-red-600 dark:text-red-400">{{ $error }}</p>
    @elseif ($hint)
        <p id="{{ $id }}-hint" class="text-[13px] text-ink-muted">{{ $hint }}</p>
    @endif
</div>
