{{--
    Dialog. Open/close via browser events:
        $dispatch('open-modal', { name: 'user-form' })      (Alpine)
        $this->dispatch('open-modal', name: 'user-form')   (Livewire)
    or bind it to a Livewire property with wire:model="showForm".
--}}
@props([
    'name',
    'title' => null,
    'description' => null,
    'maxWidth' => 'lg', // sm | md | lg | xl | 2xl
])

@php
    $model = $attributes->wire('model')->value();
    $width = match ($maxWidth) {
        'sm' => 'sm:max-w-sm',
        'md' => 'sm:max-w-md',
        'xl' => 'sm:max-w-xl',
        '2xl' => 'sm:max-w-2xl',
        default => 'sm:max-w-lg',
    };
@endphp

<div
    x-data="{ show: @if ($model) $wire.entangle(@js($model)) @else false @endif }"
    x-on:open-modal.window="if ($event.detail?.name === @js($name)) show = true"
    x-on:close-modal.window="if ($event.detail?.name === @js($name)) show = false"
    x-on:keydown.escape.window="show = false"
    x-show="show"
    x-cloak
    class="fixed inset-0 z-50 overflow-y-auto"
    role="dialog"
    aria-modal="true"
    @if ($title) aria-labelledby="modal-{{ $name }}-title" @endif
    {{ $attributes->except(['wire:model', 'wire:model.live']) }}
>
    <div
        x-show="show"
        x-transition.opacity.duration.200ms
        x-on:click="show = false"
        class="fixed inset-0 bg-black/25 backdrop-blur-sm dark:bg-black/60"
        aria-hidden="true"
    ></div>

    <div class="relative flex min-h-full items-end justify-center p-0 sm:items-start sm:p-6 sm:pt-[12vh]">
        <div
            x-show="show"
            x-trap.inert.noscroll="show"
            x-transition:enter="ease-out duration-200"
            x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
            x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
            x-transition:leave="ease-in duration-150"
            x-transition:leave-start="opacity-100 sm:scale-100"
            x-transition:leave-end="opacity-0 sm:scale-95"
            class="relative w-full {{ $width }} rounded-t-[22px] bg-surface shadow-pop sm:rounded-[20px]"
        >
            @if ($title)
                <div class="flex items-start justify-between gap-4 px-6 pt-5">
                    <div>
                        <h2 id="modal-{{ $name }}-title" class="text-[17px] font-semibold text-ink">{{ $title }}</h2>
                        @if ($description)
                            <p class="mt-1 text-[13px] text-ink-muted">{{ $description }}</p>
                        @endif
                    </div>
                    <button type="button" x-on:click="show = false" class="-mr-2 rounded-full p-1.5 text-ink-faint hover:bg-surface-muted hover:text-ink focus-visible:outline-2 focus-visible:outline-accent">
                        <span class="sr-only">Schließen</span>
                        <x-bx-x class="size-5" aria-hidden="true" />
                    </button>
                </div>
            @endif

            <div class="px-6 py-5">
                {{ $slot }}
            </div>

            @isset($footer)
                <div class="flex flex-col-reverse gap-2 border-t border-separator px-6 py-4 sm:flex-row sm:justify-end">
                    {{ $footer }}
                </div>
            @endisset
        </div>
    </div>
</div>
