<?php

namespace App\Livewire\Intern\Tickets;

use App\Livewire\Concerns\InteractsWithToasts;
use App\Models\Ticket;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Right side of the split view (or the whole page on phones).
 */
class TicketView extends Component
{
    use InteractsWithToasts;

    #[Locked]
    public Ticket $ticket;

    public function mount(Ticket $ticket): void
    {
        $this->authorize('view', $ticket);
        $this->ticket = $ticket;
    }

    public function render()
    {
        return view('livewire.intern.tickets.ticket-view');
    }
}
