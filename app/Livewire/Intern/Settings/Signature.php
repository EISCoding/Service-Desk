<?php

namespace App\Livewire\Intern\Settings;

use App\Livewire\Concerns\InteractsWithToasts;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * E-mail signature of the logged-in user, appended to public replies.
 */
#[Layout('layouts::intern')]
#[Title('Signatur')]
class Signature extends Component
{
    use InteractsWithToasts;

    public string $signature = '';

    public function mount(): void
    {
        $this->signature = (string) auth()->user()->signature;
    }

    public function save(): void
    {
        $this->validate(['signature' => ['nullable', 'string', 'max:20000']], attributes: ['signature' => 'Signatur']);

        $user = auth()->user();
        $user->signature = $this->signature;
        $user->save();

        $this->signature = (string) $user->signature;
        $this->toast($user->signature ? 'Signatur gespeichert.' : 'Signatur entfernt.');
    }
}
