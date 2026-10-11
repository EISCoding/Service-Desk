<?php

namespace App\Livewire\Intern\Settings;

use App\Livewire\Concerns\InteractsWithToasts;
use App\Livewire\Intern\Tickets\TicketList;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Appearance and the view the ticket list opens with.
 */
#[Layout('layouts::intern')]
#[Title('Einstellungen')]
class General extends Component
{
    use InteractsWithToasts;

    public string $ticketView = 'all';

    public function mount(): void
    {
        $this->ticketView = auth()->user()->ticket_view ?? 'all';
    }

    public function updatedTicketView(): void
    {
        $this->validate(['ticketView' => ['required', Rule::in(array_keys(TicketList::VIEWS))]]);

        auth()->user()->update(['ticket_view' => $this->ticketView]);
        // The list opens with the new default instead of the last chosen view.
        session()->forget(TicketList::VIEW_SESSION_KEY);

        $this->toast('Standard-Ansicht gespeichert.');
    }

    public function render()
    {
        return view('livewire.intern.settings.general', ['viewOptions' => TicketList::VIEWS]);
    }
}
