<?php

namespace App\Livewire\Concerns;

use Livewire\Attributes\Locked;

/**
 * Live updates without a websocket server: the view polls checkForChanges()
 * (wire:poll), which compares a cheap fingerprint of the shown data and only
 * re-renders when something changed.
 */
trait PollsForChanges
{
    #[Locked]
    public string $changeToken = '';

    /**
     * Fingerprint of the data the component shows (one or two cheap queries).
     */
    abstract protected function changeToken(): string;

    /**
     * Called before the re-render when the fingerprint changed (refresh models, reset computed values).
     */
    protected function changesDetected(): void {}

    public function mountPollsForChanges(): void
    {
        $this->changeToken = $this->changeToken();
    }

    public function checkForChanges(): void
    {
        $token = $this->changeToken();

        if ($token === $this->changeToken) {
            $this->skipRender();

            return;
        }

        $this->changeToken = $token;
        $this->changesDetected();
    }
}
