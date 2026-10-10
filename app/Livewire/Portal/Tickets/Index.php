<?php

namespace App\Livewire\Portal\Tickets;

use App\Enums\TicketStatus;
use App\Models\Contact;
use App\Models\Ticket;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Ticket list of the portal: own tickets, main contacts see the whole company.
 */
#[Layout('layouts::portal')]
#[Title('Tickets')]
class Index extends Component
{
    use WithPagination;

    /** open | closed | all */
    #[Url(except: 'open')]
    public string $state = 'open';

    /** Main contacts: mine | company */
    #[Url(except: 'company')]
    public string $scope = 'company';

    #[Url(except: '')]
    public string $search = '';

    public function updated(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function contact(): Contact
    {
        return auth('contact')->user()->loadMissing('customer');
    }

    /**
     * @return LengthAwarePaginator<int, Ticket>
     */
    #[Computed]
    public function tickets(): LengthAwarePaginator
    {
        $search = Ticket::normalizeSearch($this->search);

        return Ticket::query()
            ->visibleToContact($this->contact)
            ->when($this->contact->is_primary && $this->scope === 'mine', fn ($query) => $query->where('contact_id', $this->contact->id))
            ->when($this->state === 'open', fn ($query) => $query->where('status', '!=', TicketStatus::Closed->value))
            ->when($this->state === 'closed', fn ($query) => $query->where('status', TicketStatus::Closed->value))
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('number', 'like', "%{$search}%")
                ->orWhere('subject', 'like', "%{$search}%")))
            ->with(['contact:id,first_name,last_name', 'appointments' => fn ($query) => $query->upcoming()->reorder('starts_at')])
            ->latest('last_activity_at')
            ->latest('id')
            ->paginate(20);
    }

    public function render()
    {
        return view('livewire.portal.tickets.index');
    }
}
