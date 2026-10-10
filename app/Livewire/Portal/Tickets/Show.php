<?php

namespace App\Livewire\Portal\Tickets;

use App\Enums\MessageType;
use App\Livewire\Concerns\InteractsWithToasts;
use App\Models\Contact;
use App\Models\Ticket;
use App\Models\TicketMessage;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Ticket in the portal: public messages and mails only, never internal notes.
 */
#[Layout('layouts::portal')]
class Show extends Component
{
    use InteractsWithToasts;

    #[Locked]
    public Ticket $ticket;

    public function mount(Ticket $ticket): void
    {
        // Links to a merged ticket lead to the ticket it was merged into.
        if ($ticket->merged_into_id && ($target = Ticket::find($ticket->merged_into_id)) && $this->contact()->can('viewInPortal', $target)) {
            $this->redirectRoute('portal.tickets.show', $target, navigate: true);

            return;
        }

        $this->authorizeView($ticket);
        $this->ticket = $ticket->loadMissing('contact');
    }

    #[Computed]
    public function contact(): Contact
    {
        return auth('contact')->user()->loadMissing('customer');
    }

    /**
     * @return Collection<int, TicketMessage>
     */
    #[Computed]
    public function messages(): Collection
    {
        return $this->ticket->messages()
            ->where('type', '!=', MessageType::Internal->value)
            ->with(['author', 'attachments'])
            ->get();
    }

    public function render()
    {
        return view('livewire.portal.tickets.show')->title($this->ticket->reference());
    }

    private function authorizeView(Ticket $ticket): void
    {
        abort_unless($this->contact()->can('viewInPortal', $ticket), 404);
    }
}
