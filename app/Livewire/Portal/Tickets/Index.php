<?php

namespace App\Livewire\Portal\Tickets;

use App\Enums\TicketStatus;
use App\Livewire\Concerns\PollsForChanges;
use App\Models\Contact;
use App\Models\Ticket;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Start page of the portal: greeting, tickets waiting for the customer and the
 * ticket list (own tickets, main contacts see the whole company).
 */
#[Layout('layouts::portal')]
#[Title('Tickets')]
class Index extends Component
{
    use PollsForChanges, WithPagination;

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
     * Tickets where the team asked the customer something.
     *
     * @return Collection<int, Ticket>
     */
    #[Computed]
    public function waiting(): Collection
    {
        return $this->scoped()
            ->where('status', TicketStatus::WaitingCustomer->value)
            ->withReadState($this->contact)
            ->latest('last_activity_at')
            ->limit(4)
            ->get();
    }

    /**
     * @return array{open: int, waiting: int, unread: int}
     */
    #[Computed]
    public function summary(): array
    {
        return [
            'open' => $this->scoped()->where('status', '!=', TicketStatus::Closed->value)->count(),
            'waiting' => $this->scoped()->where('status', TicketStatus::WaitingCustomer->value)->count(),
            'unread' => $this->scoped()->where('status', '!=', TicketStatus::Closed->value)->unreadBy($this->contact)->count(),
        ];
    }

    /**
     * e.g. "2 offene Anfragen, 1 mit neuer Antwort."
     */
    public function summaryText(): string
    {
        ['open' => $open, 'unread' => $unread] = $this->summary;

        if ($open === 0) {
            return 'Sie haben gerade keine offenen Anfragen.';
        }

        $text = $open === 1 ? '1 offene Anfrage' : "{$open} offene Anfragen";

        if ($unread > 0) {
            $text .= $unread === 1 ? ', 1 mit neuer Antwort' : ", {$unread} mit neuen Antworten";
        }

        return $text.'.';
    }

    public function greeting(): string
    {
        $contact = $this->contact;
        $name = $contact->salutation ? $contact->salutation->label().' '.$contact->last_name : $contact->fullName();

        return match (true) {
            now()->hour < 11 => 'Guten Morgen',
            now()->hour < 18 => 'Guten Tag',
            default => 'Guten Abend',
        }.($name !== '' ? ', '.$name : '');
    }

    /**
     * @return LengthAwarePaginator<int, Ticket>
     */
    #[Computed]
    public function tickets(): LengthAwarePaginator
    {
        $search = Ticket::normalizeSearch($this->search);

        return $this->scoped()
            ->when($this->state === 'open', fn ($query) => $query->where('status', '!=', TicketStatus::Closed->value))
            ->when($this->state === 'closed', fn ($query) => $query->where('status', TicketStatus::Closed->value))
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('number', 'like', "%{$search}%")
                ->orWhere('subject', 'like', "%{$search}%")))
            ->withReadState($this->contact)
            ->with(['contact:id,first_name,last_name', 'appointments' => fn ($query) => $query->upcoming()->reorder('starts_at')])
            ->latest('last_activity_at')
            ->latest('id')
            ->paginate(20);
    }

    protected function changeToken(): string
    {
        return Ticket::fingerprintOf(Ticket::query()->visibleToContact($this->contact));
    }

    protected function changesDetected(): void
    {
        unset($this->tickets, $this->waiting, $this->summary);
    }

    public function render()
    {
        return view('livewire.portal.tickets.index');
    }

    private function scoped(): Builder
    {
        return Ticket::query()
            ->visibleToContact($this->contact)
            ->when($this->contact->is_primary && $this->scope === 'mine', fn ($query) => $query->where('contact_id', $this->contact->id));
    }
}
