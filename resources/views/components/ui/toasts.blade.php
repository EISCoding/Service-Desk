{{--
    Toast container, rendered once per layout.
    Trigger from Livewire:  $this->toast('Gespeichert.')   (App\Livewire\Concerns\InteractsWithToasts)
    After a redirect:       session()->flash('toast', ['message' => '…', 'type' => 'success'])
    From Alpine:            $dispatch('toast', { message: '…', type: 'error' })
--}}
<div
    x-data="toasts(@js(session('toast')))"
    x-on:toast.window="add($event.detail)"
    aria-live="polite"
    class="pointer-events-none fixed inset-x-0 top-0 z-[60] flex flex-col items-center gap-2 p-4 sm:items-end"
>
    <template x-for="toast in items" :key="toast.id">
        <div
            x-show="toast.visible"
            x-transition:enter="ease-out duration-200"
            x-transition:enter-start="opacity-0 -translate-y-2"
            x-transition:enter-end="opacity-100 translate-y-0"
            x-transition:leave="ease-in duration-150"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="pointer-events-auto flex w-full max-w-sm items-start gap-3 rounded-2xl bg-surface/90 px-4 py-3 shadow-lg ring-1 ring-separator backdrop-blur-xl"
            role="status"
        >
            <span x-show="toast.type === 'success'" class="text-green-600 dark:text-green-400"><x-heroicon-s-check-circle class="size-5" aria-hidden="true" /></span>
            <span x-show="toast.type === 'error'" class="text-red-600 dark:text-red-400"><x-heroicon-s-x-circle class="size-5" aria-hidden="true" /></span>
            <span x-show="toast.type === 'info'" class="text-accent"><x-heroicon-s-information-circle class="size-5" aria-hidden="true" /></span>
            <p class="flex-1 pt-px text-sm text-ink" x-text="toast.message"></p>
            <button type="button" x-on:click="remove(toast.id)" class="-mr-1 rounded-full p-0.5 text-ink-faint hover:text-ink">
                <span class="sr-only">Schließen</span>
                <x-heroicon-m-x-mark class="size-4" aria-hidden="true" />
            </button>
        </div>
    </template>
</div>
