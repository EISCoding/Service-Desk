<?php

namespace App\Livewire\Intern\Tickets;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Livewire\Concerns\PollsForChanges;
use App\Models\Group;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Attributes\Session;
use Livewire\Component;

/**
 * Ticket list with views (Meine / Meine Gruppen / Alle / Nicht zugeordnet),
 * filters and search. Filters are kept in the session, so they survive
 * switching between tickets in the split view.
 */
class TicketList extends Component
{
    use PollsForChanges;

    public const VIEWS = [
        'mine' => 'Meine Tickets',
        'groups' => 'Meine Gruppen',
        'all' => 'Alle Tickets',
        'unassigned' => 'Nicht zugeordnet',
    ];

    private const PAGE_SIZE = 30;

    #[Locked]
    public ?int $selectedId = null;

    /** Session key of the chosen view; without it the list opens with the user's default view. */
    public const VIEW_SESSION_KEY = 'tickets.view';

    #[Session(key: self::VIEW_SESSION_KEY)]
    public string $view = 'all';

    #[Session]
    public string $search = '';

    /** open | all | <status value> */
    #[Session]
    public string $status = 'open';

    #[Session]
    public string $priority = '';

    /** '' | 'none' | group id */
    #[Session]
    public string $group = '';

    /** '' | 'none' | user id */
    #[Session]
    public string $assignee = '';

    #[Session]
    public string $customer = '';

    #[Session]
    public bool $withoutCustomer = false;

    #[Session]
    public bool $dueOnly = false;

    #[Session]
    public bool $unreadOnly = false;

    /** activity | priority | created */
    #[Session]
    public string $sort = 'activity';

    public int $limit = self::PAGE_SIZE;

    /**
     * @param  string|null  $preset  start from a dashboard figure or sidebar view: mine | unread | due | unassigned | all
     */
    public function mount(?int $selectedId = null, ?string $preset = null): void
    {
        $this->authorize('viewAny', Ticket::class);

        $this->selectedId = $selectedId;

        if (! session()->exists(self::VIEW_SESSION_KEY)) {
            $this->view = auth()->user()->ticket_view ?? 'all';
        }

        if (in_array($preset, ['mine', 'unread', 'due', 'unassigned', 'all'], true)) {
            $this->resetFilters();
            $this->view = in_array($preset, ['mine', 'unassigned'], true) ? $preset : 'all';
            $this->unreadOnly = $preset === 'unread';
            $this->dueOnly = $preset === 'due';
        }

        if (! array_key_exists($this->view, self::VIEWS)) {
            $this->view = 'all';
        }
    }

    public function setView(string $view): void
    {
        $this->view = array_key_exists($view, self::VIEWS) ? $view : 'all';
        $this->limit = self::PAGE_SIZE;
    }

    public function loadMore(): void
    {
        $this->limit += self::PAGE_SIZE;
    }

    public function resetFilters(): void
    {
        $this->reset('search', 'status', 'priority', 'group', 'assignee', 'customer', 'withoutCustomer', 'dueOnly', 'unreadOnly', 'sort');
        $this->limit = self::PAGE_SIZE;
    }

    public function updated(string $property): void
    {
        if ($property !== 'limit') {
            $this->limit = self::PAGE_SIZE;
        }
    }

    #[On('ticket-updated')]
    public function refreshList(): void
    {
        unset($this->tickets, $this->counts);
    }

    protected function changeToken(): string
    {
        return Ticket::fingerprintOf(Ticket::query()->visibleTo(auth()->user()));
    }

    protected function changesDetected(): void
    {
        $this->refreshList();
    }

    /**
     * Number of active filters (shown on the filter button).
     */
    public function activeFilterCount(): int
    {
        return count(array_filter([
            $this->status !== 'open', $this->priority !== '', $this->group !== '', $this->assignee !== '',
            trim($this->customer) !== '', $this->withoutCustomer, $this->dueOnly, $this->unreadOnly, $this->sort !== 'activity',
        ]));
    }

