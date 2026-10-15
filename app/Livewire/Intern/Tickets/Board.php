<?php

namespace App\Livewire\Intern\Tickets;

use App\Enums\SystemTemplate;
use App\Enums\TicketStatus;
use App\Livewire\Concerns\InteractsWithToasts;
use App\Livewire\Concerns\PollsForChanges;
use App\Models\Ticket;
use App\Services\TemplateMailer;
use App\Services\TicketStatusService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Session;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Kanban board: one column per status, drag & drop changes the status with
 * the same rules as the ticket view (follow-up date, optional mail to the customer).
 */
#[Layout('layouts::intern')]
#[Title('Tickets')]
class Board extends Component
{
    use InteractsWithToasts, PollsForChanges;

    /** Closed tickets stay on the board for this many days. */
    public const CLOSED_DAYS = 7;

    private const LIMIT = 500;

    #[Session(key: TicketList::VIEW_SESSION_KEY)]
    public string $view = 'all';

    #[Url(except: '')]
    public string $search = '';

    /** Drop waiting for confirmation (follow-up date, mail to the customer). */
    #[Locked]
    public ?int $movingId = null;

    #[Locked]
    public ?string $movingStatus = null;

    public string $followUpDate = '';

    public bool $notifyCustomer = false;

    public function mount(): void
    {
        $this->authorize('viewAny', Ticket::class);

        // "Tickets" in the navigation opens the layout used last.
        session(['tickets.layout' => 'board']);

        if (! session()->exists(TicketList::VIEW_SESSION_KEY)) {
            $this->view = auth()->user()->ticket_view ?? 'all';
        }

        if (! array_key_exists($this->view, TicketList::VIEWS)) {
            $this->view = 'all';
        }
    }

    public function updatedView(): void
    {
        if (! array_key_exists($this->view, TicketList::VIEWS)) {
            $this->view = 'all';
        }
    }

    /**
     * Tickets per status (value => tickets), closed ones of the last days only.
     *
     * @return array<string, Collection<int, Ticket>>
     */
    #[Computed]
    public function columns(): array
    {
        $tickets = $this->query()
            ->where(fn (Builder $query) => $query
                ->where('status', '!=', TicketStatus::Closed->value)
                ->orWhere('closed_at', '>=', now()->subDays(self::CLOSED_DAYS)))
            ->withReadState(auth()->user())
            ->with(['customer:id,company', 'assignee:id,name'])
            ->orderByRaw("CASE priority WHEN 'urgent' THEN 4 WHEN 'high' THEN 3 WHEN 'normal' THEN 2 ELSE 1 END DESC")
            ->latest('last_activity_at')
            ->limit(self::LIMIT)
            ->get();

        return collect(TicketStatus::cases())
            ->mapWithKeys(fn (TicketStatus $status) => [$status->value => $tickets->where('status', $status)->values()])
            ->all();
    }

    /**
     * Drop of a card into a column (wire:sort): $status is the column, the position is not stored.
     */
    public function moveTicket(int|string $id, int $position, string $status, TicketStatusService $statuses): void
    {
        $ticket = Ticket::query()->visibleTo(auth()->user())->findOrFail((int) $id);
        $this->authorize('update', $ticket);
        $target = TicketStatus::tryFrom($status) ?? abort(422);

        if ($ticket->status === $target) {
            return;
        }

        if ($target === TicketStatus::OnSite) {
            $this->toast('Für „VOS“ bitte im Ticket einen Vor-Ort-Termin planen.', 'info');

            return;
        }

        if ($target->requiresFollowUp() || $this->notification($ticket, $target)) {
            $this->movingId = $ticket->id;
            $this->movingStatus = $target->value;
            $this->followUpDate = TicketStatusService::defaultFollowUp()->toDateString();
            $this->notifyCustomer = in_array($target, [TicketStatus::InProgress, TicketStatus::WaitingCustomer, TicketStatus::Closed], true);
            $this->resetValidation();

            return;
        }

        $statuses->change($ticket, $target);
        $this->moved($ticket, "Status: {$target->label()}");
    }

    public function confirmMove(TicketStatusService $statuses, TemplateMailer $templates): void
    {
        $ticket = $this->movingTicket() ?? abort(404);
        $this->authorize('update', $ticket);
        $target = TicketStatus::from((string) $this->movingStatus);

        $followUp = null;
        if ($target->requiresFollowUp()) {
            $this->validate(['followUpDate' => ['required', 'date', 'after_or_equal:today']], attributes: ['followUpDate' => 'Wiedervorlage']);
            $followUp = Carbon::parse($this->followUpDate);
        }

        $template = $this->notifyCustomer ? $this->notification($ticket, $target) : null;

        DB::transaction(function () use ($statuses, $templates, $ticket, $target, $followUp, $template) {
            $statuses->change($ticket, $target, $followUp);

            if ($template) {
                $templates->send($ticket, $template, auth()->user());
            }
        });

        $message = "Status: {$target->label()}".($followUp ? ', Wiedervorlage am '.$followUp->isoFormat('D. MMM') : '');
        $this->cancelMove();
        $this->moved($ticket, $template ? "{$message}. Der Kunde wird per E-Mail informiert." : $message);
    }

    public function cancelMove(): void
    {
        $this->movingId = null;
        $this->movingStatus = null;
        unset($this->columns);
    }

    public function movingTicket(): ?Ticket
    {
        return $this->movingId ? Ticket::query()->visibleTo(auth()->user())->find($this->movingId) : null;
    }

    /**
     * Template for informing the customer about the new status, if one can be sent.
     */
    public function notification(Ticket $ticket, TicketStatus $status): ?SystemTemplate
    {
        if (! auth()->user()->can('reply', $ticket)) {
            return null;
        }

        $template = $status === TicketStatus::Closed ? SystemTemplate::TicketClosed : SystemTemplate::StatusChanged;

        return app(TemplateMailer::class)->canSend($ticket, $template) ? $template : null;
    }

    protected function changeToken(): string
    {
        return Ticket::fingerprintOf(Ticket::query()->visibleTo(auth()->user()));
    }

    protected function changesDetected(): void
    {
        unset($this->columns);
    }

    public function render()
    {
        return view('livewire.intern.tickets.board');
    }

    private function moved(Ticket $ticket, string $message): void
    {
        unset($this->columns);
        $this->changeToken = $this->changeToken();
        $this->toast("{$ticket->reference()}: {$message}");
    }

    private function query(): Builder
    {
        $search = Ticket::normalizeSearch($this->search);

        return TicketList::queryFor($this->view, auth()->user())
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('subject', 'like', "%{$search}%")
                ->orWhere('number', 'like', "%{$search}%")));
    }
}
