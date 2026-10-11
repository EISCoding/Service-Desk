<?php

namespace App\Livewire\Concerns;

trait InteractsWithToasts
{
    /**
     * Show a toast on the current page.
     */
    protected function toast(string $message, string $type = 'success'): void
    {
        $this->dispatch('toast', message: $message, type: $type);
    }

    /**
     * Show a toast on the next page (use before a redirect).
     */
    protected function flashToast(string $message, string $type = 'success'): void
    {
        session()->flash('toast', ['message' => $message, 'type' => $type]);
    }
}