    /**
     * @return Collection<int, Ticket>
     */
    #[Computed]
    public function tickets(): Collection
    {
        $query = $this->viewQuery($this->view);
        $this->applyFilters($query);

        match ($this->sort) {
            'priority' => $query->orderByRaw("CASE priority WHEN 'urgent' THEN 4 WHEN 'high' THEN 3 WHEN 'normal' THEN 2 ELSE 1 END DESC")->latest('last_activity_at'),
            'created' => $query->latest('created_at'),
            default => $query->latest('last_activity_at'),
        };

        return $query
            ->withReadState(auth()->user())
            ->with(['customer:id,company', 'contact:id,first_name,last_name', 'assignee:id,name'])
            ->orderByDesc('id')
            ->limit($this->limit + 1)
            ->get();
    }

    /**
     * Open tickets per view (tab badges).
     *
     * @return array<string, int>
     */
    #[Computed]
    public function counts(): array
    {
        return collect(self::VIEWS)->map(fn ($label, $view) => $this->viewQuery($view)->open()->count())->all();
    }

    /**
     * @return array<int|string, string>
     */
    #[Computed]
    public function groupOptions(): array
    {
        return ['none' => 'Ohne Gruppe'] + Group::orderBy('name')->pluck('name', 'id')->all();
    }

    /**
     * @return array<int|string, string>
     */
    #[Computed]
    public function assigneeOptions(): array
    {
        return ['none' => 'Ohne Bearbeiter'] + User::active()->orderBy('name')->pluck('name', 'id')->all();
    }

    private function viewQuery(string $view): Builder
    {
        return self::queryFor($view, auth()->user());
    }

    /**
     * Tickets of a view (Meine / Meine Gruppen / Alle / Nicht zugeordnet), shared with the board.
     */
    public static function queryFor(string $view, User $user): Builder
    {
        $query = Ticket::query()->visibleTo($user);

        return match ($view) {
            'mine' => $query->where('assignee_id', $user->id),
            'groups' => $query->whereIn('group_id', $user->groupIds()),
            'unassigned' => $query->whereNull('assignee_id'),
            default => $query,
        };
    }

    private function applyFilters(Builder $query): void
    {
        $search = Ticket::normalizeSearch($this->search);
        $customer = trim($this->customer);

        $query
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('subject', 'like', "%{$search}%")
                ->orWhere('number', 'like', "%{$search}%")))
            ->when($this->status === 'open', fn ($query) => $query->open())
            ->when(TicketStatus::tryFrom($this->status), fn ($query, $status) => $query->where('status', $status->value))
            ->when(TicketPriority::tryFrom($this->priority), fn ($query, $priority) => $query->where('priority', $priority->value))
            ->when($this->group === 'none', fn ($query) => $query->whereNull('group_id'))
            ->when(ctype_digit($this->group), fn ($query) => $query->where('group_id', (int) $this->group))
            ->when($this->assignee === 'none', fn ($query) => $query->whereNull('assignee_id'))
            ->when(ctype_digit($this->assignee), fn ($query) => $query->where('assignee_id', (int) $this->assignee))
            ->when($customer !== '', fn ($query) => $query->whereHas('customer', fn ($query) => $query
                ->where('company', 'like', "%{$customer}%")
                ->orWhere('number', 'like', "%{$customer}%")))
            ->when($this->withoutCustomer, fn ($query) => $query->whereNull('customer_id'))
            ->when($this->dueOnly, fn ($query) => $query->where('follow_up_due', true))
            ->when($this->unreadOnly, fn ($query) => $query->unreadBy(auth()->user()));
    }

    public function render()
    {
        $tickets = $this->tickets;

        return view('livewire.intern.tickets.ticket-list', [
            'items' => $tickets->take($this->limit),
            'hasMore' => $tickets->count() > $this->limit,
            'statusOptions' => ['open' => 'Offene', 'all' => 'Alle'] + collect(TicketStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()])->all(),
            'priorityOptions' => collect(TicketPriority::cases())->mapWithKeys(fn ($p) => [$p->value => $p->label()])->all(),
        ]);
    }
}
